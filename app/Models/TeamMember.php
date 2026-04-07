<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'position',
        'department',
        'photo',
        'email',
        'phone',
        'bio',
        'social_links',
        'display_order',
        'is_active',
        'show_on_website',
        'user_id',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_active' => 'boolean',
        'show_on_website' => 'boolean',
        'display_order' => 'integer',
    ];

    protected $appends = ['full_name', 'initials', 'photo_url'];

    /**
     * Get linked user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get initials for avatar
     */
    public function getInitialsAttribute(): string
    {
        $first = mb_substr($this->first_name, 0, 1);
        $last = mb_substr($this->last_name, 0, 1);
        return strtoupper($first . $last);
    }

    /**
     * Get full photo URL
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }
        
        if (str_starts_with($this->photo, 'http')) {
            return $this->photo;
        }
        
        return Storage::disk('public')->url($this->photo);
    }

    /**
     * Scope for active members
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for website display
     */
    public function scopeForWebsite($query)
    {
        return $query->where('show_on_website', true)
                     ->where('is_active', true)
                     ->orderBy('display_order');
    }

    /**
     * Scope ordered
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('first_name');
    }
}
