<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Models\Payment;
use App\Models\Formation;
use App\Observers\UserObserver;
use App\Observers\PaymentObserver;
use App\Observers\FormationObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Register model observers for automatic activity logging
        User::observe(UserObserver::class);
        Payment::observe(PaymentObserver::class);
        Formation::observe(FormationObserver::class);
    }
}
