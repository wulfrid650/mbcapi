<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        ActivityLogService::logUserCreated($user, auth()->user());
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Only log if significant fields changed
        if ($user->isDirty(['name', 'email', 'phone', 'is_active'])) {
            ActivityLogService::logUserUpdated($user, auth()->user());
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $actor = auth()->user();

        if ($actor && (int) $actor->id === (int) $user->id) {
            ActivityLogService::log(
                'Compte supprimé par son titulaire',
                "Le compte {$user->name} ({$user->email}) a été supprimé à la demande de son titulaire.",
                null,
                null,
                false
            );
            return;
        }

        ActivityLogService::logUserDeleted($user->name, $user->email, auth()->user());
    }
}
