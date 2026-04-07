<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasPublicId;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'name',
        'email',
        'password',
        'role',
        'active_role_id',
        'phone',
        'formation',
        'project_id',
        'is_active',
        'last_login_at',
        // Employee fields
        'employee_id',
        'address',
        'emergency_contact',
        'emergency_phone',
        'invitation_token',
        'invitation_expires_at',
        'profile_completed',
        // Client fields
        'company_name',
        'company_address',
        'project_type',
        'project_description',
        // Apprenant fields
        'formation_id',
        'enrollment_date',
        // Formateur fields
        'speciality',
        'bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'public_id',
        'active_role_id',
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'profile_completed' => 'boolean',
            'last_login_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'enrollment_date' => 'date',
        ];
    }

    public function toExternalArray(): array
    {
        $data = $this->toArray();
        $data['id'] = $this->getPublicId();

        if ($this->relationLoaded('roles')) {
            $data['roles'] = $this->roles->map(function ($role) {
                return [
                    'slug' => $role->slug,
                    'name' => $role->name,
                    'is_staff' => (bool) $role->is_staff,
                    'is_primary' => (bool) ($role->pivot?->is_primary ?? false),
                ];
            })->values()->all();
        }

        if ($this->relationLoaded('activeRole') && $this->activeRole) {
            $data['active_role'] = [
                'slug' => $this->activeRole->slug,
                'name' => $this->activeRole->name,
            ];
        }

        return $data;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is secretaire
     */
    public function isSecretaire(): bool
    {
        return $this->role === 'secretaire';
    }

    /**
     * Check if user is apprenant
     */
    public function isApprenant(): bool
    {
        return $this->role === 'apprenant';
    }

    /**
     * Check if user is client
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Check if user is chef de chantier
     */
    public function isChefChantier(): bool
    {
        return $this->role === 'chef_chantier';
    }

    /**
     * Check if user is formateur
     */
    public function isFormateur(): bool
    {
        return $this->role === 'formateur';
    }

    /**
     * Check if user has staff access (admin or secretaire)
     */
    public function hasStaffAccess(): bool
    {
        return in_array($this->role, ['admin', 'secretaire']);
    }

    /**
     * Check if user can manage projects (admin, secretaire, chef_chantier)
     */
    public function canManageProjects(): bool
    {
        return in_array($this->getActiveRoleSlug(), ['admin', 'secretaire', 'chef_chantier']);
    }

    /**
     * Available roles
     */
    public const ROLES = [
        'admin' => 'Administrateur',
        'secretaire' => 'Secrétaire',
        'formateur' => 'Formateur',
        'apprenant' => 'Apprenant',
        'client' => 'Client',
        'chef_chantier' => 'Chef de chantier',
    ];

    /**
     * Roles that can self-register
     */
    public const SELF_REGISTER_ROLES = ['apprenant', 'client'];

    /**
     * Roles that must be created by admin
     */
    public const ADMIN_CREATED_ROLES = ['admin', 'secretaire', 'chef_chantier', 'formateur'];

    /**
     * Check if user is an employee (admin-created role)
     */
    public function isEmployee(): bool
    {
        return in_array($this->role, self::ADMIN_CREATED_ROLES);
    }

    /**
     * Check if invitation is valid
     */
    public function hasValidInvitation(): bool
    {
        return $this->invitation_token !== null
            && $this->invitation_expires_at !== null
            && $this->invitation_expires_at->isFuture();
    }

    /**
     * Generate invitation token
     */
    public function generateInvitationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->invitation_token = $token;
        $this->invitation_expires_at = now()->addDays(7);
        $this->save();

        return $token;
    }

    /**
     * Clear invitation token
     */
    public function clearInvitationToken(): void
    {
        $this->invitation_token = null;
        $this->invitation_expires_at = null;
        $this->save();
    }

    // ==================== MULTI-ROLE SYSTEM ====================

    /**
     * Get all roles for this user
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['is_primary', 'metadata', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Get the active role relationship
     */
    public function activeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'active_role_id');
    }

    /**
     * Get active role slug (with fallback to legacy role field)
     */
    public function getActiveRoleSlug(): string
    {
        if ($this->activeRole) {
            return $this->activeRole->slug;
        }
        return $this->role ?? 'client';
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleSlug): bool
    {
        // Check in roles relationship first
        if ($this->roles()->where('slug', $roleSlug)->exists()) {
            return true;
        }
        // Fallback to legacy role field
        return $this->role === $roleSlug;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        foreach ($roleSlugs as $slug) {
            if ($this->hasRole($slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a role to the user
     */
    public function addRole(string $roleSlug, bool $isPrimary = false, array $metadata = []): bool
    {
        $role = Role::findBySlug($roleSlug);
        if (!$role) {
            return false;
        }

        // Check if user already has this role
        if ($this->roles()->where('role_id', $role->id)->exists()) {
            return true;
        }

        // If this is the first role or marked as primary, set as primary
        if ($isPrimary || $this->roles()->count() === 0) {
            // Remove primary from other roles
            $this->roles()->updateExistingPivot(
                $this->roles()->pluck('roles.id')->toArray(),
                ['is_primary' => false]
            );
        }

        $this->roles()->attach($role->id, [
            'is_primary' => $isPrimary || $this->roles()->count() === 0,
            'metadata' => json_encode($metadata),
            'assigned_at' => now(),
        ]);

        // Update legacy role field and active_role_id if primary
        if ($isPrimary || !$this->active_role_id) {
            $this->update([
                'role' => $role->slug,
                'active_role_id' => $role->id,
            ]);
        }

        return true;
    }

    /**
     * Remove a role from the user
     */
    public function removeRole(string $roleSlug): bool
    {
        $role = Role::findBySlug($roleSlug);
        if (!$role) {
            return false;
        }

        $this->roles()->detach($role->id);

        // If this was the active role, switch to primary or first available
        if ($this->active_role_id === $role->id) {
            $primaryRole = $this->roles()->wherePivot('is_primary', true)->first();
            $newRole = $primaryRole ?? $this->roles()->first();

            if ($newRole) {
                $this->update([
                    'role' => $newRole->slug,
                    'active_role_id' => $newRole->id,
                ]);
            } else {
                $this->update([
                    'role' => null,
                    'active_role_id' => null,
                ]);
            }
        }

        return true;
    }

    /**
     * Switch active role
     */
    public function switchRole(string $roleSlug): bool
    {
        // Check if user has this role
        if (!$this->hasRole($roleSlug)) {
            return false;
        }

        $role = Role::findBySlug($roleSlug);
        if (!$role) {
            return false;
        }

        $this->update([
            'role' => $role->slug,
            'active_role_id' => $role->id,
        ]);

        return true;
    }

    /**
     * Get all role slugs for this user
     */
    public function getRoleSlugs(): array
    {
        $roles = $this->roles()->pluck('slug')->toArray();

        // Include legacy role if not in roles
        if ($this->role && !in_array($this->role, $roles)) {
            $roles[] = $this->role;
        }

        return array_unique($roles);
    }

    /**
     * Get portfolio projects for this user (if client)
     */
    public function portfolioProjects()
    {
        return $this->hasMany(PortfolioProject::class, 'client_id');
    }

    /**
     * Get formation enrollments for this user (if apprenant)
     */
    public function enrollments()
    {
        return $this->hasMany(FormationEnrollment::class, 'user_id');
    }

    /**
     * 2FA login challenges for this user
     */
    public function twoFactorLoginChallenges(): HasMany
    {
        return $this->hasMany(TwoFactorLoginChallenge::class);
    }

    /**
     * Get user data for API response with roles
     */
    public function toArrayWithRoles(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->getActiveRoleSlug(), // Current active role
            'roles' => $this->roles->map(fn($role) => [
                'slug' => $role->slug,
                'name' => $role->name,
                'is_primary' => $role->pivot->is_primary,
                'is_staff' => $role->is_staff,
            ])->toArray(),
            'active_role' => $this->activeRole ? [
                'slug' => $this->activeRole->slug,
                'name' => $this->activeRole->name,
            ] : null,
            'formation' => $this->formation,
            'project_id' => $this->project_id,
            'speciality' => $this->speciality,
            'is_active' => $this->is_active,
        ];
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
