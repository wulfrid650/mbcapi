<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TwoFactorLoginChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_token',
        'code_hash',
        'expires_at',
        'verified_at',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(TwoFactorLoginChallengeSend::class, 'challenge_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->verified_at === null
            && $this->consumed_at === null
            && $this->expires_at?->isFuture();
    }
}
