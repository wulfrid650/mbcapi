<?php

namespace App\Console\Commands;

use App\Services\FormationEnrollmentWindowService;
use Illuminate\Console\Command;

class ExpirePendingFormationEnrollments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-pending-formation-enrollments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Annule les inscriptions en attente dont la fenêtre de paiement a expiré';

    public function handle(FormationEnrollmentWindowService $enrollmentWindowService): int
    {
        $expiredCount = $enrollmentWindowService->expirePendingEnrollments();

        $this->info("Inscriptions expirées traitées: {$expiredCount}");

        return self::SUCCESS;
    }
}
