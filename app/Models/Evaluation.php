<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'formation_session_id',
        'title',
        'description',
        'type',
        'max_score',
        'passing_score',
        'date',
        'duration_minutes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'max_score' => 'decimal:2',
        'passing_score' => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(FormationSession::class, 'formation_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(EvaluationResult::class);
    }
}
