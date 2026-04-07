<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'formation_enrollment_id',
        'reference',
        'pdf_path',
        'issued_at',
        'generated_by',
        'revoked_at',
        'revoked_reason',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(FormationEnrollment::class, 'formation_enrollment_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isValid(): bool
    {
        return $this->issued_at !== null
            && $this->revoked_at === null
            && $this->enrollment?->status === 'completed';
    }
}
