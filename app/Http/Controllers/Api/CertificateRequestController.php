<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificateRequest;
use App\Models\FormationEnrollment;
use App\Services\ActivityLogService;
use App\Services\CertificateRequestService;
use App\Services\CertificateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CertificateRequestController extends Controller
{
    public function __construct(
        private CertificateRequestService $certificateRequestService,
        private CertificateService $certificateService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = CertificateRequest::query()
            ->with([
                'enrollment.user',
                'enrollment.formation',
                'enrollment.session',
                'enrollment.certificate',
                'requestedBy',
                'decisionBy',
                'invalidatedBy',
            ]);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('formation_id')) {
            $query->whereHas('enrollment', function (Builder $builder) use ($request): void {
                $builder->where('formation_id', $request->integer('formation_id'));
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('enrollment.user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                })->orWhereHas('enrollment', function (Builder $enrollmentQuery) use ($search): void {
                    $enrollmentQuery->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhereHas('formation', function (Builder $formationQuery) use ($search): void {
                            $formationQuery->where('title', 'like', '%' . $search . '%');
                        });
                })->orWhereHas('enrollment.certificate', function (Builder $certificateQuery) use ($search): void {
                    $certificateQuery->where('reference', 'like', '%' . $search . '%');
                });
            });
        }

        $requests = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('requested_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => collect($requests->items())->map(fn (CertificateRequest $certificateRequest) => $this->mapRequest($certificateRequest))->values(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function approve(Request $request, CertificateRequest $certificateRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $actor = Auth::user();
        $certificateRequest = $this->certificateRequestService->approve(
            $certificateRequest,
            $actor,
            $validated['notes'] ?? null
        );

        ActivityLogService::log(
            'Demande de certificat approuvée',
            ($actor?->name ?? 'Un membre du staff') . " a approuvé la demande de certificat pour l'inscription {$certificateRequest->formation_enrollment_id}.",
            $certificateRequest->enrollment,
            $actor?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Demande approuvée et certificat généré.',
            'data' => $this->mapRequest($certificateRequest),
        ]);
    }

    public function reject(Request $request, CertificateRequest $certificateRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $actor = Auth::user();
        $certificateRequest = $this->certificateRequestService->reject(
            $certificateRequest,
            $actor,
            $validated['notes']
        );

        ActivityLogService::log(
            'Demande de certificat rejetée',
            ($actor?->name ?? 'Un membre du staff') . " a rejeté la demande de certificat pour l'inscription {$certificateRequest->formation_enrollment_id}.",
            $certificateRequest->enrollment,
            $actor?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Demande de certificat rejetée.',
            'data' => $this->mapRequest($certificateRequest),
        ]);
    }

    public function invalidate(Request $request, CertificateRequest $certificateRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $actor = Auth::user();
        $certificateRequest = $this->certificateRequestService->invalidate(
            $certificateRequest,
            $actor,
            $validated['reason']
        );

        ActivityLogService::log(
            'Certificat invalidé',
            ($actor?->name ?? 'Un membre du staff') . " a invalidé le certificat pour l'inscription {$certificateRequest->formation_enrollment_id}.",
            $certificateRequest->enrollment,
            $actor?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Certificat invalidé.',
            'data' => $this->mapRequest($certificateRequest),
        ]);
    }

    private function mapRequest(CertificateRequest $certificateRequest): array
    {
        $enrollment = $certificateRequest->enrollment;
        $certificate = $enrollment?->certificate;
        $learnerName = $enrollment?->user?->name ?: trim(($enrollment?->first_name ?? '') . ' ' . ($enrollment?->last_name ?? ''));

        return [
            'id' => $certificateRequest->id,
            'status' => $certificateRequest->status,
            'status_label' => match ($certificateRequest->status) {
                'pending' => 'En attente d’approbation',
                'approved' => 'Approuvée',
                'rejected' => 'Rejetée',
                'invalidated' => 'Invalidée',
                default => 'Inconnue',
            },
            'requested_at' => $certificateRequest->requested_at?->toIso8601String(),
            'requestedDate' => $certificateRequest->requested_at?->format('d/m/Y H:i'),
            'requested_by_name' => $certificateRequest->requestedBy?->name,
            'decision_at' => $certificateRequest->decision_at?->toIso8601String(),
            'decisionDate' => $certificateRequest->decision_at?->format('d/m/Y H:i'),
            'decision_notes' => $certificateRequest->decision_notes,
            'invalidation_reason' => $certificateRequest->invalidation_reason,
            'invalidated_at' => $certificateRequest->invalidated_at?->toIso8601String(),
            'invalidatedDate' => $certificateRequest->invalidated_at?->format('d/m/Y H:i'),
            'learner_name' => $learnerName ?: 'Apprenant',
            'learner_email' => $enrollment?->user?->email ?: $enrollment?->email,
            'formation' => $enrollment?->formation?->title ?: 'Formation',
            'formation_id' => $enrollment?->formation_id,
            'session_id' => $enrollment?->session_id,
            'session_start_date' => $enrollment?->session?->start_date?->toDateString(),
            'session_end_date' => $enrollment?->session?->end_date?->toDateString(),
            'completed_at' => $enrollment?->completed_at?->toIso8601String(),
            'completedDate' => $enrollment?->completed_at?->format('d/m/Y'),
            'enrollment_id' => $enrollment?->id,
            'enrollment_status' => $enrollment?->status,
            'certificate_reference' => $certificate?->reference,
            'certificate_generated' => $certificate !== null && $certificate->revoked_at === null,
            'certificate_revoked_at' => $certificate?->revoked_at?->toIso8601String(),
            'verification_path' => $certificate?->reference
                ? $this->certificateService->buildVerificationPath($certificate->reference)
                : null,
            'can_approve' => $certificateRequest->status === 'pending' && $enrollment?->status === 'completed',
            'can_reject' => $certificateRequest->status === 'pending',
            'can_invalidate' => in_array($certificateRequest->status, ['approved'], true)
                || ($certificate && $certificate->revoked_at === null),
        ];
    }
}
