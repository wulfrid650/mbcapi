<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\FormationEnrollment;
use App\Models\SiteSetting;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    public function issueForEnrollment(
        FormationEnrollment $enrollment,
        ?User $issuedBy = null,
        bool $forceRegenerate = false
    ): Certificate {
        return DB::transaction(function () use ($enrollment, $issuedBy, $forceRegenerate) {
            $enrollment = FormationEnrollment::query()
                ->with([
                    'user',
                    'formation.formateur',
                    'session.formateur',
                    'certificate',
                ])
                ->lockForUpdate()
                ->findOrFail($enrollment->id);

            if ($enrollment->status !== 'completed') {
                throw new \RuntimeException('Le certificat ne peut être généré que pour une inscription terminée.');
            }

            if (!$enrollment->completed_at) {
                $enrollment->update([
                    'completed_at' => now(),
                ]);
                $enrollment->refresh();
            }

            $certificate = $enrollment->certificate;

            if (!$certificate) {
                $certificate = Certificate::create([
                    'formation_enrollment_id' => $enrollment->id,
                    'reference' => $this->generateReference(),
                    'generated_by' => $issuedBy?->id,
                ]);
            }

            if (!$certificate->reference) {
                $certificate->reference = $this->generateReference();
            }

            if ($certificate->revoked_at !== null || $certificate->issued_at === null) {
                $certificate->issued_at = now();
            }

            $certificate->generated_by = $issuedBy?->id ?? $certificate->generated_by;
            $certificate->revoked_at = null;
            $certificate->revoked_reason = null;

            if (
                !$forceRegenerate
                && $certificate->pdf_path
                && Storage::disk('public')->exists($certificate->pdf_path)
            ) {
                $certificate->metadata = $this->buildCertificateMetadata($certificate, $enrollment);
                $certificate->save();
                $this->syncEnrollmentMetadata($enrollment, $certificate);

                return $certificate->fresh([
                    'enrollment.user',
                    'enrollment.formation.formateur',
                    'enrollment.session.formateur',
                ]);
            }

            $pdf = Pdf::loadView('pdf.certificate', $this->buildPdfViewData($certificate, $enrollment));
            $pdf->setPaper('a4', 'portrait');

            $filename = sprintf('certificates/%s/%s.pdf', now()->format('Y/m'), $certificate->reference);
            $written = Storage::disk('public')->put($filename, $pdf->output());

            if ($written !== true) {
                throw new \RuntimeException('Impossible de sauvegarder le certificat PDF.');
            }

            $certificate->pdf_path = $filename;
            $certificate->metadata = $this->buildCertificateMetadata($certificate, $enrollment);
            $certificate->save();

            $this->syncEnrollmentMetadata($enrollment, $certificate);

            return $certificate->fresh([
                'enrollment.user',
                'enrollment.formation.formateur',
                'enrollment.session.formateur',
            ]);
        });
    }

    public function revokeForEnrollment(FormationEnrollment $enrollment, ?string $reason = null): ?Certificate
    {
        $certificate = Certificate::query()
            ->where('formation_enrollment_id', $enrollment->id)
            ->first();

        if (!$certificate) {
            return null;
        }

        if ($certificate->revoked_at === null) {
            $certificate->update([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
                'metadata' => array_merge($certificate->metadata ?? [], [
                    'status' => 'revoked',
                    'revoked_at' => now()->toIso8601String(),
                    'revoked_reason' => $reason,
                ]),
            ]);
        }

        $this->syncEnrollmentMetadata($enrollment->fresh(), $certificate->fresh());

        return $certificate->fresh();
    }

    public function buildVerificationPath(string $reference): string
    {
        return '/certificats/verifier/' . rawurlencode(Str::upper($reference));
    }

    public function buildVerificationUrl(string $reference): string
    {
        $baseUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

        return $baseUrl . $this->buildVerificationPath($reference);
    }

    private function generateReference(): string
    {
        do {
            $reference = 'CERT-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (Certificate::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function buildCertificateMetadata(Certificate $certificate, FormationEnrollment $enrollment): array
    {
        return array_merge($certificate->metadata ?? [], [
            'status' => $certificate->revoked_at ? 'revoked' : 'issued',
            'verification_path' => $this->buildVerificationPath($certificate->reference),
            'verification_url' => $this->buildVerificationUrl($certificate->reference),
            'participant_name' => $enrollment->full_name,
            'formation_title' => $enrollment->formation?->title,
            'session_start_date' => $enrollment->session?->start_date?->toDateString(),
            'session_end_date' => $enrollment->session?->end_date?->toDateString(),
        ]);
    }

    private function syncEnrollmentMetadata(FormationEnrollment $enrollment, Certificate $certificate): void
    {
        $metadata = array_merge(is_array($enrollment->metadata) ? $enrollment->metadata : [], [
            'certificate_reference' => $certificate->reference,
            'certificate_url' => $certificate->pdf_path,
            'certificate_issued_at' => $certificate->issued_at?->toIso8601String(),
            'certificate_verification_path' => $this->buildVerificationPath($certificate->reference),
            'certificate_verification_url' => $this->buildVerificationUrl($certificate->reference),
            'certificate_status' => $certificate->revoked_at ? 'revoked' : 'issued',
            'certificate_revoked_at' => $certificate->revoked_at?->toIso8601String(),
        ]);

        $enrollment->update([
            'metadata' => $metadata,
        ]);
    }

    private function buildPdfViewData(Certificate $certificate, FormationEnrollment $enrollment): array
    {
        $session = $enrollment->session;
        $formation = $enrollment->formation;
        $trainer = $session?->formateur ?? $formation?->formateur;
        $issuedAt = $certificate->issued_at ?? now();

        return [
            'certificate' => $certificate,
            'reference' => $certificate->reference,
            'issued_at' => $issuedAt,
            'participant_name' => $enrollment->full_name,
            'formation_title' => $formation?->title ?? 'Formation MBC',
            'trainer_name' => $trainer?->name,
            'session_start_date' => $session?->start_date,
            'session_end_date' => $session?->end_date,
            'session_location' => $session?->location,
            'duration_label' => $this->resolveDurationLabel($enrollment),
            'verification_url' => $this->buildVerificationUrl($certificate->reference),
            'qr_code_data_uri' => $this->buildQrCodeDataUri($certificate->reference),
            'company' => [
                'name' => SiteSetting::get('company_name', 'MADIBA BUILDING CONSTRUCTION SARL'),
                'short_name' => SiteSetting::get('company_short_name', 'MBC'),
                'city' => SiteSetting::get('address_city', 'Douala'),
                'address' => SiteSetting::get('address_full', SiteSetting::get('address', 'Douala, Cameroun')),
                'phone' => SiteSetting::get('phone', '+237'),
                'email' => SiteSetting::get('email', 'contact@madibabc.com'),
                'website' => SiteSetting::get('website_url', config('app.frontend_url')),
                'rccm' => SiteSetting::get('company_rccm', ''),
                'niu' => SiteSetting::get('company_niu', ''),
            ],
        ];
    }

    private function resolveDurationLabel(FormationEnrollment $enrollment): string
    {
        $formation = $enrollment->formation;
        $session = $enrollment->session;

        if (!empty($formation?->duration_days)) {
            $days = (int) $formation->duration_days;

            return $days . ' ' . Str::plural('jour', $days);
        }

        if (!empty($formation?->duration_hours)) {
            return (int) $formation->duration_hours . ' heure(s)';
        }

        if ($session?->start_date && $session?->end_date) {
            $days = (int) $session->start_date->diffInDays($session->end_date) + 1;

            return $days . ' ' . Str::plural('jour', $days);
        }

        return 'Durée validée par MBC';
    }

    private function buildQrCodeDataUri(string $reference): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(180, 2),
                new SvgImageBackEnd()
            )
        );

        $svg = $writer->writeString($this->buildVerificationUrl($reference));

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
