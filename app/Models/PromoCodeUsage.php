<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracking de l'utilisation des codes promo
 */
class PromoCodeUsage extends Model
{
    protected $fillable = [
        'promo_code_id',
        'user_id',
        'guest_email',
        'payment_id',
        'discount_applied',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Vérifier si un utilisateur a déjà utilisé ce code
     */
    public static function hasUserUsedCode(int $promoCodeId, ?int $userId = null, ?string $email = null): bool
    {
        $query = self::where('promo_code_id', $promoCodeId);

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($email) {
            $query->where('guest_email', $email);
        } else {
            return false;
        }

        return $query->exists();
    }
}
