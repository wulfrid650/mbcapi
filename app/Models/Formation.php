<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Formation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'objectives',
        'prerequisites',
        'program',
        'duration_hours',
        'duration_days',
        'price',
        'registration_fees', // Frais d'inscription
        'inscription_fee',
        'level',
        'category',
        'cover_image',
        'max_students',
        'is_active',
        'is_featured',
        'display_order',
        'formateur_id',
    ];

    protected $casts = [
        'objectives' => 'array',
        'prerequisites' => 'array',
        'program' => 'array',
        'price' => 'decimal:2',
        'registration_fees' => 'decimal:2',
        'inscription_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($formation) {
            if (empty($formation->slug)) {
                $formation->slug = Str::slug($formation->title);
            }
        });
    }

    public function formateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FormationSession::class);
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(FormationSession::class)
            ->whereIn('status', ['planned', 'ongoing']);
    }

    /**
     * Scope pour formations actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour formations mises en avant
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope pour trier par ordre d'affichage
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('title');
    }

    /**
     * Obtenir le niveau formaté
     */
    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'debutant' => 'Débutant',
            'intermediaire' => 'Intermédiaire',
            'avance' => 'Avancé',
            default => $this->level,
        };
    }
}
