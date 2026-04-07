<?php

namespace App\Observers;

use App\Models\Formation;
use App\Services\ActivityLogService;

class FormationObserver
{
    /**
     * Handle the Formation "created" event.
     */
    public function created(Formation $formation): void
    {
        ActivityLogService::logFormationCreated($formation, auth()->user());
    }

    /**
     * Handle the Formation "updated" event.
     */
    public function updated(Formation $formation): void
    {
        // Only log significant updates
        if ($formation->isDirty(['title', 'description', 'price', 'status'])) {
            ActivityLogService::logFormationUpdated($formation, auth()->user());
        }
    }
}
