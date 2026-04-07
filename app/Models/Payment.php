<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory, HasPublicId;

    protected $hidden = [
        'public_id',
    ];

    /**
     * Motifs de paiement disponibles
     */
    const PURPOSES = [
        'formation_payment' => 'Paiement inscription formation',
        'project_devis' => 'Paiement devis projet',
        'formation_installment' => 'Paiement tranche formation',
        'service_payment' => 'Paiement service',
        'project_payment' => 'Paiement projet/chantier',
        'project_advance' => 'Avance projet/chantier',
        'project_installment' => 'Tranche paiement projet',
        'consultation' => 'Frais de consultation',
        'other' => 'Autre paiement',
    ];

    protected $fillable = [
        'public_id',
        'reference',
        'user_id',
        'payable_type',
        'payable_id',
        'amount',
        'original_amount',
        'discount_amount',
        'currency',
        'method',
        'status',
        'transaction_id',
        'description',
        'purpose',
        'purpose_detail',
        'receipt_number',
        'receipt_generated_at',
        'receipt_path',
        'promo_code_id',
        'payer_name',
        'payer_email',
        'payer_phone',
        'metadata',
        'validated_at',
        'validated_by',
        'is_manual',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'metadata' => 'array',
        'validated_at' => 'datetime',
        'receipt_generated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function toExternalArray(): array
    {
        $data = $this->toArray();
        $data['id'] = $this->getPublicId();
        unset($data['user_id'], $data['validated_by']);

        if (isset($data['user']) && $this->relationLoaded('user') && $this->user) {
            $data['user']['id'] = $this->user->getPublicId();
        }

        if (isset($data['validated_by_user']) && $this->relationLoaded('validatedByUser') && $this->validatedByUser) {
            $data['validated_by'] = $this->validatedByUser->getPublicId();
            $data['validated_by_user']['id'] = $this->validatedByUser->getPublicId();
        }

        if (isset($data['payable']) && $this->relationLoaded('payable') && $this->payable instanceof User) {
            $data['payable']['id'] = $this->payable->getPublicId();
        }

        return $data;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->reference)) {
                $payment->reference = static::generateReference();
            }
        });
    }

    /**
     * Générer une référence unique
     */
    public static function generateReference(): string
    {
        $year = date('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('PAY-%s-%06d', $year, $count);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Relation avec le code promo utilisé
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * Obtenir le label de la méthode de paiement
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'orange_money' => 'Orange Money',
            'mtn_momo' => 'MTN Mobile Money',
            'carte_bancaire' => 'Carte Bancaire',
            'especes' => 'Espèces',
            'cash' => 'Espèces',
            'virement' => 'Virement Bancaire',
            'bank_transfer' => 'Virement Bancaire',
            'check' => 'Chèque',
            default => $this->method ?? 'Non spécifié',
        };
    }

    /**
     * Obtenir le label du statut
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'completed' => 'Validé',
            'failed' => 'Échoué',
            'refunded' => 'Remboursé',
            default => $this->status,
        };
    }

    /**
     * Obtenir le label du motif
     */
    public function getPurposeLabelAttribute(): string
    {
        return self::PURPOSES[$this->purpose] ?? $this->purpose ?? 'Paiement';
    }

    /**
     * Vérifier si un reçu a été généré
     */
    public function hasReceipt(): bool
    {
        return !empty($this->receipt_number) && !empty($this->receipt_path);
    }

    /**
     * Vérifier si le paiement a une réduction
     */
    public function hasDiscount(): bool
    {
        return $this->discount_amount && $this->discount_amount > 0;
    }

    /**
     * Obtenir le nom du payeur
     */
    public function getPayerDisplayNameAttribute(): string
    {
        $displayUser = $this->deriveUser();

        return $this->payer_name
            ?? $displayUser?->name
            ?? 'Client';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeWithReceipt($query)
    {
        return $query->whereNotNull('receipt_number');
    }

    /**
     * Resolver commun pour obtenir l'utilisateur à afficher
     */
    public function deriveUser(bool $attachRelation = true): ?User
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : $this->user;
        if ($user instanceof User) {
            return $user;
        }

        $payable = $this->relationLoaded('payable') ? $this->getRelation('payable') : $this->payable;

        if ($payable instanceof FormationEnrollment) {
            if ($payable->relationLoaded('user') && $payable->user) {
                if ($attachRelation) {
                    $this->setRelation('user', $payable->user);
                }
                return $payable->user;
            }

            $virtualUser = new User([
                'name' => $payable->full_name,
                'email' => $payable->participant_email ?: ($payable->user?->email ?? null),
                'phone' => $payable->phone ?? $payable->user?->phone,
            ]);
            $virtualUser->exists = false;

            if ($attachRelation) {
                $this->setRelation('user', $virtualUser);
            }

            return $virtualUser;
        }

        if ($payable instanceof User) {
            if ($attachRelation) {
                $this->setRelation('user', $payable);
            }
            return $payable;
        }

        $metadata = $this->metadata ?? [];
        $fullNameFromMetadata = $metadata['participant_name']
            ?? $metadata['customer_full_name']
            ?? trim(($metadata['customer_first_name'] ?? '') . ' ' . ($metadata['customer_last_name'] ?? ''))
            ?: null;

        if ($this->payer_name || $fullNameFromMetadata || ($metadata['customer_email'] ?? null) || ($metadata['customer_phone'] ?? null)) {
            $virtualUser = new User([
                'name' => $this->payer_name
                    ?? $fullNameFromMetadata
                    ?? 'Client non identifié',
                'email' => $this->payer_email
                    ?? $metadata['participant_email']
                    ?? $metadata['customer_email']
                    ?? null,
                'phone' => $this->payer_phone
                    ?? $metadata['participant_phone']
                    ?? $metadata['customer_phone']
                    ?? null,
            ]);
            $virtualUser->exists = false;

            if ($attachRelation) {
                $this->setRelation('user', $virtualUser);
            }

            return $virtualUser;
        }

        return null;
    }

    /**
     * Retourne le titre de la formation associée si disponible
     */
    public function deriveFormationTitle(): ?string
    {
        $metadata = $this->metadata ?? [];
        $fallbackTitle = $metadata['formation_title']
            ?? $metadata['formation_name']
            ?? null;

        $payable = $this->relationLoaded('payable') ? $this->getRelation('payable') : $this->payable;

        if ($payable instanceof FormationEnrollment) {
            return $payable->formation?->title ?? $fallbackTitle ?? 'Formation';
        }

        return $fallbackTitle;
    }
}
