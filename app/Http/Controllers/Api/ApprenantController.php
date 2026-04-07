<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\FormationEnrollment;
use App\Models\Payment;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\EnrollmentOwnershipService;
use App\Services\FormationEnrollmentWindowService;
use App\Services\MonerooService;
use App\Services\ReceiptService;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApprenantController extends Controller
{
    public function __construct(
        private FormationEnrollmentWindowService $enrollmentWindowService,
        private ReceiptService $receiptService,
        private MonerooService $monerooService,
        private CertificateService $certificateService,
        private EnrollmentOwnershipService $enrollmentOwnershipService
    ) {
    }

    /**
     * Dashboard de l'apprenant
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        $this->expireLearnerPendingEnrollments($user);

        $enrollments = $this->learnerEnrollmentsQuery($user)
            ->with([
                'formation.formateur:id,name,email,phone,speciality,bio',
                'session.formateur:id,name,email,phone,speciality,bio',
                'certificate',
                'certificateRequest.requestedBy',
            ])
            ->orderByDesc('created_at')
            ->get();

        $enrollments = $this->reconcileEnrollmentsWithCompletedPayments($enrollments);

        $paymentsQuery = $this->learnerPaymentsQuery($user, $enrollments->pluck('id')->all());
        $completedPayments = (clone $paymentsQuery)
            ->where('status', 'completed')
            ->get();
        $recentPayments = (clone $paymentsQuery)
            ->with('payable')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $currentEnrollment = $enrollments->firstWhere('status', 'confirmed')
            ?? $enrollments->firstWhere('status', 'completed')
            ?? $enrollments->first(function (FormationEnrollment $enrollment) {
                return $this->hasCompletedEnrollmentPayment($enrollment);
            });

        $pendingRequest = $enrollments->first(function (FormationEnrollment $enrollment) {
            return in_array($enrollment->status, ['pending_payment', 'cancelled'], true);
        });

        $statsTargetEnrollment = $currentEnrollment ?? $pendingRequest;
        $paymentHistory = $currentEnrollment
            ? $this->buildPaymentHistory($currentEnrollment)
            : [];

        $activities = collect();

        foreach ($enrollments->take(4) as $enrollment) {
            $activities->push([
                'id' => (int) ('1' . $enrollment->id),
                'type' => 'enrollment',
                'title' => match ($enrollment->status) {
                    'pending_payment' => 'Demande en attente de paiement',
                    'cancelled' => 'Demande expirée',
                    'confirmed' => 'Inscription validée',
                    'completed' => 'Formation terminée',
                    default => 'Inscription mise à jour',
                },
                'date' => $enrollment->created_at?->format('d/m/Y H:i'),
                'status' => match ($enrollment->status) {
                    'confirmed', 'completed' => 'completed',
                    'pending_payment' => 'pending',
                    default => 'new',
                },
                'sort_date' => $enrollment->created_at,
            ]);
        }

        foreach ($recentPayments->take(4) as $payment) {
            $activities->push([
                'id' => (int) ('2' . $payment->id),
                'type' => 'payment',
                'title' => match ($payment->status) {
                    'completed' => 'Paiement validé',
                    'pending' => 'Paiement en attente',
                    'failed' => 'Paiement échoué',
                    default => 'Paiement mis à jour',
                },
                'date' => $payment->created_at?->format('d/m/Y H:i'),
                'status' => match ($payment->status) {
                    'completed' => 'completed',
                    'pending' => 'pending',
                    default => 'new',
                },
                'sort_date' => $payment->created_at,
            ]);
        }

        $recentActivities = $activities
            ->sortByDesc('sort_date')
            ->take(6)
            ->values()
            ->map(function (array $activity) {
                unset($activity['sort_date']);
                return $activity;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'stats' => [
                    'formations_actives' => $enrollments->where('status', 'confirmed')->count(),
                    'formations_completed' => $enrollments->where('status', 'completed')->count(),
                    'total_paid' => (float) $completedPayments->sum('amount'),
                    'remaining_to_pay' => $this->calculateRemainingAmount($statsTargetEnrollment),
                    'certificates_available' => $enrollments
                        ->filter(fn (FormationEnrollment $enrollment) => $this->hasActiveCertificate($enrollment))
                        ->count(),
                ],
                'current_formation' => $currentEnrollment ? $this->mapCurrentFormation($currentEnrollment) : null,
                'pending_request' => $pendingRequest ? $this->mapPendingRequest($pendingRequest) : null,
                'payment_history' => $paymentHistory,
                'recent_activities' => $recentActivities,
            ],
        ]);
    }

    /**
     * Mes formations
     */
    public function myFormations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->expireLearnerPendingEnrollments($user);

        $query = $this->learnerEnrollmentsQuery($user)
            ->with([
                'formation.formateur:id,name,email,phone,speciality,bio',
                'session.formateur:id,name,email,phone,speciality,bio',
            ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->orderByDesc('created_at')->paginate(10);
        $enrollments->setCollection(
            $this->reconcileEnrollmentsWithCompletedPayments($enrollments->getCollection())
        );

        return response()->json([
            'success' => true,
            'data' => $enrollments,
        ]);
    }

    /**
     * Détail d'une formation
     */
    public function getFormation(int $enrollmentId): JsonResponse
    {
        $user = Auth::user();

        $enrollment = $this->learnerEnrollmentsQuery($user)
            ->where('id', $enrollmentId)
            ->with([
                'formation.formateur:id,name,email,phone,speciality,bio',
                'session.formateur:id,name,email,phone,speciality,bio',
            ])
            ->firstOrFail();

        if ($enrollment->status === 'pending_payment') {
            $this->enrollmentWindowService->expireIfNeeded($enrollment);
            $enrollment->refresh()->load([
                'formation.formateur:id,name,email,phone,speciality,bio',
                'session.formateur:id,name,email,phone,speciality,bio',
            ]);
        }

        $payments = Payment::where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
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
     * Mes certificats
     */
    public function myCertificats(): JsonResponse
    {
        $user = Auth::user();

        $enrollments = $this->learnerEnrollmentsQuery($user)
            ->with([
                'formation.formateur:id,name,email,phone,speciality,bio',
                'session.formateur:id,name,email,phone,speciality,bio',
                'certificate',
                'certificateRequest.requestedBy',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->get();

        $enrollments = $this->reconcileEnrollmentsWithCompletedPayments($enrollments);

        $issued = $enrollments
            ->where('status', 'completed')
            ->map(fn (FormationEnrollment $enrollment) => $this->mapIssuedCertificate($enrollment))
            ->filter()
            ->values();

        $pending = $enrollments
            ->where('status', 'confirmed')
            ->map(fn (FormationEnrollment $enrollment) => $this->mapPendingCertificate($enrollment))
            ->values();

        $requests = $enrollments
            ->where('status', 'completed')
            ->map(fn (FormationEnrollment $enrollment) => $this->mapCertificateRequest($enrollment))
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'issued' => $issued,
                'pending' => $pending,
                'requests' => $requests,
            ],
        ]);
    }

    /**
     * Télécharger un certificat
     */
    public function downloadCertificat(string $certificat)
    {
        $user = Auth::user();
        $this->syncLearnerOwnership($user);

        $reference = Str::upper(trim($certificat));

        $certificate = Certificate::query()
            ->with([
                'enrollment.user',
                'enrollment.formation.formateur',
                'enrollment.session.formateur',
                'enrollment.certificateRequest.requestedBy',
            ])
            ->where('reference', $reference)
            ->whereHas('enrollment', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->where('status', 'completed');
            })
            ->firstOrFail();

        $enrollment = $certificate->enrollment;
        if ($certificate->revoked_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ce certificat a été révoqué et ne peut plus être téléchargé.',
            ], 410);
        }

        if ($enrollment?->certificateRequest && !$enrollment->certificateRequest->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce certificat n’est plus autorisé au téléchargement.',
            ], 403);
        }

        if (
            !$certificate->pdf_path
            || !Storage::disk('public')->exists($certificate->pdf_path)
        ) {
            $certificate = $this->certificateService->issueForEnrollment(
                $enrollment,
                forceRegenerate: true
            );
        }

        return Storage::disk('public')->download(
            $certificate->pdf_path,
            'Certificat-' . $certificate->reference . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Mes paiements
     */
    public function myPaiements(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->expireLearnerPendingEnrollments($user);

        $enrollment = $this->resolvePrimaryLearnerEnrollment($user);

        if (!$enrollment) {
            return response()->json([
                'success' => true,
                'data' => [
                    'enrollment_id' => null,
                    'total_amount' => 0,
                    'training_amount' => 0,
                    'paid_training_amount' => 0,
                    'remaining_training_amount' => 0,
                    'registration_fee' => ['amount' => 0, 'status' => 'pending'],
                    'installments' => [],
                    'formation_name' => 'Aucune formation',
                    'payment_actions' => [
                        'can_pay_registration' => false,
                        'can_pay_installment' => false,
                        'can_pay_full' => false,
                        'suggested_installment_amount' => 0,
                        'remaining_training_amount' => 0,
                        'pending_training_payment' => null,
                    ],
                ],
            ]);
        }

        $formation = $enrollment->formation;
        $payments = Payment::where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $metadata = is_array($enrollment->metadata) ? $enrollment->metadata : [];
        $regFeeAmount = (float) ($formation?->inscription_fee ?? $metadata['inscription_fee'] ?? 0);
        $trainingAmount = (float) ($formation?->price ?? 0);

        $registrationPayments = $payments->filter(function (Payment $payment) use ($regFeeAmount) {
            return $this->isRegistrationFeePayment($payment, $regFeeAmount);
        })->values();

        $trainingPayments = $payments->reject(function (Payment $payment) use ($regFeeAmount) {
            return $this->isRegistrationFeePayment($payment, $regFeeAmount);
        })->values();

        $regFeePayment = $registrationPayments
            ->firstWhere('status', 'completed');
        $regFeePendingPayment = $registrationPayments
            ->where('status', 'pending')
            ->sortByDesc('id')
            ->first();

        $registrationFee = [
            'amount' => $regFeeAmount,
            'status' => $regFeePayment || $enrollment->paid_at ? 'paid' : 'pending',
            'paidDate' => $regFeePayment?->validated_at?->format('d/m/Y') ?? $enrollment->paid_at?->format('d/m/Y'),
            'payment_url' => $regFeePendingPayment?->reference
                ? rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $regFeePendingPayment->reference
                : null,
        ];

        $paidTrainingAmount = (float) $trainingPayments
            ->where('status', 'completed')
            ->sum('amount');
        $remainingTrainingAmount = max(0, $trainingAmount - $paidTrainingAmount);
        $pendingTrainingPayment = $trainingPayments
            ->where('status', 'pending')
            ->sortByDesc('id')
            ->first();
        $completedTrainingPaymentsCount = $trainingPayments
            ->where('status', 'completed')
            ->count();

        $installments = $trainingPayments->map(function (Payment $payment) {
            return [
                'id' => $payment->getPublicId(),
                'label' => $payment->description ?: 'Paiement formation',
                'amount' => (float) $payment->amount,
                'status' => $payment->status === 'completed'
                    ? 'paid'
                    : ($payment->status === 'pending' ? 'pending' : 'failed'),
                'due_date' => $payment->created_at?->format('Y-m-d'),
                'paid_date' => $payment->validated_at?->format('Y-m-d'),
                'reference' => $payment->reference,
                'purpose_detail' => $payment->purpose_detail,
                'payment_url' => $payment->reference
                    ? rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'enrollment_id' => $enrollment->id,
                'total_amount' => $trainingAmount + $regFeeAmount,
                'training_amount' => $trainingAmount,
                'paid_training_amount' => $paidTrainingAmount,
                'remaining_training_amount' => $remainingTrainingAmount,
                'registration_fee' => $registrationFee,
                'installments' => $installments,
                'formation_name' => $formation?->title ?? 'Formation',
                'payment_actions' => [
                    'can_pay_registration' => $registrationFee['status'] !== 'paid',
                    'can_pay_installment' => $registrationFee['status'] === 'paid' && $remainingTrainingAmount > 0,
                    'can_pay_full' => $registrationFee['status'] === 'paid' && $remainingTrainingAmount > 0,
                    'suggested_installment_amount' => $registrationFee['status'] === 'paid'
                        ? $this->calculateSuggestedInstallmentAmount($remainingTrainingAmount, $completedTrainingPaymentsCount)
                        : 0,
                    'remaining_training_amount' => $remainingTrainingAmount,
                    'pending_training_payment' => $pendingTrainingPayment ? [
                        'id' => $pendingTrainingPayment->getPublicId(),
                        'reference' => $pendingTrainingPayment->reference,
                        'amount' => (float) $pendingTrainingPayment->amount,
                        'label' => $pendingTrainingPayment->description ?: 'Paiement formation en attente',
                        'payment_url' => rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $pendingTrainingPayment->reference,
                    ] : null,
                ],
            ],
        ]);
    }

    public function initiateFormationPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'required|string|in:installment,full',
            'return_url' => 'nullable|url',
        ]);

        $user = Auth::user();
        $this->expireLearnerPendingEnrollments($user);

        $enrollment = $this->resolvePrimaryLearnerEnrollment($user);

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune formation active n’a été trouvée pour ce compte.',
            ], 404);
        }

        $formation = $enrollment->formation;
        $metadata = is_array($enrollment->metadata) ? $enrollment->metadata : [];
        $registrationFeeAmount = (float) ($formation?->inscription_fee ?? $metadata['inscription_fee'] ?? 0);

        $payments = Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $registrationPaid = $payments->contains(function (Payment $payment) use ($registrationFeeAmount) {
            return $payment->status === 'completed' && $this->isRegistrationFeePayment($payment, $registrationFeeAmount);
        }) || (bool) $enrollment->paid_at;

        if (!$registrationPaid) {
            return response()->json([
                'success' => false,
                'message' => 'Les frais d’inscription doivent être validés avant le paiement de la formation.',
            ], 422);
        }

        $trainingPayments = $payments->reject(function (Payment $payment) use ($registrationFeeAmount) {
            return $this->isRegistrationFeePayment($payment, $registrationFeeAmount);
        })->values();

        $paidTrainingAmount = (float) $trainingPayments
            ->where('status', 'completed')
            ->sum('amount');
        $remainingTrainingAmount = max(0, (float) ($formation?->price ?? 0) - $paidTrainingAmount);

        if ($remainingTrainingAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'La formation est déjà entièrement réglée.',
            ], 422);
        }

        $pendingTrainingPayment = $trainingPayments
            ->where('status', 'pending')
            ->sortByDesc('id')
            ->first();
        $completedTrainingPaymentsCount = $trainingPayments
            ->where('status', 'completed')
            ->count();

        $amount = $validated['mode'] === 'full'
            ? $remainingTrainingAmount
            : $this->calculateSuggestedInstallmentAmount($remainingTrainingAmount, $completedTrainingPaymentsCount);

        $result = $this->monerooService->initiatePayment([
            'amount' => $amount,
            'currency' => 'XOF',
            'description' => $validated['mode'] === 'full'
                ? "Paiement complet formation - {$formation?->title}"
                : "Tranche formation - {$formation?->title}",
            'user_id' => $user->id,
            'customer_email' => $user->email,
            'customer_first_name' => $user->name,
            'customer_last_name' => '',
            'customer_phone' => $user->phone ?? '',
            'payable_type' => FormationEnrollment::class,
            'payable_id' => $enrollment->id,
            'payment_id' => $pendingTrainingPayment?->id,
            'reference' => $pendingTrainingPayment?->reference,
            'return_url' => $validated['return_url'] ?? null,
            'purpose' => 'formation_installment',
            'purpose_detail' => $validated['mode'] === 'full'
                ? 'full_balance'
                : 'suggested_installment',
            'metadata' => [
                'formation_id' => $enrollment->formation_id,
                'formation_title' => $formation?->title,
                'session_id' => $enrollment->session_id,
                'payment_kind' => 'training_balance',
                'requested_mode' => $validated['mode'],
                'remaining_training_before_payment' => $remainingTrainingAmount,
            ],
        ]);

        $paymentReference = $result['reference'] ?? $pendingTrainingPayment?->reference;
        $paymentUrl = $paymentReference
            ? rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $paymentReference
            : null;
        $publicPaymentId = null;
        if (!empty($result['payment_id'])) {
            $publicPaymentId = Payment::find($result['payment_id'])?->getPublicId();
        }
        $publicPaymentId ??= $pendingTrainingPayment?->getPublicId();

        $statusCode = $result['success'] ? 200 : 202;

        return response()->json([
            'success' => true,
            'message' => $result['success']
                ? 'Paiement initialisé'
                : 'Le paiement a été préparé. Vous pouvez reprendre le paiement via le lien.',
            'data' => [
                'payment_id' => $publicPaymentId,
                'reference' => $paymentReference,
                'checkout_url' => $result['checkout_url'] ?? null,
                'payment_url' => $paymentUrl,
                'enrollment_id' => $enrollment->id,
                'formation' => $formation?->title,
                'amount' => $amount,
                'status' => $result['success'] ? 'pending' : 'retry_required',
                'payment_retry_required' => !$result['success'],
            ],
            'error' => $result['error'] ?? null,
        ], $statusCode);
    }

    /**
     * Détail d'un paiement
     */
    public function getPaiement(string $reference): JsonResponse
    {
        $user = Auth::user();

        $payment = Payment::where('reference', $reference)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('payer_email', $user->email);
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $payment->toExternalArray(),
        ]);
    }

    /**
     * Mes reçus
     */
    public function myRecus(): JsonResponse
    {
        $user = Auth::user();

        $receipts = Payment::where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('payer_email', $user->email);
        })
            ->where('status', 'completed')
            ->orderByDesc('validated_at')
            ->get()
            ->map(function (Payment $payment) {
                $paymentPublicId = $payment->getPublicId();
                return [
                    'id' => $paymentPublicId,
                    'reference' => $payment->reference,
                    'receipt_number' => $payment->receipt_number ?? $payment->reference,
                    'label' => 'Reçu de paiement',
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'description' => $payment->description,
                    'date' => $payment->validated_at?->format('d/m/Y') ?? $payment->created_at?->format('d/m/Y'),
                    'paymentMethod' => $payment->method_label ?? $payment->method,
                    'preview_url' => rtrim((string) config('app.url'), '/') . '/api/apprenant/recus/' . $paymentPublicId . '/pdf',
                    'download_url' => rtrim((string) config('app.url'), '/') . '/api/apprenant/recus/' . $paymentPublicId . '/download',
                ];
            });

        $tickets = $this->learnerEnrollmentsQuery($user)
            ->with(['formation', 'session'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (FormationEnrollment $enrollment) {
                return [
                    'id' => $enrollment->id,
                    'formation' => $enrollment->formation?->title,
                    'date' => $enrollment->created_at?->format('d/m/Y'),
                    'startDate' => $enrollment->session?->start_date?->format('d/m/Y') ?? 'À définir',
                    'status' => $enrollment->status === 'completed' ? 'completed' : 'active',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'receipts' => $receipts,
                'tickets' => $tickets,
            ],
        ]);
    }

    /**
     * Télécharger un reçu
     */
    public function previewRecu(string $payment)
    {
        $paymentModel = $this->resolveLearnerReceiptPayment($payment);
        $result = $this->receiptService->generateReceipt($paymentModel);
        $path = Storage::disk('public')->path($result['path']);
        $filename = 'Recu-' . ($result['receipt_number'] ?? $paymentModel->reference) . '.pdf';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function downloadRecu(string $payment)
    {
        $paymentModel = $this->resolveLearnerReceiptPayment($payment);

        return $this->receiptService->downloadReceipt($paymentModel);
    }

    /**
     * Mon profil
     */
    public function getProfil(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->getPublicId(),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'formation_id' => $user->formation_id,
                'enrollment_date' => $user->enrollment_date,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Mettre à jour mon profil
     */
    public function updateProfil(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:30',
            'address' => 'sometimes|string|max:500',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'data' => $user->fresh()->toExternalArray(),
        ]);
    }

    private function learnerEnrollmentsQuery(User $user): Builder
    {
        $this->syncLearnerOwnership($user);

        return FormationEnrollment::query()->where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('email', $user->email);
        });
    }

    private function learnerPaymentsQuery(User $user, array $enrollmentIds): Builder
    {
        return Payment::query()->where(function (Builder $query) use ($user, $enrollmentIds) {
            $query->where('user_id', $user->id)
                ->orWhere('payer_email', $user->email);

            if (!empty($enrollmentIds)) {
                $query->orWhere(function (Builder $subQuery) use ($enrollmentIds) {
                    $subQuery->where('payable_type', FormationEnrollment::class)
                        ->whereIn('payable_id', $enrollmentIds);
                });
            }
        });
    }

    private function resolvePrimaryLearnerEnrollment(User $user): ?FormationEnrollment
    {
        $enrollments = $this->learnerEnrollmentsQuery($user)
            ->with([
                'formation.formateur:id,name,email,phone,speciality,bio',
                'session.formateur:id,name,email,phone,speciality,bio',
            ])
            ->orderByDesc('created_at')
            ->get();

        $enrollments = $this->reconcileEnrollmentsWithCompletedPayments($enrollments);

        return $enrollments->firstWhere('status', 'confirmed')
            ?? $enrollments->firstWhere('status', 'completed')
            ?? $enrollments->firstWhere('status', 'pending_payment')
            ?? $enrollments->first();
    }

    private function expireLearnerPendingEnrollments(User $user): void
    {
        $this->learnerEnrollmentsQuery($user)
            ->where('status', 'pending_payment')
            ->get()
            ->each(function (FormationEnrollment $enrollment) {
                $this->enrollmentWindowService->expireIfNeeded($enrollment);
            });
    }

    private function mapCurrentFormation(FormationEnrollment $enrollment): array
    {
        $session = $enrollment->session;
        $formateur = $session?->formateur ?? $enrollment->formation?->formateur;
        $progress = $enrollment->status === 'completed'
            ? 100
            : max((int) ($enrollment->progression ?? 0), $enrollment->status === 'confirmed' ? 5 : 0);

        return [
            'id' => $enrollment->id,
            'title' => $enrollment->formation?->title ?? 'Formation',
            'status' => $enrollment->status,
            'status_label' => $enrollment->status === 'completed'
                ? 'Formation terminée'
                : 'Inscription validée',
            'progress' => $progress,
            'start_date' => $session?->start_date?->format('d/m/Y') ?? 'À définir',
            'end_date' => $session?->end_date?->format('d/m/Y'),
            'session' => [
                'id' => $session?->id,
                'name' => $session?->name ?? 'Session en cours',
                'location' => $session?->location ?? 'À définir',
                'start_date' => $session?->start_date?->format('d/m/Y'),
                'end_date' => $session?->end_date?->format('d/m/Y'),
                'start_time' => $session?->start_time,
                'end_time' => $session?->end_time,
            ],
            'formateur' => $this->mapTrainer($formateur),
            'next_session' => $session && $session->start_date && $session->start_date->isFuture()
                ? [
                    'date' => $session->start_date->format('d/m/Y'),
                    'time' => $session->start_time
                        ? substr((string) $session->start_time, 0, 5)
                        : '08:00',
                ]
                : null,
        ];
    }

    private function mapPendingRequest(FormationEnrollment $enrollment): array
    {
        $metadata = is_array($enrollment->metadata) ? $enrollment->metadata : [];
        $payment = Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->latest('id')
            ->first();

        $paymentReference = $metadata['payment_reference'] ?? $payment?->reference;
        $paymentUrl = $paymentReference
            ? rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $paymentReference
            : null;

        return [
            'id' => $enrollment->id,
            'title' => $enrollment->formation?->title ?? 'Formation',
            'status' => $enrollment->status,
            'status_label' => $enrollment->status === 'pending_payment'
                ? 'En attente de confirmation'
                : 'Demande annulée',
            'expires_at' => isset($metadata['payment_window_expires_at'])
                ? $metadata['payment_window_expires_at']
                : $this->enrollmentWindowService->getExpiresAt($enrollment)->toISOString(),
            'remaining_seconds' => $enrollment->status === 'pending_payment'
                ? $this->enrollmentWindowService->getRemainingSeconds($enrollment)
                : 0,
            'payment_reference' => $paymentReference,
            'payment_url' => $enrollment->status === 'pending_payment' ? $paymentUrl : null,
            'amount' => (float) ($metadata['inscription_fee'] ?? $enrollment->formation?->inscription_fee ?? 0),
            'error' => $payment?->metadata['initialization_error'] ?? null,
            'cancelled_reason' => $metadata['cancelled_reason'] ?? null,
            'submitted_at' => $enrollment->created_at?->toISOString(),
        ];
    }

    private function mapIssuedCertificate(FormationEnrollment $enrollment): ?array
    {
        if (!$this->hasActiveCertificate($enrollment)) {
            return null;
        }

        $certificate = $enrollment->certificate;
        $trainer = $enrollment->session?->formateur ?? $enrollment->formation?->formateur;
        $downloadAvailable = $certificate->pdf_path
            ? Storage::disk('public')->exists($certificate->pdf_path)
            : false;

        return [
            'id' => $certificate->reference,
            'reference' => $certificate->reference,
            'formation' => $enrollment->formation?->title ?? 'Formation',
            'completed_at' => $enrollment->completed_at?->toIso8601String(),
            'completedDate' => $enrollment->completed_at?->format('d/m/Y')
                ?? $certificate->issued_at?->format('d/m/Y')
                ?? 'N/A',
            'issued_at' => $certificate->issued_at?->toIso8601String(),
            'issuedDate' => $certificate->issued_at?->format('d/m/Y'),
            'instructor' => $trainer?->name ?? 'Équipe pédagogique MBC',
            'download_available' => $downloadAvailable,
            'verification_path' => $this->certificateService->buildVerificationPath($certificate->reference),
            'verification_url' => $this->certificateService->buildVerificationUrl($certificate->reference),
        ];
    }

    private function mapCertificateRequest(FormationEnrollment $enrollment): ?array
    {
        if ($this->hasActiveCertificate($enrollment)) {
            return null;
        }

        $request = $enrollment->certificateRequest;
        $status = $request?->status ?? 'eligible';
        $statusLabel = match ($status) {
            'pending' => 'Demande en attente du secrétariat',
            'approved' => 'Approuvée, prête à être générée',
            'rejected' => 'Demande rejetée par le secrétariat',
            'invalidated' => 'Certificat invalidé par le staff',
            default => 'Éligible à une demande',
        };

        return [
            'id' => $request?->id ?? ('eligible-' . $enrollment->id),
            'enrollment_id' => $enrollment->id,
            'formation' => $enrollment->formation?->title ?? 'Formation',
            'completed_at' => $enrollment->completed_at?->toIso8601String(),
            'completedDate' => $enrollment->completed_at?->format('d/m/Y'),
            'status' => $status,
            'status_label' => $statusLabel,
            'requested_at' => $request?->requested_at?->toIso8601String(),
            'requestedDate' => $request?->requested_at?->format('d/m/Y H:i'),
            'requested_by_name' => $request?->requestedBy?->name,
            'decision_at' => $request?->decision_at?->toIso8601String(),
            'decisionDate' => $request?->decision_at?->format('d/m/Y H:i'),
            'decision_notes' => $request?->decision_notes,
            'invalidation_reason' => $request?->invalidation_reason,
            'certificate_reference' => $enrollment->certificate?->reference,
        ];
    }

    private function mapPendingCertificate(FormationEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'formation' => $enrollment->formation?->title ?? 'Formation',
            'expectedDate' => $enrollment->session?->end_date?->format('d/m/Y') ?? 'À définir',
            'expected_date' => $enrollment->session?->end_date?->toDateString(),
            'progress' => $this->resolveEnrollmentProgress($enrollment),
            'status' => $enrollment->status,
            'status_label' => $enrollment->status === 'confirmed'
                ? 'Formation en cours'
                : 'En attente',
        ];
    }

    private function hasActiveCertificate(FormationEnrollment $enrollment): bool
    {
        $request = $enrollment->certificateRequest;

        return $enrollment->certificate !== null
            && $enrollment->certificate->revoked_at === null
            && ($request === null || $request->isApproved());
    }

    private function syncLearnerOwnership(User $user): void
    {
        $this->enrollmentOwnershipService->attachByEmail($user);
    }

    private function resolveEnrollmentProgress(FormationEnrollment $enrollment): int
    {
        if ($enrollment->status === 'completed') {
            return 100;
        }

        $progress = (int) ($enrollment->progression ?? 0);
        if ($progress > 0) {
            return max(0, min(100, $progress));
        }

        $session = $enrollment->session;
        if (!$session?->start_date || !$session?->end_date) {
            return $enrollment->status === 'confirmed' ? 5 : 0;
        }

        if ($session->start_date->isFuture()) {
            return 0;
        }

        if ($session->end_date->isPast()) {
            return 95;
        }

        $totalDays = max(1, $session->start_date->diffInDays($session->end_date) + 1);
        $elapsedDays = min($totalDays, max(0, $session->start_date->diffInDays(now()) + 1));

        return max(5, min(95, (int) round(($elapsedDays / $totalDays) * 100)));
    }

    private function hasCompletedEnrollmentPayment(FormationEnrollment $enrollment): bool
    {
        return Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->where('status', 'completed')
            ->exists();
    }

    private function reconcileEnrollmentsWithCompletedPayments(Collection $enrollments): Collection
    {
        $enrollmentIds = $enrollments->pluck('id')->filter()->values();

        if ($enrollmentIds->isEmpty()) {
            return $enrollments;
        }

        $completedPaymentsByEnrollment = Payment::query()
            ->select('payable_id')
            ->selectRaw('MAX(COALESCE(validated_at, created_at)) as last_paid_at')
            ->where('payable_type', FormationEnrollment::class)
            ->whereIn('payable_id', $enrollmentIds)
            ->where('status', 'completed')
            ->groupBy('payable_id')
            ->get()
            ->keyBy('payable_id');

        return $enrollments->map(function (FormationEnrollment $enrollment) use ($completedPaymentsByEnrollment) {
            $paymentInfo = $completedPaymentsByEnrollment->get($enrollment->id);

            if (!$paymentInfo || in_array($enrollment->status, ['confirmed', 'completed'], true)) {
                return $enrollment;
            }

            $metadata = is_array($enrollment->metadata) ? $enrollment->metadata : [];

            $enrollment->update([
                'status' => 'confirmed',
                'paid_at' => $enrollment->paid_at ?? $paymentInfo->last_paid_at ?? now(),
                'metadata' => array_merge($metadata, [
                    'reconciled_from_completed_payment' => true,
                    'reconciled_at' => now()->toISOString(),
                ]),
            ]);

            return $enrollment->fresh([
                'formation.formateur:id,name,email,phone,speciality,bio',
                'session.formateur:id,name,email,phone,speciality,bio',
            ]);
        });
    }

    private function mapTrainer(?User $trainer): ?array
    {
        if (!$trainer) {
            return null;
        }

        return [
            'id' => $trainer->id,
            'name' => $trainer->name,
            'email' => $trainer->email,
            'phone' => $trainer->phone,
            'speciality' => $trainer->speciality,
            'bio' => $trainer->bio,
        ];
    }

    private function buildPaymentHistory(FormationEnrollment $enrollment): array
    {
        return Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'id' => $payment->getPublicId(),
                    'reference' => $payment->reference,
                    'label' => $payment->description ?: $payment->purpose_label,
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                    'status_label' => $payment->status_label,
                    'method' => $payment->method_label,
                    'created_at' => $payment->created_at?->toISOString(),
                    'validated_at' => $payment->validated_at?->toISOString(),
                    'receipt_available' => $payment->status === 'completed',
                ];
            })
            ->values()
            ->all();
    }

    private function resolveLearnerReceiptPayment(string $payment): Payment
    {
        $user = Auth::user();

        return Payment::where('public_id', $payment)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('payer_email', $user->email);
            })
            ->where('status', 'completed')
            ->firstOrFail();
    }

    private function isRegistrationFeePayment(Payment $payment, float $registrationFeeAmount): bool
    {
        if ($payment->purpose_detail === 'inscription_fee') {
            return true;
        }

        if (($payment->metadata['payment_kind'] ?? null) === 'registration_fee') {
            return true;
        }

        if (stripos((string) $payment->description, 'inscription') !== false) {
            return true;
        }

        return $registrationFeeAmount > 0
            && abs((float) $payment->amount - $registrationFeeAmount) < 100
            && $payment->purpose !== 'formation_installment';
    }

    private function calculateSuggestedInstallmentAmount(float $remainingTrainingAmount, int $completedTrainingPaymentsCount): float
    {
        if ($remainingTrainingAmount <= 0) {
            return 0;
        }

        $remainingSlots = max(1, 3 - max(0, $completedTrainingPaymentsCount));

        return min($remainingTrainingAmount, (float) ceil($remainingTrainingAmount / $remainingSlots));
    }

    private function calculateRemainingAmount(?FormationEnrollment $enrollment): float
    {
        if (!$enrollment) {
            return 0;
        }

        $metadata = is_array($enrollment->metadata) ? $enrollment->metadata : [];
        $inscriptionFee = (float) ($metadata['inscription_fee'] ?? $enrollment->formation?->inscription_fee ?? 0);
        $formationPrice = (float) ($metadata['formation_price'] ?? $enrollment->formation?->price ?? 0);

        $completedAmount = (float) Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->where('status', 'completed')
            ->sum('amount');

        return max(0, ($inscriptionFee + $formationPrice) - $completedAmount);
    }
}
