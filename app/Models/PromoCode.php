<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by',
        'formations',
        'description',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'formations' => 'array',
    ];

    /**
     * Vérifier si le code promo est valide
     */
    public function isValid(?int $formationId = null): bool
    {
        // Vérifier si actif
        if (!$this->is_active) {
            return false;
        }

        // Vérifier les dates
        $now = Carbon::now();
        if ($this->valid_from && $now->isBefore($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && $now->isAfter($this->valid_until)) {
            return false;
        }

        // Vérifier nombre d'utilisations
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        // Vérifier si applicable à cette formation
        if ($formationId && $this->formations && !in_array($formationId, $this->formations)) {
            return false;
        }

        return true;
    }

    /**
     * Calculer le montant de la réduction
     */
    public function calculateDiscount(float $originalPrice): float
    {
        if ($this->type === 'percentage') {
            return $originalPrice * ($this->value / 100);
        }

        return min($this->value, $originalPrice); // Ne pas dépasser le prix
    }

    /**
     * Incrémenter le compteur d'utilisation
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }

    /**
     * Relations
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(FormationEnrollment::class);
    }
}
