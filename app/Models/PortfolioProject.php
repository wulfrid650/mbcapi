<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category',
        'client',
        'location',
        'year',
        'duration',
        'budget',
        'status',
        'cover_image',
        'images',
        'services',
        'challenges',
        'results',
        'is_featured',
        'is_published',
        'created_by',
        'chef_chantier_id',
        'team_ids',
        'progress',
        'metadata',
        'client_id',
        'linked_quote_request_id',
        'client_email',
        'start_date',
        'completion_date',
        'expected_end_date',
    ];

    protected $casts = [
        'images' => 'array',
        'services' => 'array',
        'team_ids' => 'array',
        'metadata' => 'array',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'progress' => 'integer',
        'year' => 'integer',
    ];

    /**
     * Scope pour les projets publiés
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope pour les projets mis en avant
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope par catégorie
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope par statut
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Relation avec les médias
     */
    public function media()
    {
        return $this->hasMany(Media::class, 'portfolio_project_id');
    }

    /**
     * Relation avec le créateur
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation avec le client lié au chantier
     */
    public function clientUser()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function chefChantierUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_chantier_id');
    }

    public function linkedQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class, 'linked_quote_request_id');
    }

    /**
     * Relation avec les témoignages
     */
    public function testimonials()
    {
        return $this->hasMany(Testimonial::class, 'portfolio_project_id');
    }

    /**
     * Relation avec les mises à jour de progression
     */
    public function progressUpdates()
    {
        return $this->hasMany(ProgressUpdate::class, 'portfolio_project_id');
    }
}
