<?php

namespace App\Console\Commands;

use App\Models\Formation;
use App\Services\FormationSessionSchedulerService;
use Illuminate\Console\Command;

class GenerateFormationSessions extends Command
{
    protected $signature = 'formations:generate-sessions {formation_id? : ID de la formation à traiter}';
    protected $description = 'Génère les sessions futures pour les formations selon la configuration auto.';

    public function handle(FormationSessionSchedulerService $scheduler): int
    {
        $formationId = $this->argument('formation_id');
        $formation = null;

        if ($formationId) {
            $formation = Formation::find($formationId);

            if (!$formation) {
                $this->error("Formation introuvable pour l'ID {$formationId}.");
                return self::FAILURE;
            }
        }

        $created = $scheduler->ensureUpcomingSessions($formation);

        if ($created === 0) {
            $this->info('Aucune nouvelle session créée. Vérifiez la configuration des paramètres.');
        } else {
            $this->info("{$created} session(s) générée(s) avec succès.");
        }

        return self::SUCCESS;
    }
}
