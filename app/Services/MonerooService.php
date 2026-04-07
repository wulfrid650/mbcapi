<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FormationEnrollment;
use App\Models\Payment;
use App\Models\SiteSetting;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentReceipt;
use App\Mail\PaymentReceived;
use App\Mail\RegistrationThanks;
use App\Mail\NewApprenant;

/**
 * Service pour l'intégration de Moneroo
 * 
 * Moneroo est une passerelle de paiement africaine supportant:
 * - Orange Money
 * - MTN Mobile Money
 * - Cartes bancaires
 */
class MonerooService
{
    private string $baseUrl;
    private string $secretKey;
    private bool $isTestMode;
    private string $currency;

    public function __construct()
    {
        $this->baseUrl = (string) config('moneroo.baseUrl', 'https://api.moneroo.io/v1');
        // Priorité aux paramètres en base de données, puis .env
        $this->secretKey = $this->getConfigValue('moneroo_secret_key', config('moneroo.secretKey'));
        $this->isTestMode = $this->getConfigValue('moneroo_test_mode', config('moneroo.testMode', true)) === 'true' || $this->getConfigValue('moneroo_test_mode', config('moneroo.testMode', true)) === true;
        $this->currency = $this->getConfigValue('moneroo_currency', config('moneroo.currency', 'XAF'));
    }

    /**
     * Récupérer une valeur de configuration depuis SiteSettings ou fallback
     */
    private function getConfigValue(string $key, $default = null)
    {
        try {
            $setting = SiteSetting::where('key', $key)->first();
            if ($setting && !empty($setting->value)) {
                return $setting->value;
            }
        } catch (\Exception $e) {
            // Table peut ne pas exister en dev
        }
        return $default;
    }

    /**
     * Vérifier si les paiements sont activés
     */
    public function isPaymentEnabled(): bool
    {
        $enabled = $this->getConfigValue('payment_enabled', 'true');
        return $enabled === 'true' || $enabled === true || $enabled === '1';
    }

    /**
     * Vérifier si la configuration Moneroo est valide
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    /**
     * Initialiser un paiement
     *
     * @param array $data
     * @return array
     */
    public function initiatePayment(array $data): array
    {
        $payment = null;

        try {
            // Vérifier si les paiements sont activés
            if (!$this->isPaymentEnabled()) {
                return [
                    'success' => false,
                    'message' => 'Les paiements en ligne sont temporairement désactivés',
                ];
            }

            // Vérifier si Moneroo est configuré
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'La passerelle de paiement n\'est pas configurée. Contactez l\'administrateur.',
                ];
            }

            // Réutiliser un paiement en attente si présent
            $payment = $this->resolveExistingPayment($data);
            $reference = $payment?->reference ?? Payment::generateReference();

            // Construire l'URL de retour avec la référence
            $baseReturnUrl = $data['return_url'] ?? config('app.frontend_url') . '/paiement/confirmation';
            $returnUrlSeparator = str_contains($baseReturnUrl, '?') ? '&' : '?';
            $returnUrl = $baseReturnUrl . $returnUrlSeparator . 'reference=' . $reference;

            // Extraire prénom et nom si non fournis
            $fullName = $data['customer_first_name'] ?? 'Client';
            $nameParts = explode(' ', (string) $fullName, 2);
            $firstName = !empty($nameParts[0]) ? (string) $nameParts[0] : 'Client';
            $lastName = !empty($data['customer_last_name'])
                ? (string) $data['customer_last_name']
                : (!empty($nameParts[1]) ? (string) $nameParts[1] : 'MBC');

            // Construire les métadonnées (exclure les valeurs null)
            $metadata = ['reference' => $reference];
            if (!empty($data['payable_type'])) {
                $metadata['payable_type'] = (string) $data['payable_type'];
            }
            if (!empty($data['payable_id'])) {
                $metadata['payable_id'] = (int) $data['payable_id'];
            }
            if (!empty($data['user_id'])) {
                $metadata['user_id'] = (int) $data['user_id'];
            }

