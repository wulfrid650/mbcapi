<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'formation_enrollment_id',
        'requested_by',
        'status',
        'requested_at',
        'decision_by',
        'decision_at',
        'decision_notes',
        'invalidated_by',
        'invalidated_at',
        'invalidation_reason',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decision_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(FormationEnrollment::class, 'formation_enrollment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }

    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved'
            && $this->invalidated_at === null;
    }
}
