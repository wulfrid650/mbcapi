<?php

namespace Database\Seeders;

use App\Models\ConstructionTeam;
use App\Models\Message;
use App\Models\PortfolioProject;
use App\Models\ProjectReport;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChefChantierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Assurer qu'on a des projets
        if (PortfolioProject::count() === 0) {
            $this->call(PortfolioSeeder::class);
        }

        $projects = PortfolioProject::take(4)->get();
        if ($projects->isEmpty())
            return;

        // 2. Créer des équipes
        $teams = [
            [
                'name' => 'Équipe Maçonnerie A',
                'leader_name' => 'Jean Nkomo',
                'members_count' => 8,
                'phone' => '+237 670 123 456',
                'email' => 'jean.nkomo@mbc.cm',
                'specialization' => 'Maçonnerie',
                'projects_count' => 2,
                'status' => 'Actif',
            ],
            [
                'name' => 'Équipe Électricité B',
                'leader_name' => 'Marie Tansi',
                'members_count' => 6,
                'phone' => '+237 671 234 567',
                'email' => 'marie.tansi@mbc.cm',
                'specialization' => 'Électricité',
                'projects_count' => 2,
                'status' => 'Actif',
            ],
            [
                'name' => 'Équipe Plomberie C',
                'leader_name' => 'Pierre Mvondo',
                'members_count' => 5,
                'phone' => '+237 672 345 678',
                'email' => 'pierre.mvondo@mbc.cm',
                'specialization' => 'Plomberie',
                'projects_count' => 1,
                'status' => 'Actif',
            ],
            [
                'name' => 'Équipe Finition D',
                'leader_name' => 'Sophie Okoye',
                'members_count' => 7,
                'phone' => '+237 673 456 789',
                'email' => 'sophie.okoye@mbc.cm',
                'specialization' => 'Peinture & Finition',
                'projects_count' => 1,
                'status' => 'En pause',
            ],
        ];

        foreach ($teams as $team) {
            ConstructionTeam::firstOrCreate(['name' => $team['name']], $team);
        }

        // 3. Créer des mises à jour (Avancements)
        $updates = [
            [
                'title' => 'Étage 3 complété',
                'description' => 'Les travaux de maçonnerie sur l\'étage 3 sont terminés',
                'date' => '2026-01-09',
                'author_name' => 'Vous',
                'images_count' => 3,
                'status' => 'Publié',
                'project_index' => 0,
            ],
            [
                'title' => 'Installation électrique en cours',
                'description' => 'Les câblages électriques sont en cours sur les étages 1 et 2',
                'date' => '2026-01-08',
                'author_name' => 'Chef équipe 1',
                'images_count' => 5,
                'status' => 'Publié',
                'project_index' => 1,
            ],
            [
                'title' => 'Excavation commencée',
                'description' => 'Début de l\'excavation pour les fondations',
                'date' => '2026-01-07',
                'author_name' => 'Vous',
                'images_count' => 2,
                'status' => 'Publié',
                'project_index' => 2,
            ],
        ];

        foreach ($updates as $update) {
            if (isset($projects[$update['project_index']])) {
                ProjectUpdate::create([
                    'portfolio_project_id' => $projects[$update['project_index']]->id,
                    'title' => $update['title'],
                    'description' => $update['description'],
                    'date' => $update['date'],
                    'author_name' => $update['author_name'],
                    'images_count' => $update['images_count'],
                    'status' => $update['status'],
                ]);
            }
        }

        // 4. Créer des rapports
        $reports = [
            [
                'title' => 'Rapport d\'avancement - ' . $projects[0]->title,
                'period' => 'Janvier 2026',
                'type' => 'Avancement',
                'author_name' => 'Vous',
                'date' => '2026-01-09',
                'pages_count' => 8,
                'status' => 'Complété',
                'project_index' => 0,
            ],
            [
                'title' => 'Rapport de sécurité - ' . ($projects[2]->title ?? 'Chantier C'),
                'period' => 'Janvier 2026',
                'type' => 'Sécurité',
                'author_name' => 'Chef équipe 2',
                'date' => '2026-01-08',
                'pages_count' => 5,
                'status' => 'Complété',
                'project_index' => 2,
            ],
        ];

        foreach ($reports as $report) {
            if (isset($projects[$report['project_index']])) {
                ProjectReport::create([
                    'portfolio_project_id' => $projects[$report['project_index']]->id,
                    'title' => $report['title'],
                    'period' => $report['period'],
                    'type' => $report['type'],
                    'author_name' => $report['author_name'],
                    'date' => $report['date'],
                    'pages_count' => $report['pages_count'],
                    'status' => $report['status'],
                ]);
            }
        }

        // 5. Créer des messages
        // Trouver ou créer un user Chef Chantier pour simuler "Vous"
        $chefChantier = User::whereHas('roles', function ($q) {
            $q->where('slug', 'chef_chantier');
        })->first();

        // Si pas de chef chantier, on en crée un ou usage générique

        $messages = [
            [
                'sender_name' => 'Chef équipe 1',
                'subject' => 'Problème d\'approvisionnement',
                'content' => 'Nous manquons de ciment pour l\'étage 3. Pouvez-vous vérifier le stock?',
                'date' => now()->subHours(2),
                'type' => 'received',
                'project_index' => 0,
            ],
            [
                'sender_name' => 'Bureau d\'études',
                'subject' => 'Modification plans',
                'content' => 'Les plans révisés sont prêts. Voir le rapport joint.',
                'date' => now()->subHours(5),
                'type' => 'received',
                'project_index' => 2,
            ]
        ];

        foreach ($messages as $msg) {
            Message::create([
                'recipient_id' => $chefChantier ? $chefChantier->id : null, // Destiné au chef
                'sender_name' => $msg['sender_name'],
                'subject' => $msg['subject'],
                'content' => $msg['content'],
                'portfolio_project_id' => isset($projects[$msg['project_index']]) ? $projects[$msg['project_index']]->id : null,
                'created_at' => $msg['date'],
                'type' => $msg['type'],
            ]);
        }
    }
}
