<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormationEnrollment;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\CertificateRequestService;
use App\Services\CertificateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FormateurCertificateController extends Controller
{
    public function __construct(
        private CertificateRequestService $certificateRequestService,
        private CertificateService $certificateService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $formateur = Auth::user();

        $query = FormationEnrollment::query()
            ->with([
                'user',
                'formation',
                'session',
                'certificate',
                'certificateRequest.requestedBy',
                'certificateRequest.decisionBy',
                'certificateRequest.invalidatedBy',
            ])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function (Builder $builder) use ($formateur): void {
                $builder->whereHas('session', function (Builder $sessionQuery) use ($formateur): void {
                    $sessionQuery->where('formateur_id', $formateur->id);
                })->orWhereHas('formation', function (Builder $formationQuery) use ($formateur): void {
                    $formationQuery->where('formateur_id', $formateur->id);
                });
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                })->orWhere('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereHas('formation', function (Builder $formationQuery) use ($search): void {
                        $formationQuery->where('title', 'like', '%' . $search . '%');
                    });
            });
        }

        $enrollments = $query
            ->orderByRaw("CASE WHEN status = 'completed' THEN 0 ELSE 1 END")
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $enrollments->map(fn (FormationEnrollment $enrollment) => $this->mapEnrollment($enrollment))->values(),
        ]);
    }

    public function requestCertificate(Request $request, int $enrollmentId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $formateur = Auth::user();

        $enrollment = FormationEnrollment::query()
            ->with([
                'user',
                'formation',
                'session',
                'certificate',
                'certificateRequest.requestedBy',
                'certificateRequest.decisionBy',
                'certificateRequest.invalidatedBy',
            ])
            ->findOrFail($enrollmentId);

        if (!$this->canManageEnrollment($formateur, $enrollment)) {
            abort(403, 'Vous ne pouvez pas demander un certificat pour cette inscription.');
        }

        $requestModel = $this->certificateRequestService->requestByTrainer(
            $enrollment,
            $formateur,
            $validated['notes'] ?? null
        );

        $freshEnrollment = $requestModel->enrollment->fresh([
            'user',
            'formation',
            'session',
            'certificate',
            'certificateRequest.requestedBy',
            'certificateRequest.decisionBy',
            'certificateRequest.invalidatedBy',
        ]);

        ActivityLogService::log(
            'Demande de certificat soumise par le formateur',
            ($formateur?->name ?? 'Un formateur') . " a demandé la génération du certificat pour l'inscription {$freshEnrollment->id}.",
            $freshEnrollment,
            $formateur?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'La demande de certificat a été transmise au secrétariat.',
            'data' => $this->mapEnrollment($freshEnrollment),
        ]);
    }

    private function canManageEnrollment(User $formateur, FormationEnrollment $enrollment): bool
    {
        return $enrollment->session?->formateur_id === $formateur->id
            || $enrollment->formation?->formateur_id === $formateur->id;
    }

    private function mapEnrollment(FormationEnrollment $enrollment): array
    {
        $certificate = $enrollment->certificate;
        $request = $enrollment->certificateRequest;
        $hasActiveCertificate = $certificate !== null
            && $certificate->revoked_at === null
            && ($request === null || $request->isApproved());

        $workflowStatus = match (true) {
            $hasActiveCertificate => 'generated',
            $request?->status === 'pending' => 'pending_secretary',
            $request?->status === 'approved' => 'approved',
            $request?->status === 'rejected' => 'rejected',
            $request?->status === 'invalidated' => 'invalidated',
            $enrollment->status === 'completed' => 'ready_for_request',
            default => 'in_progress',
        };

        $canRequest = in_array($workflowStatus, ['in_progress', 'ready_for_request', 'rejected', 'invalidated'], true)
            && !$hasActiveCertificate;

        return [
            'enrollment_id' => $enrollment->id,
            'learner_name' => $enrollment->full_name ?: 'Apprenant',
            'learner_email' => $enrollment->user?->email ?: $enrollment->email,
            'learner_phone' => $enrollment->user?->phone ?: $enrollment->phone,
            'formation' => $enrollment->formation?->title ?: 'Formation',
            'formation_id' => $enrollment->formation_id,
            'session_id' => $enrollment->session_id,
            'session_start_date' => $enrollment->session?->start_date?->toDateString(),
            'session_end_date' => $enrollment->session?->end_date?->toDateString(),
            'session_location' => $enrollment->session?->location,
            'enrollment_status' => $enrollment->status,
            'completed_at' => $enrollment->completed_at?->toIso8601String(),
            'completedDate' => $enrollment->completed_at?->format('d/m/Y'),
            'workflow_status' => $workflowStatus,
            'workflow_label' => match ($workflowStatus) {
                'generated' => 'Certificat généré',
                'pending_secretary' => 'En attente du secrétariat',
                'approved' => 'Approuvé, génération en cours',
                'rejected' => 'Refusé par le secrétariat',
                'invalidated' => 'Certificat invalidé',
                'ready_for_request' => 'Formation terminée, demande possible',
                default => 'Formation en cours',
            },
            'request_id' => $request?->id,
            'requested_at' => $request?->requested_at?->toIso8601String(),
            'requestedDate' => $request?->requested_at?->format('d/m/Y H:i'),
            'requested_by_name' => $request?->requestedBy?->name,
            'decision_at' => $request?->decision_at?->toIso8601String(),
            'decisionDate' => $request?->decision_at?->format('d/m/Y H:i'),
            'decision_notes' => $request?->decision_notes,
            'invalidation_reason' => $request?->invalidation_reason,
            'certificate_reference' => $certificate?->reference,
            'verification_path' => $certificate?->reference
                ? $this->certificateService->buildVerificationPath($certificate->reference)
                : null,
            'can_request' => $canRequest,
        ];
    }
}