            $customerFirstName = $data['customer_first_name'] ?? $firstName;
            $customerLastName = $data['customer_last_name'] ?? $lastName;

            if (empty($data['customer_last_name']) && !empty($data['customer_first_name'])) {
                $parts = explode(' ', (string) $data['customer_first_name'], 2);
                $customerFirstName = $parts[0] ?? $firstName;
                if (empty($customerLastName) && isset($parts[1])) {
                    $customerLastName = $parts[1];
                }
            }

            $customerFirstName = $customerFirstName ?: 'Client';
            $customerLastName = $customerLastName ?: 'MBC';
            $customerFullName = trim($customerFirstName . ' ' . $customerLastName);
            $customerEmail = $data['customer_email'] ?? 'client@madibabc.com';
            $customerPhone = $this->normalizePhone($data['customer_phone'] ?? '');

            $paymentMetadata = [];

            if (!empty($data['metadata']) && is_array($data['metadata'])) {
                $paymentMetadata = array_merge($paymentMetadata, $data['metadata']);
            }

            $paymentMetadata['customer_first_name'] = $customerFirstName;
            $paymentMetadata['customer_last_name'] = $customerLastName;
            $paymentMetadata['customer_full_name'] = $customerFullName;
            $paymentMetadata['customer_email'] = $customerEmail;
            if (!empty($customerPhone)) {
                $paymentMetadata['customer_phone'] = $customerPhone;
            }
            if (!empty($data['return_url'])) {
                $paymentMetadata['return_url'] = $data['return_url'];
            }

            $payment = $this->upsertPendingPayment($payment, $reference, $data, $paymentMetadata, $customerFullName, $customerEmail, $customerPhone);

            $payload = [
                'amount' => (int) $data['amount'],
                'currency' => $data['currency'] ?? $this->currency,
                'description' => $data['description'] ?? 'Paiement MBC',
                'customer' => [
                    'email' => $customerEmail,
                    'first_name' => $customerFirstName,
                    'last_name' => $customerLastName,
                    'phone' => $customerPhone,
                ],
                'return_url' => $returnUrl,
                'callback_url' => config('app.url') . '/api/payments/webhook/moneroo',
                'metadata' => $metadata,
            ];

            // Appeler l'API Moneroo
            // Note: En développement, on peut désactiver la vérification SSL
            $httpClient = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout((int) config('moneroo.connectTimeout', 10))
                ->timeout((int) config('moneroo.timeout', 20))
                ->retry(
                    (int) config('moneroo.retryTimes', 1),
                    (int) config('moneroo.retryDelayMs', 500)
                );

            // Désactiver la vérification SSL en mode développement
            if (app()->environment('local')) {
                $httpClient = $httpClient->withoutVerifying();
            }

            $response = $httpClient->post("{$this->baseUrl}/payments/initialize", $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $paymentMetadata['moneroo_response'] = $responseData['data'] ?? [];
                $payment->update([
                    'transaction_id' => $responseData['data']['id'] ?? $payment->transaction_id,
                    'method' => 'moneroo',
                    'status' => 'pending',
                    'metadata' => array_merge(
                        is_array($payment->metadata) ? $payment->metadata : [],
                        $paymentMetadata,
                        [
                            'initialization_error' => null,
                            'last_initialization_at' => now()->toISOString(),
                        ]
                    ),
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'reference' => $reference,
                    'checkout_url' => $responseData['data']['checkout_url'] ?? null,
                    'transaction_id' => $responseData['data']['id'] ?? null,
                ];
            }

            Log::error('Moneroo payment initialization failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'payload' => $payload,
            ]);

            // Récupérer les erreurs détaillées
            $responseData = $response->json();
            $errorMessage = $responseData['message'] ?? 'Erreur inconnue';
            if (isset($responseData['errors'])) {
                $errorMessage .= ' (' . collect($responseData['errors'])->flatten()->implode(', ') . ')';
            }

