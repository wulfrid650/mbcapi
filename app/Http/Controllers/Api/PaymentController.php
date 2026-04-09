<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MonerooService;
use App\Services\ReceiptService;
use App\Services\PromoCodeService;
use App\Services\ActivityLogService;
use App\Services\FormationEnrollmentWindowService;
use App\Models\Payment;
use App\Models\FormationEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentReceipt;
use App\Mail\PaymentReceiptWithAttachment;

class PaymentController extends Controller
{
    private MonerooService $monerooService;
    private ReceiptService $receiptService;
    private PromoCodeService $promoCodeService;
    private FormationEnrollmentWindowService $enrollmentWindowService;

    public function __construct(
        MonerooService $monerooService,
        ReceiptService $receiptService,
        PromoCodeService $promoCodeService,
        FormationEnrollmentWindowService $enrollmentWindowService
    ) {
        $this->monerooService = $monerooService;
        $this->receiptService = $receiptService;
        $this->promoCodeService = $promoCodeService;
        $this->enrollmentWindowService = $enrollmentWindowService;
    }

    /**
     * Obtenir les motifs de paiement disponibles
     */
    public function getPurposes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Payment::PURPOSES,
        ]);
    }

    /**
     * Initialiser un paiement
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'currency' => 'nullable|string|in:XOF,XAF,EUR',
            'description' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|in:' . implode(',', array_keys(Payment::PURPOSES)),
            'purpose_detail' => 'nullable|string|max:255',
            'payable_type' => 'nullable|string|in:formation_enrollment,service_request,project',
            'payable_id' => 'nullable|integer',
            'return_url' => 'nullable|url',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
            'customer_first_name' => 'nullable|string',
            'customer_last_name' => 'nullable|string',
            'promo_code' => 'nullable|string',
        ]);

        $user = Auth::user();
        $amount = $request->amount;
        $promoCodeApplied = null;
        $originalAmount = null;
        $discountAmount = null;

        // Appliquer le code promo si fourni
        if ($request->promo_code) {
            $promoResult = $this->promoCodeService->validateAndApply(
                $request->promo_code,
                $amount,
                $request->payable_id,
                $user?->id,
                $request->customer_email
            );

            if ($promoResult['valid']) {
                $originalAmount = $amount;
                $discountAmount = $promoResult['discount'];
                $amount = $promoResult['new_amount'];
                $promoCodeApplied = $promoResult['promo_code'];
            }
        }

        // Préparer les données
        $data = [
            'amount' => $amount,
            'currency' => $request->currency ?? 'XOF',
            'description' => $request->description ?? 'Paiement MBC',
            'user_id' => $user?->id,
            'customer_email' => $request->customer_email ?? $user?->email,
            'customer_first_name' => $request->customer_first_name ?? $user?->name,
            'customer_last_name' => $request->customer_last_name ?? '',
            'customer_phone' => $request->customer_phone ?? $user?->phone,
            'return_url' => $request->return_url,
            'purpose' => $request->purpose ?? 'other',
            'purpose_detail' => $request->purpose_detail,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'promo_code_id' => $promoCodeApplied?->id,
        ];

        // Ajouter les informations payable si fournies
        if ($request->payable_type && $request->payable_id) {
            $data['payable_type'] = $this->resolvePayableType($request->payable_type);
            $data['payable_id'] = $request->payable_id;
        }

        $result = $this->monerooService->initiatePayment($data);

        if ($result['success']) {
            // Enregistrer l'utilisation du code promo
            if ($promoCodeApplied && $discountAmount > 0) {
                $payment = Payment::where('reference', $result['reference'])->first();
                if ($payment) {
                    $this->promoCodeService->recordUsage(
                        $promoCodeApplied,
                        $discountAmount,
                        $user?->id,
                        $request->customer_email,
                        $payment->id
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Paiement initialisé',
                'data' => [
                    'payment_id' => $this->resolvePublicPaymentId($result['payment_id'] ?? null),
                    'reference' => $result['reference'],
                    'checkout_url' => $result['checkout_url'],
                    'amount' => $amount,
                    'original_amount' => $originalAmount,
                    'discount_applied' => $discountAmount,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Échec de l\'initialisation',
            'error' => $result['error'] ?? null,
        ], 400);
    }

    /**
     * Initialiser un paiement pour une inscription à une formation
     */
    public function initiateEnrollmentPayment(Request $request): JsonResponse
    {
        $request->validate([
            'enrollment_id' => 'required|integer|exists:formation_enrollments,id',
            'return_url' => 'nullable|url',
            'promo_code' => 'nullable|string',
        ]);

        $user = Auth::user();
        $enrollment = FormationEnrollment::with('formation')->findOrFail($request->enrollment_id);

        // Vérifier que l'utilisateur est bien le propriétaire de l'inscription
        if ($enrollment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à payer cette inscription',
            ], 403);
        }

        // Vérifier que l'inscription n'est pas déjà payée
        if ($enrollment->status === 'confirmed' || $enrollment->paid_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cette inscription est déjà payée',
            ], 400);
        }

        if ($enrollment->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande a expire. Veuillez soumettre une nouvelle inscription.',
            ], 410);
        }

        $enrollment = $this->enrollmentWindowService->reopenPaymentWindow($enrollment, $user, 'apprenant_retry');
        $existingPendingPayment = Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $data = [
            'amount' => $enrollment->formation->inscription_fee ?? 10000,
            'currency' => 'XOF',
            'description' => "Frais d'inscription - {$enrollment->formation->title}",
            'user_id' => $user->id,
            'customer_email' => $user->email,
            'customer_first_name' => $user->name,
            'customer_phone' => $user->phone ?? '',
            'payable_type' => 'App\\Models\\FormationEnrollment',
            'payable_id' => $enrollment->id,
            'payment_id' => $existingPendingPayment?->id,
            'reference' => $existingPendingPayment?->reference,
            'return_url' => $request->return_url,
            'purpose' => 'formation_payment',
            'purpose_detail' => 'inscription_fee',
            'metadata' => [
                'formation_id' => $enrollment->formation_id,
                'formation_title' => $enrollment->formation->title,
                'session_id' => $enrollment->session_id,
                'payment_window_expires_at' => $this->enrollmentWindowService->getExpiresAt($enrollment)->toISOString(),
            ],
        ];

        $result = $this->monerooService->initiatePayment($data);

        $enrollment->update([
            'metadata' => array_merge($enrollment->metadata ?? [], [
                'payment_reference' => $result['reference'] ?? ($existingPendingPayment?->reference),
                'payment_id' => $result['payment_id'] ?? ($existingPendingPayment?->id),
            ]),
        ]);

        $paymentReference = $result['reference'] ?? $existingPendingPayment?->reference;
        $paymentUrl = $paymentReference
            ? rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $paymentReference
            : null;

        if ($result['success']) {

        return response()->json([
            'success' => true,
            'message' => 'Paiement initialisé',
            'data' => [
                'payment_id' => $this->resolvePublicPaymentId($result['payment_id'] ?? null),
                'reference' => $paymentReference,
                'checkout_url' => $result['checkout_url'],
                    'payment_url' => $paymentUrl,
                    'enrollment_id' => $enrollment->id,
                    'formation' => $enrollment->formation->title,
                    'amount' => $data['amount'],
                    'expires_at' => $this->enrollmentWindowService->getExpiresAt($enrollment)->toISOString(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande conservee en attente. Vous pouvez reprendre le paiement.',
            'data' => [
                'payment_id' => $this->resolvePublicPaymentId($result['payment_id'] ?? $existingPendingPayment?->id),
                'reference' => $paymentReference,
                'checkout_url' => null,
                'payment_url' => $paymentUrl,
                'enrollment_id' => $enrollment->id,
                'formation' => $enrollment->formation->title,
                'amount' => $data['amount'],
                'expires_at' => $this->enrollmentWindowService->getExpiresAt($enrollment)->toISOString(),
            ],
            'error' => $result['error'] ?? null,
        ], 202);
    }


    /**
     * Vérifier un code promo (Public)
     */
    public function checkPromo(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric',
            'formation_id' => 'nullable|integer',
        ]);

        $validation = $this->promoCodeService->validateAndApply(
            (string) $request->code,
            (float) $request->amount,
            $request->integer('formation_id') ?: null,
            Auth::id(),
            $request->input('guest_email')
        );

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['error'] ?? 'Code promo invalide ou expiré',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $validation['promo_code']->code,
                'discount' => $validation['discount'],
                'new_amount' => $validation['new_amount'],
                'type' => $validation['type'],
                'value' => $validation['value'],
                'description' => $validation['description'],
            ],
        ]);
    }

    /**
     * Payer un paiement en attente existant (via lien généré)
     */
    public function payPending(Request $request, string $reference): JsonResponse
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        $actor = Auth::user();

        if ($payment->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement a deja ete valide',
            ], 400);
        }

        if ($payment->status !== 'pending' && $payment->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement ne peut pas etre relance',
            ], 400);
        }

        $payment->load(['user', 'payable']);
        $this->recordPaymentLinkRetry($payment, $actor);
        $enrollment = $payment->payable instanceof FormationEnrollment ? $payment->payable : null;

        if ($enrollment) {
            $this->enrollmentWindowService->expireIfNeeded($enrollment);
            $enrollment->refresh();

            if ($enrollment->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande a expire et a ete annulee.',
                ], 410);
            }

            $this->enrollmentWindowService->reopenPaymentWindow($enrollment, Auth::user(), 'payment_retry');
            $payment->refresh();
        }

        // Appliquer le code promo si fourni
        if ($request->has('promo_code')) {
            $promo = \App\Models\PromoCode::where('code', $request->promo_code)->first();

            // On vérifie si le code est valide
            if ($promo && $promo->isValid($payment->payable_type === 'App\\Models\\FormationEnrollment' ? $payment->payable_id : null)) {
                // On vérifie si ce code n'a pas déjà été appliqué
                if (!isset($payment->metadata['promo_code'])) {
                    $originalAmount = $payment->amount;
                    $discount = $promo->calculateDiscount($originalAmount);
                    $newAmount = max(0, $originalAmount - $discount);

                    $payment->update([
                        'amount' => $newAmount,
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'original_amount' => $originalAmount,
                            'promo_code' => $promo->code,
                            'discount_amount' => $discount
                        ])
                    ]);

                    $promo->incrementUsage();
                }
            }
        }

        // Préparer les données pour Moneroo en utilisant le paiement existant
        $data = [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'description' => $payment->description,
            'user_id' => $payment->user_id,
            'customer_email' => $payment->user->email ?? $payment->metadata['customer_email'] ?? '',
            'customer_first_name' => $payment->user->name ?? $payment->metadata['customer_first_name'] ?? '',
            'customer_last_name' => $payment->metadata['customer_last_name'] ?? '',
            'customer_phone' => $payment->user->phone ?? $payment->metadata['customer_phone'] ?? '',
            'return_url' => $payment->metadata['return_url'] ?? null,
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'payable_type' => $payment->payable_type,
            'payable_id' => $payment->payable_id,
            'purpose' => $payment->purpose,
            'purpose_detail' => $payment->purpose_detail,
            'metadata' => array_merge(
                is_array($payment->metadata) ? $payment->metadata : [],
                ['payment_reference' => $payment->reference]
            ),
        ];

        $result = $this->monerooService->initiatePayment($data);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Lien de paiement généré',
                'data' => [
                    'payment_id' => $payment->getPublicId(),
                    'reference' => $payment->reference,
                    'checkout_url' => $result['checkout_url'],
                    'payment_url' => rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Le paiement reste en attente. Vous pouvez reessayer.',
            'data' => [
                'payment_id' => $payment->getPublicId(),
                'reference' => $payment->reference,
                'checkout_url' => null,
                'payment_url' => rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference,
                'expires_at' => $enrollment ? $this->enrollmentWindowService->getExpiresAt($enrollment)->toISOString() : null,
            ],
            'error' => $result['error'] ?? null,
        ], 202);
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function verify(Request $request, ?string $reference = null): JsonResponse
    {
        // Récupérer la référence depuis l'URL ou les paramètres
        $ref = $reference ?? $request->reference ?? $request->transaction_id;

        if (!$ref) {
            return response()->json([
                'success' => false,
                'message' => 'Référence de paiement requise',
            ], 400);
        }

        $payment = Payment::where('reference', $ref)
            ->orWhere('transaction_id', $ref)
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé',
            ], 404);
        }

        // Vérifier auprès de Moneroo si le paiement est en attente
        if ($payment->status === 'pending' && $payment->transaction_id) {
            $verification = $this->monerooService->verifyPayment($payment->transaction_id);

            if ($verification['success']) {
                $monerooStatus = $verification['data']['status'] ?? null;

                if ($monerooStatus === 'success' && $payment->status !== 'completed') {
                    $payment->update([
                        'status' => 'completed',
                        'validated_at' => now(),
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'verified_at' => now()->toISOString(),
                            'moneroo_data' => $verification['data'],
                        ]),
                    ]);

                    // Send Email Receipt
                    $receiptUser = $payment->deriveUser();
                    if ($receiptUser && $receiptUser->email) {
                        try {
                            Mail::to($receiptUser->email)->send(new PaymentReceipt($payment));
                        } catch (\Exception $e) {
                            Log::error('Failed to send payment receipt email: ' . $e->getMessage());
                        }
                    }

                    // Log activity
                    ActivityLogService::log(
                        'Paiement validé',
                        'Paiement de ' . $payment->amount . ' ' . $payment->currency . ' reçu via ' . $payment->method_label,
                        $payment,
                        $payment->user_id
                    );
                } elseif ($monerooStatus === 'failed') {
                    $payment->update([
                        'status' => 'failed',
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'verified_at' => now()->toISOString(),
                            'failure_reason' => $verification['data']['failure_reason'] ?? 'Unknown',
                        ]),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $payment->reference,
                'description' => $payment->description,
                'status' => $payment->status,
                'status_label' => $payment->status_label,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'method_label' => $payment->method_label,
                'created_at' => $payment->created_at->toISOString(),
                'validated_at' => $payment->validated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Webhook Moneroo
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('Moneroo webhook received', $request->all());

        $result = $this->monerooService->handleWebhook($request->all());

        if ($result['success']) {
            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'error', 'message' => $result['message']], 400);
    }

    /**
     * Historique des paiements de l'utilisateur
     */
    public function history(Request $request): JsonResponse
    {
        $user = Auth::user();

        $payments = Payment::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 10));
        $payments->setCollection(
            $payments->getCollection()->map(fn(Payment $payment) => $payment->toExternalArray())
        );

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Détails d'un paiement
     */
    public function show(string $reference): JsonResponse
    {
        // Accès public via la référence unique
        $payment = Payment::with('payable')->where('reference', $reference)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé',
            ], 404);
        }

        $enrollment = $payment->payable instanceof FormationEnrollment ? $payment->payable : null;
        if ($enrollment) {
            $this->enrollmentWindowService->expireIfNeeded($enrollment);
            $payment->refresh();
            $enrollment->refresh();
        }

        $this->recordPaymentLinkConsultation($payment, Auth::user());
        $payment->refresh();

        $paymentUrl = rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference;

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->status,
                'status_label' => $payment->status, // Fallback if accessor missing, though model likely has it
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'description' => $payment->description,
                'metadata' => $payment->metadata,
                'formation_id' => $enrollment?->formation_id ?? ($payment->metadata['formation_id'] ?? null),
                'payment_url' => $paymentUrl,
                'expires_at' => $enrollment ? $this->enrollmentWindowService->getExpiresAt($enrollment)->toISOString() : null,
                'remaining_seconds' => $enrollment && $enrollment->status === 'pending_payment'
                    ? $this->enrollmentWindowService->getRemainingSeconds($enrollment)
                    : 0,
                'enrollment_status' => $enrollment?->status,
                'created_at' => $payment->created_at->toISOString(),
                'validated_at' => $payment->validated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Méthodes de paiement disponibles
     */
    public function methods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->monerooService->getAvailableMethods(),
        ]);
    }

    private function resolvePublicPaymentId(?int $paymentId): ?string
    {
        if (!$paymentId) {
            return null;
        }

        return Payment::find($paymentId)?->getPublicId();
    }

    private function recordPaymentLinkConsultation(Payment $payment, $actor = null): void
    {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $actorName = $actor?->name ?? 'Un visiteur';

        $payment->update([
            'metadata' => array_merge($metadata, [
                'payment_link_url' => rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference,
                'link_access_count' => ((int) ($metadata['link_access_count'] ?? 0)) + 1,
                'last_link_accessed_at' => now()->toISOString(),
                'last_link_access_ip' => request()->ip(),
                'last_link_access_user_agent' => request()->userAgent(),
            ]),
        ]);

        ActivityLogService::log(
            'Lien de paiement consulté',
            "{$actorName} a consulté le lien de paiement {$payment->reference}.",
            $payment,
            $actor?->id
        );
    }

    private function recordPaymentLinkRetry(Payment $payment, $actor = null): void
    {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $actorName = $actor?->name ?? 'Un visiteur';

        $payment->update([
            'metadata' => array_merge($metadata, [
                'payment_link_url' => rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $payment->reference,
                'link_retry_count' => ((int) ($metadata['link_retry_count'] ?? 0)) + 1,
                'last_link_retry_at' => now()->toISOString(),
                'last_link_retry_ip' => request()->ip(),
                'last_link_retry_user_agent' => request()->userAgent(),
                'last_link_retry_by' => $actor?->id,
                'last_link_retry_by_name' => $actor?->name,
            ]),
        ]);

        ActivityLogService::log(
            'Lien de paiement relancé',
            "{$actorName} a relancé le paiement {$payment->reference} via le lien de paiement.",
            $payment,
            $actor?->id
        );
    }

    /**
     * Générer le reçu d'un paiement
     */
    public function generateReceipt(string $reference): JsonResponse
    {
        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé',
            ], 404);
        }

        // Vérifier que le paiement est complété
        if ($payment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Le reçu ne peut être généré que pour les paiements validés',
            ], 400);
        }

        try {
            $result = $this->receiptService->generateReceipt($payment);

            return response()->json([
                'success' => true,
                'message' => 'Reçu généré avec succès',
                'data' => [
                    'receipt_number' => $result['receipt_number'],
                    'download_url' => $result['url'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur génération reçu: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du reçu',
            ], 500);
        }
    }

    /**
     * Télécharger le reçu d'un paiement
     */
    public function downloadReceipt(string $reference)
    {
        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé',
            ], 404);
        }

        if ($payment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Le reçu ne peut être téléchargé que pour les paiements validés',
            ], 400);
        }

        try {
            return $this->receiptService->downloadReceipt($payment);
        } catch (\Exception $e) {
            Log::error('Erreur téléchargement reçu: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du reçu',
            ], 500);
        }
    }

    /**
     * Envoyer le reçu par email
     */
    public function sendReceiptByEmail(Request $request, string $reference): JsonResponse
    {
        $request->validate([
            'email' => 'nullable|email',
        ]);

        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé',
            ], 404);
        }

        if ($payment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Le reçu ne peut être envoyé que pour les paiements validés',
            ], 400);
        }

        $email = $request->email ?? $payment->payer_email ?? $payment->user?->email;

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune adresse email spécifiée',
            ], 400);
        }

        try {
            $sent = $this->receiptService->sendReceiptByEmail($payment, $email);

            if ($sent) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reçu envoyé par email avec succès',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du reçu',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Erreur envoi reçu email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du reçu',
            ], 500);
        }
    }

    /**
     * Résoudre le type de payable
     */
    private function resolvePayableType(string $type): string
    {
        return match ($type) {
            'formation_enrollment' => 'App\\Models\\FormationEnrollment',
            'service_request' => 'App\\Models\\ServiceRequest',
            'project' => 'App\\Models\\PortfolioProject',
            default => $type,
        };
    }
}
