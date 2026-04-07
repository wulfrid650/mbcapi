<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FormationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'formation_id',
        'formateur_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location',
        'max_students',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function formateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(FormationEnrollment::class, 'session_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'formation_enrollments', 'session_id', 'user_id')
            ->withPivot(['status', 'amount_paid', 'payment_complete', 'progression', 'enrolled_at', 'completed_at'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * Nombre d'inscrits confirmés
     */
    public function getEnrolledCountAttribute(): int
    {
        return $this->enrollments()
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();
    }

    /**
     * Places restantes
     */
    public function getAvailableSpotsAttribute(): int
    {
        return max(0, $this->max_students - $this->enrolled_count);
    }

    /**
     * Session complète ?
     */
    public function getIsFullAttribute(): bool
    {
        return $this->available_spots <= 0;
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now()->toDateString())
            ->whereIn('status', ['planned', 'ongoing']);
    }
}
