<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Models\FormationEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CertificateRequestService
{
    public function requestByTrainer(
        FormationEnrollment $enrollment,
        User $trainer,
        ?string $notes = null
    ): CertificateRequest {
        return DB::transaction(function () use ($enrollment, $trainer, $notes): CertificateRequest {
            $enrollment = FormationEnrollment::query()
                ->with(['certificateRequest', 'certificate', 'session', 'formation'])
                ->lockForUpdate()
                ->findOrFail($enrollment->id);

            if (!$this->trainerCanManageEnrollment($trainer, $enrollment)) {
                throw new \RuntimeException('Le formateur ne peut pas demander un certificat pour cette inscription.');
            }

            if (in_array($enrollment->status, ['pending_payment', 'cancelled'], true)) {
                throw new \RuntimeException('Le certificat ne peut être demandé que pour une inscription validée.');
            }

            if ($enrollment->certificate && $enrollment->certificate->revoked_at === null) {
                throw new \RuntimeException('Un certificat actif existe déjà pour cette inscription.');
            }

            $request = $enrollment->certificateRequest;

            $enrollmentMetadata = array_merge(is_array($enrollment->metadata) ? $enrollment->metadata : [], [
                'last_status_update_by' => $trainer->id,
                'last_status_update_by_name' => $trainer->name,
                'last_status_update_at' => now()->toISOString(),
                'certificate_requested_by_trainer' => true,
                'certificate_requested_at' => now()->toIso8601String(),
            ]);

            $enrollment->update([
                'status' => 'completed',
                'completed_at' => $enrollment->completed_at ?? now(),
                'metadata' => $enrollmentMetadata,
            ]);
            $enrollment->refresh();

            $payload = [
                'requested_by' => $trainer->id,
                'status' => 'pending',
                'requested_at' => now(),
                'decision_by' => null,
                'decision_at' => null,
                'decision_notes' => null,
                'invalidated_by' => null,
                'invalidated_at' => null,
                'invalidation_reason' => null,
                'metadata' => array_merge(is_array($request?->metadata) ? $request->metadata : [], [
                    'request_origin' => 'trainer',
                    'last_requested_by' => $trainer->id,
                    'last_requested_by_name' => $trainer->name,
                    'last_requested_at' => now()->toIso8601String(),
                    'request_notes' => $notes,
                ]),
            ];

            if ($request) {
                $request->update($payload);
            } else {
                $request = CertificateRequest::query()->create(array_merge($payload, [
                    'formation_enrollment_id' => $enrollment->id,
                ]));
            }

            return $request->fresh([
                'enrollment.user',
                'enrollment.formation',
                'enrollment.session',
                'enrollment.certificate',
                'requestedBy',
                'decisionBy',
                'invalidatedBy',
            ]);
        });
    }

    public function approve(CertificateRequest $request, User $actor, ?string $notes = null): CertificateRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes): CertificateRequest {
            $request = CertificateRequest::query()
                ->with([
                    'enrollment.user',
                    'enrollment.formation.formateur',
                    'enrollment.session.formateur',
                    'enrollment.certificate',
                ])
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($request->enrollment?->status !== 'completed') {
                throw new \RuntimeException('La demande ne peut être approuvée que pour une inscription terminée.');
            }

            if (
                $request->status === 'approved'
                && $request->enrollment?->certificate
                && $request->enrollment->certificate->revoked_at === null
            ) {
                return $request->fresh([
                    'enrollment.user',
                    'enrollment.formation',
                    'enrollment.session',
                    'enrollment.certificate',
                    'requestedBy',
                    'decisionBy',
                    'invalidatedBy',
                ]);
            }

            $request->update([
                'status' => 'approved',
                'decision_by' => $actor->id,
                'decision_at' => now(),
                'decision_notes' => $notes,
                'invalidated_by' => null,
                'invalidated_at' => null,
                'invalidation_reason' => null,
                'metadata' => array_merge(is_array($request->metadata) ? $request->metadata : [], [
                    'approved_by' => $actor->id,
                    'approved_by_name' => $actor->name,
                    'approved_at' => now()->toIso8601String(),
                ]),
            ]);

            $certificate = app(CertificateService::class)->issueForEnrollment(
                $request->enrollment,
                $actor
            );

            $request->update([
                'metadata' => array_merge(is_array($request->metadata) ? $request->metadata : [], [
                    'certificate_reference' => $certificate->reference,
                    'certificate_generated_at' => $certificate->issued_at?->toIso8601String(),
                ]),
            ]);

            return $request->fresh([
                'enrollment.user',
                'enrollment.formation',
                'enrollment.session',
                'enrollment.certificate',
                'requestedBy',
                'decisionBy',
                'invalidatedBy',
            ]);
        });
    }

    public function reject(CertificateRequest $request, User $actor, ?string $notes = null): CertificateRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes): CertificateRequest {
            $request = CertificateRequest::query()
                ->with('enrollment.certificate')
                ->lockForUpdate()
                ->findOrFail($request->id);

            $request->update([
                'status' => 'rejected',
                'decision_by' => $actor->id,
                'decision_at' => now(),
                'decision_notes' => $notes,
                'invalidated_by' => null,
                'invalidated_at' => null,
                'invalidation_reason' => null,
                'metadata' => array_merge(is_array($request->metadata) ? $request->metadata : [], [
                    'rejected_by' => $actor->id,
                    'rejected_by_name' => $actor->name,
                    'rejected_at' => now()->toIso8601String(),
                ]),
            ]);

            return $request->fresh([
                'enrollment.user',
                'enrollment.formation',
                'enrollment.session',
                'enrollment.certificate',
                'requestedBy',
                'decisionBy',
                'invalidatedBy',
            ]);
        });
    }

    public function invalidate(CertificateRequest $request, User $actor, ?string $reason = null): CertificateRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason): CertificateRequest {
            $request = CertificateRequest::query()
                ->with('enrollment.certificate')
                ->lockForUpdate()
                ->findOrFail($request->id);

            $request->update([
                'status' => 'invalidated',
                'invalidated_by' => $actor->id,
                'invalidated_at' => now(),
                'invalidation_reason' => $reason,
                'metadata' => array_merge(is_array($request->metadata) ? $request->metadata : [], [
                    'invalidated_by' => $actor->id,
                    'invalidated_by_name' => $actor->name,
                    'invalidated_at' => now()->toIso8601String(),
                    'invalidation_reason' => $reason,
                ]),
            ]);

            if ($request->enrollment) {
                app(CertificateService::class)->revokeForEnrollment(
                    $request->enrollment,
                    $reason ?: 'Certificat invalidé par le secrétariat.'
                );
            }

            return $request->fresh([
                'enrollment.user',
                'enrollment.formation',
                'enrollment.session',
                'enrollment.certificate',
                'requestedBy',
                'decisionBy',
                'invalidatedBy',
            ]);
        });
    }

    public function invalidateEnrollment(
        FormationEnrollment $enrollment,
        ?User $actor = null,
        ?string $reason = null
    ): ?CertificateRequest {
        $request = $enrollment->relationLoaded('certificateRequest')
            ? $enrollment->certificateRequest
            : $enrollment->certificateRequest()->first();

        if (!$request) {
            if ($enrollment->certificate) {
                app(CertificateService::class)->revokeForEnrollment(
                    $enrollment,
                    $reason ?: 'Le statut de la formation ne permet plus ce certificat.'
                );
            }

            return null;
        }

        if (!$actor) {
            $request->update([
                'status' => 'invalidated',
                'invalidated_at' => now(),
                'invalidation_reason' => $reason ?: 'Le statut de la formation ne permet plus ce certificat.',
                'metadata' => array_merge(is_array($request->metadata) ? $request->metadata : [], [
                    'invalidated_by_system' => true,
                    'invalidated_at' => now()->toIso8601String(),
                    'invalidation_reason' => $reason ?: 'Le statut de la formation ne permet plus ce certificat.',
                ]),
            ]);

            app(CertificateService::class)->revokeForEnrollment(
                $enrollment,
                $reason ?: 'Le statut de la formation ne permet plus ce certificat.'
            );

            return $request->fresh([
                'enrollment.user',
                'enrollment.formation',
                'enrollment.session',
                'enrollment.certificate',
                'requestedBy',
                'decisionBy',
                'invalidatedBy',
            ]);
        }

        return $this->invalidate($request, $actor, $reason ?: 'Le statut de la formation ne permet plus ce certificat.');
    }

    private function trainerCanManageEnrollment(User $trainer, FormationEnrollment $enrollment): bool
    {
        return $enrollment->session?->formateur_id === $trainer->id
            || $enrollment->formation?->formateur_id === $trainer->id;
    }
}
