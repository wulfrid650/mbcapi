<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AccountCreated;
use App\Mail\PaymentReceipt;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\FormationEnrollment;
use App\Models\Payment;
use App\Models\PortfolioProject;
use App\Services\ActivityLogService;
use App\Services\CertificateRequestService;
use App\Services\CertificateService;
use App\Services\EnrollmentOwnershipService;
use App\Services\FormationEnrollmentWindowService;
use App\Services\ProjectPhaseWorkflowService;
use App\Services\ReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SecretaireController extends Controller
{
    public function __construct(
        private ProjectPhaseWorkflowService $projectPhaseWorkflowService,
        private FormationEnrollmentWindowService $enrollmentWindowService,
        private ReceiptService $receiptService,
        private CertificateService $certificateService,
        private CertificateRequestService $certificateRequestService,
        private EnrollmentOwnershipService $enrollmentOwnershipService
    ) {
    }

    /**
     * Dashboard de la secrétaire
     */
    /**
     * Dashboard de la secrétaire
     */
    public function dashboard(): JsonResponse
    {
        $this->enrollmentWindowService->expirePendingEnrollments();

        // 1. Statistiques générales
        $stats = [
            'totalClients' => User::whereHas('roles', fn($query) => $query->where('slug', 'client'))->count(),
            'projetsEnCours' => PortfolioProject::where('status', 'in_progress')->count(),
            'apprenants' => User::whereHas('roles', fn($query) => $query->where('slug', 'apprenant'))->count(),
            'paiementsEnAttente' => Payment::where('status', 'pending')->count(),
            'recusAujourdhui' => Payment::where('status', 'completed')
                ->whereDate('validated_at', now()->toDateString())
                ->count(),
        ];

        // 2. Paiements récents (formatés pour le frontend)
        $paymentsBaseQuery = Payment::query()->with([
            'user',
            'payable' => function ($morphTo) {
                $morphTo->morphWith([
                    FormationEnrollment::class => ['formation', 'user', 'session'],
                    User::class => [],
                ]);
            },
        ]);

        $pendingPayments = (clone $paymentsBaseQuery)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $recentPaymentsRaw = (clone $paymentsBaseQuery)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $paiementsRecents = $pendingPayments->map(function (Payment $payment) {
            $displayUser = $payment->deriveUser();
            return [
                'id' => $payment->getPublicId(),
                'nom' => $displayUser?->name ?? 'Client non identifié',
                'formation' => $payment->deriveFormationTitle() ?? 'Formation',
                'montant' => number_format((float) $payment->amount, 0, ',', ' ') . ' FCFA',
                'type' => $payment->method_label ?: ucfirst($payment->method ?? 'Mode inconnu'),
                'date' => $payment->created_at?->format('d/m/Y'),
                'status' => $payment->status,
                'payment_type' => match ($payment->purpose) {
                    'formation_payment' => 'Inscription',
                    'formation_installment' => 'Acompte',
                    default => $payment->purpose_label,
                },
            ];
        });

        // 3. Activités récentes traçables par auteur
        $finalActivities = ActivityLog::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (ActivityLog $log) {
                $actorName = $log->user?->name ?? 'Système';
                $detail = $log->description ?: $log->action;

                if (!str_contains($detail, $actorName)) {
                    $detail = "{$actorName} - {$detail}";
                }

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'detail' => $detail,
                    'time' => $log->created_at->diffForHumans(),
                    'icon' => $this->resolveActivityIcon($log->action),
                    'color' => $this->resolveActivityColor($log->action),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'paiementsRecents' => $paiementsRecents,
                'activitesRecentes' => $finalActivities,
            ],
        ]);
    }

    /**
     * Liste des apprenants
     */
    public function listApprenants(Request $request): JsonResponse
    {
        $query = User::where('role', 'apprenant');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $apprenants = $query->with('enrollments.formation')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));
        $apprenants->setCollection(
            $apprenants->getCollection()->map(fn(User $apprenant) => $apprenant->toExternalArray())
        );

        return response()->json([
            'success' => true,
            'data' => $apprenants,
        ]);
    }

    /**
     * Détail d'un apprenant
     */
    public function getApprenant(string $apprenant): JsonResponse
    {
        $apprenant = User::where('public_id', $apprenant)
            ->where('role', 'apprenant')
            ->with(['enrollments.formation', 'enrollments.session'])
            ->firstOrFail();

        $payments = Payment::where('user_id', $apprenant->id)
            ->orWhere(function ($q) use ($apprenant) {
                $q->where('payable_type', 'App\\Models\\FormationEnrollment')
                    ->whereIn('payable_id', $apprenant->enrollments->pluck('id'));
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'apprenant' => $apprenant->toExternalArray(),
                'payments' => $payments->map(fn(Payment $payment) => $payment->toExternalArray())->values(),
            ],
        ]);
    }

    /**
     * Mettre à jour un apprenant
     */
    public function updateApprenant(Request $request, string $apprenant): JsonResponse
    {
        $apprenant = User::where('public_id', $apprenant)
            ->where('role', 'apprenant')
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $apprenant->id,
            'phone' => 'sometimes|string|max:30',
            'is_active' => 'sometimes|boolean',
        ]);

        $apprenant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Apprenant mis à jour avec succès',
            'data' => $apprenant->fresh()->toExternalArray(),
        ]);
    }

    /**
     * Liste des inscriptions
     */
    public function listEnrollments(Request $request): JsonResponse
    {
        $this->enrollmentWindowService->expirePendingEnrollments();

        $query = FormationEnrollment::with(['formation', 'user', 'session']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $enrollments = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $enrollments,
        ]);
    }

    /**
     * Détail d'une inscription
     */
    public function getEnrollment(int $enrollmentId): JsonResponse
    {
        $enrollment = FormationEnrollment::with(['formation', 'user', 'session'])
            ->findOrFail($enrollmentId);

        if ($enrollment->status === 'pending_payment') {
            $this->enrollmentWindowService->expireIfNeeded($enrollment);
            $enrollment->refresh()->load(['formation', 'user', 'session']);
        }

        $payments = Payment::where('payable_type', 'App\\Models\\FormationEnrollment')
            ->where('payable_id', $enrollmentId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'enrollment' => $enrollment,
                'payments' => $payments,
            ],
        ]);
    }

    /**
     * Mettre à jour le statut d'une inscription
     */
    public function updateEnrollmentStatus(Request $request, int $enrollmentId): JsonResponse
    {
        $enrollment = FormationEnrollment::findOrFail($enrollmentId);
        $actor = Auth::user();

        if ($enrollment->status === 'pending_payment') {
            $this->enrollmentWindowService->expireIfNeeded($enrollment);
            $enrollment->refresh();
        }

        $validated = $request->validate([
            'status' => 'required|in:pending_payment,confirmed,cancelled,completed',
            'notes' => 'nullable|string|max:1000',
        ]);

        $metadata = array_merge(is_array($enrollment->metadata) ? $enrollment->metadata : [], [
            'last_status_update_by' => $actor?->id,
            'last_status_update_by_name' => $actor?->name,
            'last_status_update_at' => now()->toISOString(),
        ]);

        $updatePayload = [
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $enrollment->notes,
            'metadata' => $metadata,
        ];

        // Actions supplémentaires selon le statut
        if ($validated['status'] === 'confirmed' && !$enrollment->paid_at) {
            $updatePayload['paid_at'] = now();
        }

        if ($validated['status'] === 'completed') {
            $updatePayload['completed_at'] = $enrollment->completed_at ?? now();
        } elseif ($enrollment->completed_at) {
            $updatePayload['completed_at'] = null;
        }

        $enrollment->update($updatePayload);

        if ($validated['status'] !== 'completed') {
            $this->certificateRequestService->invalidateEnrollment(
                $enrollment->fresh(['certificate', 'certificateRequest']),
                $actor,
                "Inscription repositionnée sur le statut {$validated['status']}."
            );
        }

        ActivityLogService::log(
            'Statut inscription mis à jour',
            ($actor?->name ?? 'Un membre du staff') . " a positionné l'inscription {$enrollment->id} sur le statut {$validated['status']}.",
            $enrollment,
            $actor?->id
        );

        $freshEnrollment = $enrollment->fresh([
            'formation',
            'user',
            'session',
            'certificate',
            'certificateRequest',
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['status'] === 'completed'
                ? 'Statut de l\'inscription mis à jour. La demande de certificat peut maintenant être soumise.'
                : 'Statut de l\'inscription mis à jour',
            'data' => $freshEnrollment->toArray(),
        ]);
    }

    /**
     * Liste des paiements
     */
    public function listPayments(Request $request): JsonResponse
    {
        $this->enrollmentWindowService->expirePendingEnrollments();

        $query = Payment::query()->with([
            'user',
            'validatedByUser',
            'payable' => function ($morphTo) {
                $morphTo->morphWith([
                    FormationEnrollment::class => ['formation', 'user', 'session'],
                    User::class => [],
                ]);
            },
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('method')) {
            $query->where('method', $request->input('method'));
        }

        if ($request->filled('category')) {
            $category = (string) $request->input('category');

            if ($category === 'formation') {
                $query->where(function ($categoryQuery) {
                    $categoryQuery->where('payable_type', FormationEnrollment::class)
                        ->orWhereIn('purpose', ['formation_payment', 'formation_installment']);
                });
            } elseif ($category === 'other') {
                $query->where(function ($categoryQuery) {
                    $categoryQuery->where(function ($subQuery) {
                        $subQuery->whereNull('payable_type')
                            ->orWhere('payable_type', '!=', FormationEnrollment::class);
                    })->whereNotIn('purpose', ['formation_payment', 'formation_installment']);
                });
            }
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $payments = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        $payments->getCollection()->transform(function (Payment $payment) {
            $payable = $payment->payable;

            if ((!$payment->relationLoaded('user') || !$payment->user) && $payable) {
                if ($payable instanceof FormationEnrollment && $payable->relationLoaded('user') && $payable->user) {
                    $payment->setRelation('user', $payable->user);
                } elseif ($payable instanceof User) {
                    $payment->setRelation('user', $payable);
                }
            }

            if (!$payment->user && $payable instanceof FormationEnrollment) {
                $virtualUser = new User([
                    'name' => $payable->full_name,
                    'email' => $payable->participant_email ?: ($payable->user?->email ?? null),
                    'phone' => $payable->phone ?? $payable->user?->phone,
                ]);
                $virtualUser->exists = false;
                $payment->setRelation('user', $virtualUser);
            }

            if (!$payment->user) {
                $metadata = $payment->metadata ?? [];
                $virtualUser = new User([
                    'name' => $payment->payer_name
                        ?? ($metadata['customer_full_name'] ?? trim(($metadata['customer_first_name'] ?? '') . ' ' . ($metadata['customer_last_name'] ?? '')))
                        ?? 'Client inconnu',
                    'email' => $payment->payer_email ?? ($metadata['customer_email'] ?? null),
                    'phone' => $payment->payer_phone ?? ($metadata['customer_phone'] ?? null),
                ]);
                $virtualUser->exists = false;
                $payment->setRelation('user', $virtualUser);
            }

            if ($payable instanceof FormationEnrollment) {
                $payment->setAttribute('formation_title', $payable->formation?->title);
                $payment->setAttribute('session_name', $payable->session?->name);
            } else {
                $payment->setAttribute('formation_title', null);
                $payment->setAttribute('session_name', null);
            }

            $payment->setAttribute('purpose_label', $payment->purpose_label);
            $payment->setAttribute('method_label', $payment->method_label);
            $payment->setAttribute('actor_name', $payment->validatedByUser?->name ?? ($payment->metadata['validated_by_name'] ?? null));
            $payment->setAttribute('manual_reference', $payment->metadata['manual_payment_reference'] ?? null);
            $payment->setAttribute('payment_proof_url', isset($payment->metadata['payment_proof_path'])
                ? Storage::disk('public')->url($payment->metadata['payment_proof_path'])
                : ($payment->metadata['payment_proof_url'] ?? null));
            $payment->setAttribute('payment_url', rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference);
            $payment->setAttribute('link_audit_count', ActivityLog::query()
                ->where('subject_type', Payment::class)
                ->where('subject_id', $payment->id)
                ->count());
            $payment->setAttribute('link_access_count', (int) (($payment->metadata ?? [])['link_access_count'] ?? 0));
            $payment->setAttribute('last_link_accessed_at', ($payment->metadata ?? [])['last_link_accessed_at'] ?? null);
            $payment->setAttribute('status_label', $payment->status_label);
            $payment->setAttribute('category', $this->isFormationPayment($payment) ? 'formation' : 'other');
            $payment->setAttribute('has_receipt', $payment->status === 'completed');
            $payment->setAttribute('receipt_number', $payment->receipt_number);
            $paymentPublicId = $payment->getPublicId();
            $payment->setAttribute('receipt_preview_url', $payment->status === 'completed'
                ? rtrim((string) config('app.url'), '/') . '/api/secretaire/recus/' . $paymentPublicId . '/pdf'
                : null);
            $payment->setAttribute('receipt_download_url', $payment->status === 'completed'
                ? rtrim((string) config('app.url'), '/') . '/api/secretaire/recus/' . $paymentPublicId . '/download'
                : null);

            $payment->setAttribute('payment_type', match ($payment->purpose) {
                'formation_payment' => 'inscription',
                'formation_installment' => 'acompte',
                default => $payment->purpose,
            });

            return $payment;
        });
        $payments->setCollection(
            $payments->getCollection()->map(fn(Payment $payment) => $payment->toExternalArray())
        );

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Détail d'un paiement
     */
    public function getPayment(string $payment): JsonResponse
    {
        $payment = Payment::with([
            'user',
            'validatedByUser',
            'payable' => function ($morphTo) {
                $morphTo->morphWith([
                    FormationEnrollment::class => ['formation', 'user', 'session'],
                    User::class => [],
                ]);
            },
        ])->where('public_id', $payment)->firstOrFail();

        $payment->setAttribute('payment_url', rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference);
        $payment->setAttribute('link_audit_summary', $this->buildPaymentLinkAuditSummary($payment));
        $payment->setAttribute('link_audit', $this->buildPaymentLinkAuditTrail($payment));

        return response()->json([
            'success' => true,
            'data' => $payment->toExternalArray(),
        ]);
    }

    /**
     * Valider manuellement un paiement
     */
    public function validatePayment(Request $request, string $payment): JsonResponse
    {
        $payment = Payment::with(['payable', 'validatedByUser'])->where('public_id', $payment)->firstOrFail();
        $actor = Auth::user();

        if ($payment->status === 'completed') {
            $payment->loadMissing([
                'user',
                'validatedByUser',
                'payable' => function ($morphTo) {
                    $morphTo->morphWith([
                        FormationEnrollment::class => ['formation', 'user', 'session'],
                        User::class => [],
                    ]);
                },
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ce paiement est déjà validé',
                'data' => $payment->toExternalArray(),
            ]);
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
            'amount' => 'nullable|numeric|min:0',
            'method' => 'required|string|max:100',
            'reference' => 'required|string|max:255',
            'proof' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        $enrollment = $payment->payable instanceof FormationEnrollment ? $payment->payable : null;

        if ($enrollment && $enrollment->status === 'pending_payment') {
            $this->enrollmentWindowService->expireIfNeeded($enrollment);
            $enrollment->refresh();
        }

        if ($enrollment && $enrollment->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande a expiré et ne peut plus être validée automatiquement.',
            ], 410);
        }

        $proofPath = $request->file('proof')->store('payment-proofs/' . now()->format('Y/m'), 'public');

        $metadata = array_merge(is_array($payment->metadata) ? $payment->metadata : [], [
            'manual_validation' => true,
            'validation_mode' => 'secretariat_manual',
            'validated_by' => $actor?->id,
            'validated_by_name' => $actor?->name,
            'validation_notes' => $validated['notes'],
            'manual_payment_reference' => $validated['reference'],
            'payment_proof_path' => $proofPath,
            'payment_proof_url' => Storage::disk('public')->url($proofPath),
            'validated_at' => now()->toISOString(),
        ]);

        $updateData = [
            'status' => 'completed',
            'validated_at' => now(),
            'validated_by' => $actor?->id,
            'is_manual' => true,
            'method' => $validated['method'],
            'metadata' => $metadata,
        ];

        if (isset($validated['amount'])) {
            $updateData['amount'] = $validated['amount'];
        }

        $payment->update($updateData);

        // Mettre à jour l'inscription si c'est un paiement de formation
        if ($payment->payable_type === FormationEnrollment::class && $enrollment) {
            $linkedUserId = $enrollment->user_id ?: $payment->user_id;

            $enrollment->update([
                'user_id' => $linkedUserId,
                'status' => 'confirmed',
                'paid_at' => now(),
                'notes' => $validated['notes'],
                'metadata' => array_merge(is_array($enrollment->metadata) ? $enrollment->metadata : [], [
                    'validation_mode' => 'manual_secretary',
                    'validated_by' => $actor?->id,
                    'validated_by_name' => $actor?->name,
                    'manual_payment_reference' => $validated['reference'],
                    'validation_notes' => $validated['notes'],
                    'payment_proof_path' => $proofPath,
                    'validated_at' => now()->toISOString(),
                ]),
            ]);

            ActivityLogService::log(
                'Inscription validée',
                ($actor?->name ?? 'Un membre du staff') . " a validé l'inscription de {$enrollment->full_name}.",
                $enrollment,
                $actor?->id
            );
        }

        ActivityLogService::log(
            'Paiement validé manuellement',
            ($actor?->name ?? 'Un membre du staff') . " a validé manuellement le paiement {$payment->reference}.",
            $payment,
            $actor?->id
        );

        $payment->refresh()->load([
            'user',
            'validatedByUser',
            'payable' => function ($morphTo) {
                $morphTo->morphWith([
                    FormationEnrollment::class => ['formation', 'user', 'session'],
                    User::class => [],
                ]);
            },
        ]);

        try {
            $this->receiptService->generateReceipt($payment);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Failed to generate payment receipt: ' . $exception->getMessage());
        }

        $receiptUser = $payment->deriveUser();
        if ($receiptUser?->email) {
            $paymentId = $payment->id;
            $receiptEmail = $receiptUser->email;

            app()->terminating(function () use ($paymentId, $receiptEmail) {
                try {
                    $freshPayment = Payment::with(['user', 'payable', 'validatedByUser'])->find($paymentId);

                    if ($freshPayment && $freshPayment->status === 'completed') {
                        $this->receiptService->sendReceiptByEmail($freshPayment, $receiptEmail);
                    }
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Log::error('Failed to send payment receipt asynchronously: ' . $exception->getMessage());
                }
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Paiement validé avec succès',
            'data' => $payment->toExternalArray(),
        ]);
    }

    /**
     * Rejeter un paiement
     */
    public function rejectPayment(Request $request, string $payment): JsonResponse
    {
        $payment = Payment::where('public_id', $payment)->firstOrFail();
        $actor = Auth::user();

        if ($payment->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement est déjà validé',
            ], 400);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $payment->update([
            'status' => 'failed',
            'metadata' => array_merge($payment->metadata ?? [], [
                'rejected_by' => $actor?->id,
                'rejected_by_name' => $actor?->name,
                'rejection_reason' => $validated['reason'],
                'rejected_at' => now()->toISOString(),
            ]),
        ]);

        ActivityLogService::log(
            'Paiement rejeté',
            ($actor?->name ?? 'Un membre du staff') . " a rejeté le paiement {$payment->reference}.",
            $payment,
            $actor?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Paiement rejeté',
            'data' => $payment->fresh()->toExternalArray(),
        ]);
    }

    /**
     * Liste des reçus
     */
    public function listReceipts(Request $request): JsonResponse
    {
        $query = Payment::with(['user', 'payable.formation'])
            ->where('status', 'completed');

        if ($request->has('from_date')) {
            $query->whereDate('validated_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('validated_at', '<=', $request->to_date);
        }

        $receipts = $query->orderByDesc('validated_at')
            ->paginate($request->get('per_page', 15));

        $receipts->getCollection()->transform(function (Payment $payment) {
            return $this->mapReceiptRecord($payment);
        });

        $invalidReceipts = (clone $query)
            ->orderByDesc('validated_at')
            ->get()
            ->map(function (Payment $payment) {
                return $this->mapReceiptRecord($payment);
            })
            ->filter(fn(array $receipt) => !$receipt['is_complete'])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $receipts->items(),
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'per_page' => $receipts->perPage(),
                'total' => $receipts->total(),
                'invalid_receipts' => $invalidReceipts,
                'invalid_receipts_count' => $invalidReceipts->count(),
            ],
        ]);
    }

    /**
     * Ignorer l'alerte sur un reçu
     */
    public function ignoreReceiptWarning(string $paymentId): JsonResponse
    {
        $payment = Payment::where('public_id', $paymentId)->firstOrFail();
        
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $metadata['receipt_warning_ignored'] = true;
        
        $payment->update([
            'metadata' => $metadata
        ]);

        return response()->json([
            'success' => true,
            'message' => 'L\'alerte pour ce reçu sera désormais ignorée.',
            'data' => $this->mapReceiptRecord($payment)
        ]);
    }


    /**
     * Télécharger un reçu
     */
    public function downloadReceipt(string $payment)
    {
        $payment = Payment::with([
            'user',
            'payable' => function ($morphTo) {
                $morphTo->morphWith([
                    FormationEnrollment::class => ['user'],
                    User::class => [],
                ]);
            },
        ])->where('public_id', $payment)->firstOrFail();

        if ($payment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement n\'est pas encore validé',
            ], 400);
        }

        return $this->receiptService->downloadReceipt($payment);
    }

    /**
     * Registre des inscriptions
     */
    public function getRegistre(Request $request): JsonResponse
    {
        $query = FormationEnrollment::with(['formation', 'user', 'session'])
            ->where('status', 'confirmed');

        if ($request->has('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $registre = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $registre,
        ]);
    }

    /**
     * Exporter le registre
     */
    public function exportRegistre(Request $request): JsonResponse
    {
        $query = FormationEnrollment::with(['formation', 'user', 'session'])
            ->where('status', 'confirmed');

        if ($request->has('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $registre = $query->orderByDesc('created_at')->get();

        // Formater pour export
        $exportData = $registre->map(function ($enrollment) {
            return [
                'Date inscription' => $enrollment->created_at->format('d/m/Y'),
                'Nom' => $enrollment->full_name,
                'Email' => $enrollment->participant_email,
                'Téléphone' => $enrollment->phone ?? $enrollment->user?->phone ?? 'N/A',
                'Formation' => $enrollment->formation->title,
                'Session' => $enrollment->session?->name ?? 'N/A',
                'Statut' => $enrollment->status,
                'Montant payé' => $enrollment->amount_paid,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $exportData,
        ]);
    }

    /**
     * Liste des projets (vue limitée)
     */
    public function listProjets(Request $request): JsonResponse
    {
        $query = PortfolioProject::select([
            'id',
            'title',
            'description',
            'client',
            'client_id',
            'client_email',
            'status',
            'created_at',
            'location',
            'start_date',
            'expected_end_date',
            'completion_date',
            'budget',
            'progress',
            'category',
            'chef_chantier_id',
            'metadata',
        ])->with('clientUser:id,name,email');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $projets = $query
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        $projets->getCollection()->transform(fn(PortfolioProject $project) => $this->mapProjetSummary($project));

        return response()->json([
            'success' => true,
            'data' => $projets,
        ]);
    }

    /**
     * Détail d'un chantier pour la secrétaire/admin.
     */
    public function getProjet(int $projet): JsonResponse
    {
        $project = PortfolioProject::with([
            'clientUser:id,name,email,phone',
            'creator:id,name,email',
        ])->findOrFail($projet);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $this->mapProjetSummary($project),
                [
                    'description' => $project->description,
                    'services' => is_array($project->services) ? $project->services : [],
                    'images' => is_array($project->images) ? $project->images : [],
                    'created_by' => $project->created_by,
                    'created_by_name' => $project->creator?->name,
                    'client_phone' => $project->clientUser?->phone,
                    'created_at' => $project->created_at?->toIso8601String(),
                    'updated_at' => $project->updated_at?->toIso8601String(),
                    'phase_history' => data_get($project->metadata, 'phase_workflow.history', []),
                    'validation' => [
                        'status' => data_get($project->metadata, 'creation_validation.status', 'approved'),
                        'note' => data_get($project->metadata, 'creation_validation.note'),
                        'validated_at' => data_get($project->metadata, 'creation_validation.validated_at'),
                        'validated_by' => data_get($project->metadata, 'creation_validation.validated_by'),
                    ],
                ]
            ),
        ]);
    }

    /**
     * Lier un chantier à un client.
     * Accessible aux profils secretaire/admin.
     */
    public function assignProjetClient(Request $request, int $projet): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:users,id',
        ]);

        $project = PortfolioProject::findOrFail($projet);
        $creationStatus = data_get($project->metadata, 'creation_validation.status', 'approved');
        if ($creationStatus !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Validez d\'abord la création du chantier avant de lier un client.',
            ], 422);
        }

        $client = User::findOrFail((int) $validated['client_id']);

        if (!$this->isClientUser($client)) {
            return response()->json([
                'success' => false,
                'message' => 'L\'utilisateur sélectionné n\'a pas le rôle client.',
            ], 422);
        }

        $project->update([
            'client_id' => $client->id,
            'client_email' => $client->email,
            'client' => $client->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client lié au chantier avec succès.',
            'data' => [
                'project_id' => $project->id,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_email' => $client->email,
            ],
        ]);
    }

    /**
     * Valider ou rejeter la création d'un chantier.
     * Accessible aux profils secretaire/admin.
     */
    public function validateProjetCreation(Request $request, int $projet): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:1000',
        ]);

        $project = PortfolioProject::findOrFail($projet);
        $metadata = is_array($project->metadata) ? $project->metadata : [];
        $metadata['creation_validation'] = [
            'status' => $validated['decision'],
            'note' => $validated['note'] ?? null,
            'validated_by' => Auth::id(),
            'validated_at' => now()->toIso8601String(),
            'previous_status' => data_get($metadata, 'creation_validation.status', 'pending'),
        ];

        $project->update([
            'metadata' => $metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['decision'] === 'approved'
                ? 'Création du chantier validée.'
                : 'Création du chantier rejetée.',
            'data' => [
                'project_id' => $project->id,
                'decision' => $validated['decision'],
                'note' => $validated['note'] ?? null,
                'validated_by' => Auth::id(),
                'validated_at' => $metadata['creation_validation']['validated_at'],
            ],
        ]);
    }

    /**
     * État du workflow de phase d'un chantier.
     */
    public function getProjetPhaseState(int $projet): JsonResponse
    {
        $project = PortfolioProject::findOrFail($projet);

        return response()->json([
            'success' => true,
            'data' => $this->projectPhaseWorkflowService->getPhaseState($project),
        ]);
    }

    /**
     * Appliquer ou approuver/rejeter un changement de phase.
     */
    public function updateProjetPhase(Request $request, int $projet): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'nullable|string|in:apply,approve,reject',
            'to_phase' => 'nullable|string|in:' . implode(',', ProjectPhaseWorkflowService::phaseKeys()),
            'note' => 'nullable|string|max:1000',
        ]);

        $project = PortfolioProject::findOrFail($projet);
        $user = Auth::user();
        $action = $validated['action'] ?? 'apply';

        if ($action === 'apply') {
            if (empty($validated['to_phase'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'La phase cible est obligatoire pour appliquer un changement.',
                ], 422);
            }

            $result = $this->projectPhaseWorkflowService->requestTransition(
                $project,
                $user,
                (string) $validated['to_phase'],
                $validated['note'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Phase du chantier mise à jour.',
                'data' => $result,
            ]);
        }

        try {
            if ($action === 'approve') {
                $result = $this->projectPhaseWorkflowService->approvePending($project, $user, $validated['note'] ?? null);
                return response()->json([
                    'success' => true,
                    'message' => 'Demande de phase approuvée.',
                    'data' => $result,
                ]);
            }

            $result = $this->projectPhaseWorkflowService->rejectPending($project, $user, $validated['note'] ?? null);
            return response()->json([
                'success' => true,
                'message' => 'Demande de phase rejetée.',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Liste des clients (Entreprises et Particuliers)
     */
    public function listClients(Request $request): JsonResponse
    {
        $query = User::query()->where(function ($builder) {
            $builder->where('role', 'client')
                ->orWhereHas('roles', function ($roleQuery) {
                    $roleQuery->where('slug', 'client');
                });
        });

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by type if we add 'client_type' column later, for now we assume all are clients.
        // If 'status' is 'actif', we check is_active
        if ($request->has('status')) {
            // map 'actif'/'inactif' to boolean
            $isActive = in_array($request->status, ['actif', 'active']);
            if ($request->status !== 'tous') {
                $query->where('is_active', $isActive);
            }
        }

        $clients = $query->withCount('portfolioProjects as projets') // Assuming relation name
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Liste des codes promo
     */
    public function listPromoCodes(Request $request): JsonResponse
    {
        $query = \App\Models\PromoCode::with('creator');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $promoCodes = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $promoCodes,
        ]);
    }

    /**
     * Créer un code promo
     */
    public function createPromoCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:promo_codes,code|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'formations' => 'nullable|array',
            'formations.*' => 'exists:formations,id',
            'description' => 'nullable|string',
        ]);

        $promoCode = \App\Models\PromoCode::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Code promo créé avec succès',
            'data' => $promoCode,
        ], 201);
    }

    /**
     * Mettre à jour un code promo
     */
    public function updatePromoCode(Request $request, int $id): JsonResponse
    {
        $promoCode = \App\Models\PromoCode::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|unique:promo_codes,code,' . $id . '|max:50',
            'type' => 'sometimes|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
            'formations' => 'nullable|array',
            'formations.*' => 'exists:formations,id',
            'description' => 'nullable|string',
        ]);

        $promoCode->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Code promo mis à jour',
            'data' => $promoCode->fresh(),
        ]);
    }

    /**
     * Supprimer un code promo
     */
    public function deletePromoCode(int $id): JsonResponse
    {
        $promoCode = \App\Models\PromoCode::findOrFail($id);
        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Code promo supprimé',
        ]);
    }

    /**
     * Créer un reçu manuel (espèces, chèques, etc.)
     */
    public function createManualReceipt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:500',
            'payment_method' => 'required|in:cash,bank_transfer,check',
            'payment_date' => 'required|date',
            'reference' => 'nullable|string|unique:payments,reference',
        ]);

        // Générer référence unique si non fournie
        if (empty($validated['reference'])) {
            $year = date('Y');
            $lastReceipt = Payment::where('reference', 'like', "REC-$year%")->latest()->first();
            $nextNumber = $lastReceipt ? (int) substr($lastReceipt->reference, -4) + 1 : 1;
            $validated['reference'] = sprintf('REC-%s-%04d', $year, $nextNumber);
        }

        // Créer le paiement manuel
        $payment = Payment::create([
            'payable_type' => 'App\\Models\\User',
            'payable_id' => $validated['user_id'],
            'user_id' => $validated['user_id'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'method' => $validated['payment_method'],
            'reference' => $validated['reference'],
            'status' => 'completed',
            'is_manual' => true,
            'paid_at' => $validated['payment_date'],
            'validated_at' => now(), // Manual receipts are effectively validated immediately
            'validated_by' => Auth::id(),
        ]);

        // Send Email Receipt
        $manualReceiptUser = $payment->deriveUser();
        if ($manualReceiptUser && $manualReceiptUser->email) {
            try {
                Mail::to($manualReceiptUser->email)->send(new PaymentReceipt($payment));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send manual receipt: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Reçu manuel créé avec succès',
            'data' => $payment->toExternalArray(),
        ], 201);
    }

    /**
     * Générer le PDF d'un reçu
     */
    public function generateReceiptPDF(Request $request, string $payment)
    {
        $payment = Payment::with([
            'user',
            'payable' => function ($morphTo) {
                $morphTo->morphWith([
                    FormationEnrollment::class => ['formation', 'user', 'session'],
                    User::class => [],
                ]);
            },
            'validatedByUser',
        ])->where('public_id', $payment)->firstOrFail();

        if ($payment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement n\'est pas encore validé',
            ], 400);
        }

        $result = $this->receiptService->generateReceipt($payment);
        $path = Storage::disk('public')->path($result['path']);
        $filename = 'Recu-' . ($result['receipt_number'] ?? $payment->reference) . '.pdf';

        if ($request->boolean('download')) {
            return response()->download($path, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Liste des rapports financiers
     */
    public function listFinancialReports(Request $request): JsonResponse
    {
        $query = \App\Models\FinancialReport::with('creator');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('period_start')) {
            $query->whereDate('period_start', '>=', $request->period_start);
        }

        $reports = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Importer un rapport financier manuellement
     */
    public function storeFinancialReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,xlsx,xls,csv|max:10240', // 10MB max
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'type' => 'nullable|string',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = 'manual_report_' . time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('financial-reports', $filename, 'public');

            $report = \App\Models\FinancialReport::create([
                'title' => $validated['title'],
                'period_start' => $validated['period_start'] ?? now()->startOfMonth(),
                'period_end' => $validated['period_end'] ?? now()->endOfMonth(),
                'type' => $validated['type'] ?? 'manual',
                'file_path' => $path,
                'generated_by' => Auth::id(),
                'is_auto_generated' => false,
                'status' => 'published',
                'metadata' => [
                    'original_filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rapport importé avec succès',
                'data' => $report
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'Aucun fichier fourni'
        ], 400);
    }

    /**
     * Générer un rapport financier mensuel ou annuel.
     */
    public function generateFinancialReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_type' => 'nullable|string|in:monthly,current_year,previous_year',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2035',
        ]);

        $period = $this->resolveFinancialReportPeriod($validated);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];

        $payments = \App\Models\Payment::with(['user', 'payable'])
            ->where('status', 'completed')
            ->whereBetween($this->financialReportDateExpression(), [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->get();

        $totalRevenue = $payments->sum('amount');
        $count = $payments->count();

        // Group by method
        $byMethod = $payments->groupBy('method')->map->sum('amount');

        // 2. Generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.monthly_financial', [
            'payments' => $payments,
            'totalRevenue' => $totalRevenue,
            'count' => $count,
            'byMethod' => $byMethod,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reportTitle' => $period['title'],
            'periodLabel' => $period['period_label'],
        ]);

        // 3. Save to Storage
        $filename = 'rapport_financier_' . $period['filename_suffix'] . '_' . time() . '.pdf';
        $path = "financial-reports/$filename";
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());

        // 4. Create Record
        $report = \App\Models\FinancialReport::create([
            'title' => $period['title'],
            'period_start' => $startDate,
            'period_end' => $endDate,
            'type' => $period['report_type'],
            'file_path' => $path,
            'generated_by' => Auth::id(),
            'is_auto_generated' => true,
            'status' => 'published',
            'metadata' => [
                'total_revenue' => $totalRevenue,
                'transaction_count' => $count,
                'period_type' => $period['period_type'],
                'report_year' => $period['report_year'],
                'report_month' => $period['report_month'],
                'period_label' => $period['period_label'],
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rapport généré avec succès',
            'data' => $report
        ]);
    }

    private function financialReportDateExpression()
    {
        return \Illuminate\Support\Facades\DB::raw('COALESCE(validated_at, paid_at, created_at)');
    }

    private function resolveFinancialReportPeriod(array $validated): array
    {
        $periodType = $validated['period_type'] ?? 'monthly';
        $currentYear = now()->year;

        if ($periodType === 'current_year') {
            $startDate = \Carbon\Carbon::createFromDate($currentYear, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();

            return [
                'period_type' => $periodType,
                'report_type' => 'yearly_revenue',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'title' => "Rapport Financier - Année {$currentYear}",
                'filename_suffix' => (string) $currentYear,
                'period_label' => "Année {$currentYear}",
                'report_year' => $currentYear,
                'report_month' => null,
            ];
        }

        if ($periodType === 'previous_year') {
            $previousYear = $currentYear - 1;
            $startDate = \Carbon\Carbon::createFromDate($previousYear, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();

            return [
                'period_type' => $periodType,
                'report_type' => 'yearly_revenue',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'title' => "Rapport Financier - Année {$previousYear}",
                'filename_suffix' => (string) $previousYear,
                'period_label' => "Année {$previousYear}",
                'report_year' => $previousYear,
                'report_month' => null,
            ];
        }

        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? $currentYear);
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return [
            'period_type' => 'monthly',
            'report_type' => 'monthly_revenue',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'title' => 'Rapport Financier - ' . $startDate->translatedFormat('F Y'),
            'filename_suffix' => sprintf('%04d_%02d', $year, $month),
            'period_label' => $startDate->translatedFormat('F Y'),
            'report_year' => $year,
            'report_month' => $month,
        ];
    }

    public function generatePaymentLink(Request $request): JsonResponse
    {
        $actor = Auth::user();
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:100',
            'motif' => 'required|string|max:255', // "Motif qui renseigne la nature"
            'payable_type' => 'nullable|string|in:formation_enrollment,service_request',
            'payable_id' => 'nullable|integer',
        ]);

        $client = User::findOrFail($validated['client_id']);

        // Vérification des 3 tranches si lié à une inscription
        if (isset($validated['payable_type']) && $validated['payable_type'] === 'formation_enrollment') {
            $enrollmentId = $validated['payable_id'];
            $existingPaymentsCount = Payment::where('payable_type', 'App\\Models\\FormationEnrollment')
                ->where('payable_id', $enrollmentId)
                ->whereIn('status', ['completed', 'pending']) // Include pending to avoid spamming links
                ->count();

            if ($existingPaymentsCount >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nombre maximum de tranches (3) pour cette inscription a été atteint.',
                ], 422);
            }
        }

        $reference = Payment::generateReference();
        $frontendUrl = rtrim((string) config('app.frontend_url', $request->header('Origin') ?? 'http://localhost:3000'), '/');
        $paymentLink = "{$frontendUrl}/paiement/link/{$reference}";

        // Créer le paiement en attente
        $payment = Payment::create([
            'reference' => $reference,
            'user_id' => $client->id,
            'amount' => $validated['amount'],
            'currency' => 'XAF',
            'description' => $validated['motif'],
            'status' => 'pending',
            'method' => 'link', // Indicates generated link
            'is_manual' => false,
            'payable_type' => isset($validated['payable_type']) ? $this->resolvePayableModel($validated['payable_type']) : 'App\\Models\\User',
            'payable_id' => $validated['payable_id'] ?? $client->id,
            'metadata' => [
                'customer_first_name' => $client->name, // Using name as first name for now or split if needed
                'customer_email' => $client->email,
                'customer_phone' => $client->phone,
                'generated_by' => $actor?->id,
                'generated_by_name' => $actor?->name,
                'generated_at' => now()->toIsoString(),
                'payment_link_url' => $paymentLink,
                'link_access_count' => 0,
                'link_retry_count' => 0,
                'return_url' => $request->header('Origin') . '/paiement/callback', // Default callback
            ],
        ]);

        ActivityLogService::log(
            'Lien de paiement généré',
            ($actor?->name ?? 'Un membre du staff') . " a généré le lien {$reference} pour {$client->name}.",
            $payment,
            $actor?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Lien de paiement généré avec succès',
            'data' => [
                'link' => $paymentLink,
                'payment_url' => $paymentLink,
                'reference' => $reference,
                'payment_id' => $payment->getPublicId(),
                'payment' => $payment->toExternalArray(),
            ],
        ]);
    }

    private function resolvePayableModel(string $type): string
    {
        return match ($type) {
            'formation_enrollment' => 'App\\Models\\FormationEnrollment',
            'service_request' => 'App\\Models\\ServiceRequest',
            default => 'App\\Models\\User',
        };
    }

    private function isClientUser(User $user): bool
    {
        if ($user->role === 'client') {
            return true;
        }

        if (method_exists($user, 'getRoleSlugs')) {
            return in_array('client', $user->getRoleSlugs(), true);
        }

        return false;
    }

    private function buildPaymentLinkAuditSummary(Payment $payment): array
    {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $latestAudit = ActivityLog::query()
            ->where('subject_type', Payment::class)
            ->where('subject_id', $payment->id)
            ->latest()
            ->first();

        return [
            'payment_url' => $metadata['payment_link_url'] ?? rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference,
            'generated_by' => $metadata['generated_by_name'] ?? $payment->user?->name,
            'generated_at' => $metadata['generated_at'] ?? $payment->created_at?->toISOString(),
            'access_count' => (int) ($metadata['link_access_count'] ?? 0),
            'retry_count' => (int) ($metadata['link_retry_count'] ?? 0),
            'last_accessed_at' => $metadata['last_link_accessed_at'] ?? null,
            'last_access_ip' => $metadata['last_link_access_ip'] ?? null,
            'last_retry_at' => $metadata['last_link_retry_at'] ?? null,
            'last_retry_by' => $metadata['last_link_retry_by_name'] ?? null,
            'last_event' => $latestAudit ? [
                'action' => $latestAudit->action,
                'description' => $latestAudit->description,
                'created_at' => $latestAudit->created_at?->toISOString(),
            ] : null,
        ];
    }

    private function buildPaymentLinkAuditTrail(Payment $payment): array
    {
        return ActivityLog::with('user')
            ->where('subject_type', Payment::class)
            ->where('subject_id', $payment->id)
            ->latest()
            ->take(50)
            ->get()
            ->map(function (ActivityLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'actor_name' => $log->user?->name ?? 'Système',
                    'created_at' => $log->created_at?->toISOString(),
                    'time' => $log->created_at?->diffForHumans(),
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Créer un nouveau client
     */
    public function createClient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_type' => 'required|in:particulier,entreprise',
            'name' => 'required|string|max:255', // Contact Person Name or Company Name depending on logic, keeping as Name for User model
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'company_name' => 'nullable|required_if:client_type,entreprise|string|max:255',
            'company_address' => 'nullable|string|max:255',
        ]);

        $password = \Illuminate\Support\Str::random(10); // Generate random password

        $creationData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'client',
            'is_active' => true,
        ];

        if ($validated['client_type'] === 'entreprise') {
            $creationData['company_name'] = $validated['company_name'];
            $creationData['company_address'] = $validated['company_address'] ?? $validated['address'];
        }

        $user = User::create($creationData);

        // Send Welcome Email
        try {
            Mail::to($user->email)->send(new AccountCreated($user, $password));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send welcome email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Client créé avec succès. Mot de passe temporaire: ' . $password,
            'data' => $user->toExternalArray(),
        ], 201);
    }

    /**
     * Créer un nouvel apprenant
     */
    public function createApprenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'formation_id' => 'nullable|exists:formations,id',
        ]);

        $password = \Illuminate\Support\Str::random(10);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'apprenant',
            'is_active' => true,
        ]);

        $this->enrollmentOwnershipService->attachByEmail($user);

        // Create enrollment if formation_id provided
        if (!empty($validated['formation_id'])) {
            $existingEnrollment = FormationEnrollment::query()
                ->where('user_id', $user->id)
                ->where('formation_id', $validated['formation_id'])
                ->whereIn('status', ['pending_payment', 'confirmed', 'completed'])
                ->latest('id')
                ->first();

            if (!$existingEnrollment) {
                FormationEnrollment::create([
                    'formation_id' => $validated['formation_id'],
                    'user_id' => $user->id,
                    'status' => 'pending_payment',
                    'first_name' => $user->name,
                    'last_name' => '',
                    'email' => $user->email,
                    'phone' => $user->phone,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Apprenant créé avec succès. Mot de passe temporaire: ' . $password,
            'data' => $user->toExternalArray(),
        ], 201);
    }

    private function mapProjetSummary(PortfolioProject $project): array
    {
        $phaseWorkflow = is_array(data_get($project->metadata, 'phase_workflow'))
            ? data_get($project->metadata, 'phase_workflow')
            : [];

        return [
            'id' => $project->id,
            'title' => $project->title,
            'description' => $project->description,
            'client' => $project->client,
            'client_id' => $project->client_id,
            'client_email' => $project->client_email,
            'client_name' => $project->clientUser?->name ?? $project->client,
            'status' => $project->status,
            'created_at' => $project->created_at?->toIso8601String(),
            'location' => $project->location,
            'start_date' => $project->start_date,
            'expected_end_date' => $project->expected_end_date,
            'completion_date' => $project->completion_date,
            'budget' => $project->budget,
            'progress' => $project->progress,
            'category' => $project->category,
            'chef_chantier_id' => $project->chef_chantier_id,
            'creation_validation_status' => data_get($project->metadata, 'creation_validation.status', 'approved'),
            'creation_validation_note' => data_get($project->metadata, 'creation_validation.note'),
            'phase_current' => $phaseWorkflow['current_phase'] ?? null,
            'phase_pending' => $phaseWorkflow['pending_request'] ?? null,
        ];
    }

    private function isFormationPayment(Payment $payment): bool
    {
        return $payment->payable_type === FormationEnrollment::class
            || in_array($payment->purpose, ['formation_payment', 'formation_installment'], true);
    }

    private function mapReceiptRecord(Payment $payment): array
    {
        $payment->loadMissing([
            'user',
            'validatedByUser',
            'payable' => function ($morphTo) {
                $morphTo->morphWith([
                    FormationEnrollment::class => ['formation', 'user', 'session'],
                    User::class => [],
                ]);
            },
        ]);

        $beneficiary = $payment->deriveUser();
        $issues = $this->resolveReceiptIssues($payment, $beneficiary);
        $beneficiaryType = $this->resolveReceiptBeneficiaryType($payment, $beneficiary);
        $paymentPublicId = $payment->getPublicId();
        
        $isIgnored = (bool) (($payment->metadata ?? [])['receipt_warning_ignored'] ?? false);

        return [
            'id' => $paymentPublicId,
            'payment_id' => $paymentPublicId,
            'number' => $payment->receipt_number ?? $payment->reference,
            'reference' => $payment->reference,
            'date' => ($payment->validated_at ?? $payment->created_at)?->toISOString(),
            'beneficiary' => [
                'name' => $beneficiary?->name ?? 'Non renseigne',
                'email' => $beneficiary?->email ?? 'Non renseigne',
                'type' => $beneficiaryType,
            ],
            'object' => $payment->description ?: $payment->purpose_label,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->method_label,
            'issuer' => $payment->validatedByUser?->name
                ?? ($payment->metadata['validated_by_name'] ?? 'Systeme'),
            'status' => $payment->status,
            'is_complete' => empty($issues) || $isIgnored,
            'is_ignored' => $isIgnored,
            'issues' => $issues,
            'preview_url' => rtrim((string) config('app.url'), '/') . '/api/secretaire/recus/' . $paymentPublicId . '/pdf',
            'download_url' => rtrim((string) config('app.url'), '/') . '/api/secretaire/recus/' . $paymentPublicId . '/download',
        ];
    }

    private function resolveReceiptIssues(Payment $payment, ?User $beneficiary): array
    {
        $issues = [];

        if (!$payment->receipt_number) {
            $issues[] = 'Numero de recu non genere';
        }

        if (!$beneficiary?->name) {
            $issues[] = 'Beneficiaire manquant';
        }

        if (!$payment->description && !$payment->purpose) {
            $issues[] = 'Objet de paiement manquant';
        }

        if (!$payment->validated_at) {
            $issues[] = 'Date de validation manquante';
        }

        return $issues;
    }

    private function resolveReceiptBeneficiaryType(Payment $payment, ?User $beneficiary): string
    {
        if ($this->isFormationPayment($payment)) {
            return 'apprenant';
        }

        if ($beneficiary?->role === 'client' || $beneficiary?->roles?->contains('slug', 'client')) {
            return 'client';
        }

        return 'autre';
    }

    /**
     * Convertir un nombre en lettres (simplifié)
     */
    private function resolveActivityIcon(string $action): string
    {
        $normalized = mb_strtolower($action);

        return match (true) {
            str_contains($normalized, 'paiement') => 'receipt',
            str_contains($normalized, 'inscription') || str_contains($normalized, 'utilisateur') => 'user-plus',
            str_contains($normalized, 'projet') || str_contains($normalized, 'chantier') => 'building',
            default => 'check',
        };
    }

    private function resolveActivityColor(string $action): string
    {
        $normalized = mb_strtolower($action);

        return match (true) {
            str_contains($normalized, 'rejet') || str_contains($normalized, 'annul') || str_contains($normalized, 'échou') || str_contains($normalized, 'echou') => 'amber',
            str_contains($normalized, 'paiement') => 'green',
            str_contains($normalized, 'inscription') || str_contains($normalized, 'utilisateur') => 'blue',
            default => 'purple',
        };
    }

    private function numberToWords(float $number): string
    {
        $number = (int) $number;
        return number_format($number, 0, ',', ' ') . ' francs CFA';
    }
}
