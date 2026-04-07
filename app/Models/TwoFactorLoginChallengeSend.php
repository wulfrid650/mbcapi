<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorLoginChallengeSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(TwoFactorLoginChallenge::class, 'challenge_id');
    }
}
