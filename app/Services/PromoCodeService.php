<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Service pour la gestion des codes promo
 */
class PromoCodeService
{
    /**
     * Valider et appliquer un code promo
     */
    public function validateAndApply(
        string $code,
        float $amount,
        ?int $formationId = null,
        ?int $userId = null,
        ?string $guestEmail = null
    ): array {
        $promoCode = PromoCode::where('code', strtoupper(trim($code)))->first();

        if (!$promoCode) {
            return [
                'valid' => false,
                'error' => 'Code promo introuvable',
            ];
        }

        // Vérifier la validité générale
        if (!$promoCode->isValid($formationId)) {
            return [
                'valid' => false,
                'error' => $this->getInvalidReason($promoCode, $formationId),
            ];
        }

        // Vérifier l'utilisation unique par utilisateur
        if ($userId || $guestEmail) {
            if (PromoCodeUsage::hasUserUsedCode($promoCode->id, $userId, $guestEmail)) {
                return [
                    'valid' => false,
                    'error' => 'Vous avez déjà utilisé ce code promo',
                ];
            }
        }

        // Calculer la réduction
        $discount = $promoCode->calculateDiscount($amount);
        $newAmount = max(0, $amount - $discount);

        return [
            'valid' => true,
            'promo_code' => $promoCode,
            'original_amount' => $amount,
            'discount' => $discount,
            'new_amount' => $newAmount,
            'type' => $promoCode->type,
            'value' => $promoCode->value,
            'description' => $this->getDiscountDescription($promoCode),
        ];
    }

    /**
     * Enregistrer l'utilisation d'un code promo
     */
    public function recordUsage(
        PromoCode $promoCode,
        float $discountApplied,
        ?int $userId = null,
        ?string $guestEmail = null,
        ?int $paymentId = null
    ): PromoCodeUsage {
        // Créer l'enregistrement d'utilisation
        $usage = PromoCodeUsage::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $userId,
            'guest_email' => $guestEmail,
            'payment_id' => $paymentId,
            'discount_applied' => $discountApplied,
        ]);

        // Incrémenter le compteur du code promo
        $promoCode->incrementUsage();

        return $usage;
    }

    /**
     * Obtenir la raison d'invalidité du code
     */
    private function getInvalidReason(PromoCode $promoCode, ?int $formationId = null): string
    {
        if (!$promoCode->is_active) {
            return 'Ce code promo est désactivé';
        }

        $now = now();

        if ($promoCode->valid_from && $now->isBefore($promoCode->valid_from)) {
            return 'Ce code promo n\'est pas encore actif';
        }

        if ($promoCode->valid_until && $now->isAfter($promoCode->valid_until)) {
            return 'Ce code promo a expiré';
        }

        if ($promoCode->max_uses && $promoCode->used_count >= $promoCode->max_uses) {
            return 'Ce code promo a atteint sa limite d\'utilisation';
        }

        if ($formationId && $promoCode->formations && !in_array($formationId, $promoCode->formations)) {
            return 'Ce code promo n\'est pas applicable à cette formation';
        }

        return 'Code promo invalide';
    }

    /**
     * Obtenir la description de la réduction
     */
    private function getDiscountDescription(PromoCode $promoCode): string
    {
        if ($promoCode->type === 'percentage') {
            return sprintf('Réduction de %d%%', (int) $promoCode->value);
        }

        return sprintf('Réduction de %s FCFA', number_format($promoCode->value, 0, ',', ' '));
    }

    /**
     * Appliquer le code promo à un paiement
     */
    public function applyToPayment(
        Payment $payment,
        string $code,
        ?int $formationId = null
    ): array {
        $userId = $payment->user_id;
        $guestEmail = $payment->payer_email;

        $validation = $this->validateAndApply(
            $code,
            (float) $payment->original_amount ?? (float) $payment->amount,
            $formationId,
            $userId,
            $guestEmail
        );

        if (!$validation['valid']) {
            return $validation;
        }

        // Mettre à jour le paiement
        $payment->update([
            'original_amount' => $validation['original_amount'],
            'amount' => $validation['new_amount'],
            'discount_amount' => $validation['discount'],
            'promo_code_id' => $validation['promo_code']->id,
            'metadata' => array_merge($payment->metadata ?? [], [
                'promo_code' => $validation['promo_code']->code,
                'promo_applied_at' => now()->toISOString(),
            ]),
        ]);

        // Enregistrer l'utilisation
        $this->recordUsage(
            $validation['promo_code'],
            $validation['discount'],
            $userId,
            $guestEmail,
            $payment->id
        );

        return $validation;
    }

    /**
     * Obtenir les statistiques d'un code promo
     */
    public function getStatistics(PromoCode $promoCode): array
    {
        $usages = PromoCodeUsage::where('promo_code_id', $promoCode->id)
            ->with(['user:id,name,email', 'payment:id,reference,amount'])
            ->get();

        return [
            'total_uses' => $usages->count(),
            'total_discount_given' => $usages->sum('discount_applied'),
            'unique_users' => $usages->whereNotNull('user_id')->pluck('user_id')->unique()->count(),
            'guest_uses' => $usages->whereNull('user_id')->count(),
            'recent_usages' => $usages->sortByDesc('created_at')->take(10)->values(),
        ];
    }
}
