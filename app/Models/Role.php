<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_staff',
        'can_self_register',
    ];

    protected $casts = [
        'is_staff' => 'boolean',
        'can_self_register' => 'boolean',
    ];

    /**
     * Get users with this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['is_primary', 'metadata', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Scope for staff roles
     */
    public function scopeStaff($query)
    {
        return $query->where('is_staff', true);
    }

    /**
     * Scope for self-registrable roles
     */
    public function scopeSelfRegistrable($query)
    {
        return $query->where('can_self_register', true);
    }

    /**
     * Find role by slug
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Get all role slugs
     */
    public static function allSlugs(): array
    {
        return static::pluck('slug')->toArray();
    }
}
