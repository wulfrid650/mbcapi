<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'content',
        'meta_title',
        'meta_description',
        'last_updated',
        'is_active',
    ];

    protected $casts = [
        'last_updated' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Récupérer une page légale par son slug
     */
    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Récupérer les CGU
     */
    public static function getCgu(): ?self
    {
        return self::findBySlug('cgu');
    }

    /**
     * Récupérer les CGV
     */
    public static function getCgv(): ?self
    {
        return self::findBySlug('cgv');
    }

    /**
     * Récupérer la politique de confidentialité
     */
    public static function getPrivacyPolicy(): ?self
    {
        return self::findBySlug('privacy-policy');
    }

    /**
     * Récupérer les mentions légales
     */
    public static function getMentionsLegales(): ?self
    {
        return self::findBySlug('mentions-legales');
    }
}
