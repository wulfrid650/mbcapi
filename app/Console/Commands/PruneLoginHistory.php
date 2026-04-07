<?php

namespace App\Console\Commands;

use App\Models\LoginHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneLoginHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-login-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supprime l\'historique des connexions datant de plus de 90 jours (RGPD / Privacy)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = LoginHistory::where('created_at', '<', now()->subDays(90))->delete();

        $this->info("{$count} entrées d'historique de connexion supprimées.");
        
        if ($count > 0) {
            Log::info("Nettoyage automatique : {$count} entrées d'historique de connexion supprimées (plus de 90 jours).");
        }
    }
}
