<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateService $certificateService
    ) {
    }

    public function verify(string $reference): JsonResponse
    {
        $reference = Str::upper(trim($reference));

        $certificate = Certificate::query()
            ->with([
                'enrollment.user',
                'enrollment.formation.formateur',
                'enrollment.session.formateur',
            ])
            ->where('reference', $reference)
            ->first();

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificat introuvable.',
            ], 404);
        }

        $enrollment = $certificate->enrollment;
        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'Certificat invalide.',
            ], 404);
        }

        if (
            $certificate->revoked_at === null
            && (!$certificate->pdf_path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($certificate->pdf_path))
            && $enrollment->status === 'completed'
        ) {
            $certificate = $this->certificateService->issueForEnrollment($enrollment);
            $enrollment = $certificate->enrollment;
        }

        $trainer = $enrollment->session?->formateur ?? $enrollment->formation?->formateur;
        $isValid = $certificate->isValid();

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $isValid,
                'status' => $isValid ? 'valid' : ($certificate->revoked_at ? 'revoked' : 'invalid'),
                'status_label' => $isValid
                    ? 'Certificat authentique'
                    : ($certificate->revoked_at ? 'Certificat révoqué' : 'Certificat invalide'),
                'reference' => $certificate->reference,
                'learner_name' => $enrollment->full_name,
                'formation' => $enrollment->formation?->title,
                'instructor' => $trainer?->name,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'completed_at' => $enrollment->completed_at?->toIso8601String(),
                'revoked_at' => $certificate->revoked_at?->toIso8601String(),
                'revoked_reason' => $certificate->revoked_reason,
                'verification_path' => $this->certificateService->buildVerificationPath($certificate->reference),
                'verification_url' => $this->certificateService->buildVerificationUrl($certificate->reference),
                'session' => [
                    'start_date' => $enrollment->session?->start_date?->toDateString(),
                    'end_date' => $enrollment->session?->end_date?->toDateString(),
                    'location' => $enrollment->session?->location,
                ],
            ],
        ]);
    }
}
