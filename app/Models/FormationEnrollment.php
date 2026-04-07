<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormationEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'formation_id',
        'session_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'message',
        'status',
        'amount_paid',
        'payment_complete',
        'progression',
        'enrolled_at',
        'completed_at',
        'paid_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_complete' => 'boolean',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FormationSession::class, 'session_id');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'formation_enrollment_id');
    }

    public function certificateRequest(): HasOne
    {
        return $this->hasOne(CertificateRequest::class, 'formation_enrollment_id');
    }

    /**
     * Alias de compatibilité pour l'ancien nom de colonne.
     */
    public function getFormationSessionIdAttribute(): ?int
    {
        return $this->session_id;
    }

    /**
     * Alias de compatibilité pour l'ancien nom de colonne.
     */
    public function setFormationSessionIdAttribute($value): void
    {
        $this->attributes['session_id'] = $value;
    }

    /**
     * Nom complet du participant
     */
    public function getFullNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Email du participant
     */
    public function getParticipantEmailAttribute(): string
    {
        return $this->email ?? $this->user?->email ?? '';
    }

    /**
     * Scope pour inscriptions en attente de paiement
     */
    public function scopePendingPayment($query)
    {
        return $query->where('status', 'pending_payment');
    }

    /**
     * Scope pour inscriptions en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope pour inscriptions confirmées
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}