            if ($payment) {
                $payment->update([
                    'status' => 'pending',
                    'metadata' => array_merge(
                        is_array($payment->metadata) ? $payment->metadata : [],
                        [
                            'initialization_error' => $errorMessage,
                            'last_initialization_at' => now()->toISOString(),
                        ]
                    ),
                ]);
            }

            return [
                'success' => false,
                'message' => 'Échec de l\'initialisation du paiement',
                'error' => $errorMessage,
                'payment_id' => $payment?->id,
                'reference' => $payment?->reference ?? $reference,
            ];

        } catch (Exception $e) {
            Log::error('Moneroo payment error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($payment) {
                $payment->update([
                    'status' => 'pending',
                    'metadata' => array_merge(
                        is_array($payment->metadata) ? $payment->metadata : [],
                        [
                            'initialization_error' => $e->getMessage(),
                            'last_initialization_at' => now()->toISOString(),
                        ]
                    ),
                ]);
            }

            return [
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement',
                'error' => $e->getMessage(),
                'payment_id' => $payment?->id,
                'reference' => $payment?->reference ?? null,
            ];
        }
    }

    /**
     * Vérifier le statut d'un paiement
     *
     * @param string $transactionId
     * @return array
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $httpClient = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
            ]);

            // Désactiver la vérification SSL en mode développement
            if (app()->environment('local')) {
                $httpClient = $httpClient->withoutVerifying();
            }

            $response = $httpClient->get("{$this->baseUrl}/payments/{$transactionId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => 'Impossible de vérifier le paiement',
                'error' => $response->json()['message'] ?? 'Erreur inconnue',
            ];

        } catch (Exception $e) {
            Log::error('Moneroo verify payment error', [
                'transaction_id' => $transactionId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Traiter le webhook de Moneroo
     *
     * @param array $payload
     * @return array
     */
    public function handleWebhook(array $payload): array
    {
        try {
            $event = $payload['event'] ?? null;
            $data = $payload['data'] ?? [];

            if (!$event || !$data) {
                return ['success' => false, 'message' => 'Payload invalide'];
            }

            // Récupérer la référence depuis les metadata
            $metadata = $data['metadata'] ?? [];
            $reference = $metadata['reference'] ?? null;

            if (!$reference) {
                Log::warning('Moneroo webhook: référence manquante', $payload);
                return ['success' => false, 'message' => 'Référence manquante'];
            }

            // Trouver le paiement
            $payment = Payment::where('reference', $reference)->first();

            if (!$payment) {
                Log::warning('Moneroo webhook: paiement non trouvé', ['reference' => $reference]);
                return ['success' => false, 'message' => 'Paiement non trouvé'];
            }

            // Mettre à jour le statut selon l'événement
            switch ($event) {
                case 'payment.success':
                    $payment->status = 'completed';
                    $payment->validated_at = now();
                    $payment->method = $this->mapPaymentMethod($data['payment_method'] ?? null);
                    $payment->metadata = array_merge($payment->metadata ?? [], [
                        'moneroo_status' => 'success',
                        'payment_method_details' => $data['payment_method'] ?? null,
                        'completed_at' => $data['completed_at'] ?? now()->toISOString(),
                    ]);
                    break;

                case 'payment.failed':
                    $payment->status = 'failed';
                    $payment->metadata = array_merge($payment->metadata ?? [], [
                        'moneroo_status' => 'failed',
                        'failure_reason' => $data['failure_reason'] ?? 'Unknown',
                    ]);
                    break;

                case 'payment.cancelled':
                    $payment->status = 'failed';
                    $payment->metadata = array_merge($payment->metadata ?? [], [
                        'moneroo_status' => 'cancelled',
                    ]);
                    break;

                default:
                    Log::info('Moneroo webhook: événement non géré', ['event' => $event]);
                    return ['success' => true, 'message' => 'Événement ignoré'];
            }

            $payment->save();

            // Déclencher les actions post-paiement si réussi
            if ($payment->status === 'completed') {
                $this->onPaymentSuccess($payment);
            }

            return [
                'success' => true,
                'message' => 'Webhook traité avec succès',
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ];

        } catch (Exception $e) {
            Log::error('Moneroo webhook error', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors du traitement du webhook',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mapper la méthode de paiement Moneroo vers nos méthodes
     */
    private function mapPaymentMethod(?string $method): string
    {
        return match ($method) {
            'orange_money', 'orange-money' => 'orange_money',
            'mtn_money', 'mtn-money', 'mtn_momo' => 'mtn_momo',
            'wave' => 'wave',
            'card' => 'carte_bancaire',
            default => $method ?? 'moneroo',
        };
    }

    private function resolveExistingPayment(array $data): ?Payment
    {
        if (!empty($data['payment_id'])) {
            return Payment::find($data['payment_id']);
        }

        if (!empty($data['reference'])) {
            return Payment::where('reference', $data['reference'])->first();
        }

        if (!empty($data['payable_type']) && !empty($data['payable_id'])) {
            return Payment::query()
                ->where('payable_type', $data['payable_type'])
                ->where('payable_id', $data['payable_id'])
                ->where('status', 'pending')
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function upsertPendingPayment(
        ?Payment $payment,
        string $reference,
        array $data,
        array $metadata,
        string $customerFullName,
        string $customerEmail,
        string $customerPhone
    ): Payment {
        $payload = [
            'reference' => $reference,
            'user_id' => $data['user_id'] ?? $payment?->user_id,
            'payable_type' => $data['payable_type'] ?? $payment?->payable_type,
            'payable_id' => $data['payable_id'] ?? $payment?->payable_id,
            'amount' => $data['amount'],
            'original_amount' => $data['original_amount'] ?? $payment?->original_amount,
            'discount_amount' => $data['discount_amount'] ?? $payment?->discount_amount,
            'promo_code_id' => $data['promo_code_id'] ?? $payment?->promo_code_id,
            'currency' => $data['currency'] ?? $this->currency,
            'method' => 'moneroo',
            'status' => 'pending',
            'description' => $data['description'] ?? 'Paiement MBC',
            'purpose' => $data['purpose'] ?? (($data['payable_type'] ?? null) === 'App\\Models\\FormationEnrollment' ? 'formation_payment' : null),
            'purpose_detail' => $data['purpose_detail'] ?? $payment?->purpose_detail,
            'payer_name' => $customerFullName,
            'payer_email' => $customerEmail,
            'payer_phone' => $customerPhone ?: null,
            'metadata' => array_merge(
                is_array($payment?->metadata) ? $payment->metadata : [],
                $metadata,
                [
                    'initialization_error' => null,
                    'last_initialization_at' => now()->toISOString(),
                ]
            ),
        ];

        if ($payment) {
            $payment->update($payload);
            return $payment->fresh();
        }

        return Payment::create($payload);
    }

    private function normalizePhone(string $input): string
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return '';
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? '';
        if (str_starts_with($digits, '+')) {
            return $digits;
        }
        if (str_starts_with($digits, '00')) {
            return '+' . substr($digits, 2);
        }
        $numeric = preg_replace('/\D/', '', $digits) ?? '';
        if (strlen($numeric) === 9) {
            return '+237' . $numeric;
        }
        if (str_starts_with($numeric, '237')) {
            return '+' . $numeric;
        }

        return $numeric;
    }

    /**
     * Actions à effectuer après un paiement réussi
     */
    private function onPaymentSuccess(Payment $payment): void
    {
        $windowService = app(FormationEnrollmentWindowService::class);
        $paymentMetadata = is_array($payment->metadata) ? $payment->metadata : [];

        // Activer l'inscription à la formation si c'est le cas
        if ($payment->payable_type === FormationEnrollment::class) {
            $enrollment = $payment->payable;
            if ($enrollment) {
                $isOutsideWindow = $enrollment->status === 'cancelled'
                    || (
                        $enrollment->status === 'pending_payment'
                        && $windowService->getExpiresAt($enrollment)->isPast()
                    );

                if ($isOutsideWindow) {
                    $windowService->expireIfNeeded($enrollment);

                    $payment->update([
                        'metadata' => array_merge($paymentMetadata, [
                            'validated_outside_window' => true,
                            'late_payment_received_at' => now()->toISOString(),
                        ]),
                    ]);

                    ActivityLogService::log(
                        'Paiement reçu hors délai',
                        "Un paiement Moneroo a été reçu hors délai pour l'inscription de {$enrollment->full_name}. Une vérification manuelle est requise.",
                        $payment,
                        $payment->user_id
                    );
                } else {
                    $enrollment->update([
                        'status' => 'confirmed',
                        'paid_at' => now(),
                        'metadata' => array_merge(
                            is_array($enrollment->metadata) ? $enrollment->metadata : [],
                            [
                                'validation_mode' => 'moneroo_webhook',
                                'validated_at' => now()->toISOString(),
                            ]
                        ),
                    ]);

                    ActivityLogService::log(
                        'Inscription validée',
                        "Le paiement Moneroo a validé l'inscription de {$enrollment->full_name}.",
                        $enrollment,
                        $payment->user_id
                    );

                    // Notify Apprenant (Registration Thanks)
                    if ($enrollment->email) {
                        try {
                            Mail::to($enrollment->email)->send(new RegistrationThanks($enrollment->user ?? new \stdClass(['first_name' => $enrollment->first_name, 'last_name' => $enrollment->last_name, 'email' => $enrollment->email])));
                        } catch (\Exception $e) {
                            Log::error('Failed to send registration thanks: ' . $e->getMessage());
                        }
                    }

                    // Notify Staff of New Apprenant Enrollment
                    try {
                        $staffEmail = SiteSetting::get('email', 'contact@madibabc.com');
                        Mail::to($staffEmail)->send(new NewApprenant($enrollment));
                    } catch (\Exception $e) {
                        Log::error('Failed to send new apprenant notification: ' . $e->getMessage());
                    }
                }
            }
        }

        // Send Payment Receipt to User
        $receiptUser = method_exists($payment, 'deriveUser') ? $payment->deriveUser() : $payment->user;
        $userEmail = $receiptUser?->email
            ?? ($payment->payable->email ?? null);
        if ($userEmail) {
            try {
                Mail::to($userEmail)->send(new PaymentReceipt($payment));
            } catch (\Exception $e) {
                Log::error('Failed to send payment receipt: ' . $e->getMessage());
            }
        }

        // Send Payment Received Notification to Staff
        try {
            $staffEmail = SiteSetting::get('email', 'contact@madibabc.com');
            Mail::to($staffEmail)->send(new PaymentReceived($payment));
        } catch (\Exception $e) {
            Log::error('Failed to send payment received notification: ' . $e->getMessage());
        }

        ActivityLogService::log(
            'Paiement validé',
            "Le paiement {$payment->reference} a été validé pour un montant de {$payment->amount} {$payment->currency}.",
            $payment,
            $payment->user_id
        );

        Log::info('Payment success processed', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'payable_type' => $payment->payable_type,
        ]);
    }

    /**
     * Obtenir les méthodes de paiement disponibles
     */
    public function getAvailableMethods(): array
    {
        return [
            [
                'id' => 'orange_money',
                'name' => 'Orange Money',
                'icon' => 'orange-money',
                'description' => 'Paiement via Orange Money',
                'available' => true,
            ],
            [
                'id' => 'mtn_momo',
                'name' => 'MTN Mobile Money',
                'icon' => 'mtn-momo',
                'description' => 'Paiement via MTN MoMo',
                'available' => true,
            ],
            [
                'id' => 'wave',
                'name' => 'Wave',
                'icon' => 'wave',
                'description' => 'Paiement via Wave',
                'available' => true,
            ],
            [
                'id' => 'card',
                'name' => 'Carte Bancaire',
                'icon' => 'credit-card',
                'description' => 'Visa, Mastercard',
                'available' => false, // Désactivé par défaut
            ],
        ];
    }
}
