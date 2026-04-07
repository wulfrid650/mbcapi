<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Payment;
use App\Models\Formation;
use App\Models\PortfolioProject;
use Carbon\Carbon;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get sample users
        $admin = User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->first();
        $users = User::limit(5)->get();
        
        if (!$admin) {
            $admin = $users->first();
        }

        // Get sample data
        $payments = Payment::latest()->limit(3)->get();
        $formations = Formation::limit(3)->get();
        $projects = PortfolioProject::limit(3)->get();

        $activities = [];

        // User activities
        foreach ($users->take(3) as $index => $user) {
            $activities[] = [
                'user_id' => $admin ? $admin->id : null,
                'action' => 'Nouvel utilisateur créé',
                'description' => "L'utilisateur {$user->name} a été ajouté au système",
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => Carbon::now()->subDays($index + 1),
                'updated_at' => Carbon::now()->subDays($index + 1),
            ];
        }

        // Payment activities
        foreach ($payments as $index => $payment) {
            $activities[] = [
                'user_id' => $payment->user_id ?? ($admin ? $admin->id : null),
                'action' => $payment->status === 'completed' ? 'Paiement complété' : 'Paiement en attente',
                'description' => "Paiement de {$payment->amount} FCFA - Référence: {$payment->reference}",
                'subject_type' => Payment::class,
                'subject_id' => $payment->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => Carbon::now()->subDays($index),
                'updated_at' => Carbon::now()->subDays($index),
            ];
        }

        // Formation activities
        foreach ($formations as $index => $formation) {
            $activities[] = [
                'user_id' => $admin ? $admin->id : null,
                'action' => 'Formation créée',
                'description' => "Nouvelle formation '{$formation->title}' ajoutée au catalogue",
                'subject_type' => Formation::class,
                'subject_id' => $formation->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => Carbon::now()->subDays($index + 2),
                'updated_at' => Carbon::now()->subDays($index + 2),
            ];
        }

        // Project activities
        foreach ($projects as $index => $project) {
            $activities[] = [
                'user_id' => $admin ? $admin->id : null,
                'action' => 'Projet créé',
                'description' => "Nouveau projet '{$project->title}' ajouté au portfolio",
                'subject_type' => PortfolioProject::class,
                'subject_id' => $project->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => Carbon::now()->subDays($index + 3),
                'updated_at' => Carbon::now()->subDays($index + 3),
            ];
        }

        // System activities
        $activities[] = [
            'user_id' => null,
            'action' => 'Sauvegarde automatique',
            'description' => 'Sauvegarde automatique de la base de données effectuée avec succès',
            'subject_type' => null,
            'subject_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System Cron',
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(2),
        ];

        $activities[] = [
            'user_id' => $admin ? $admin->id : null,
            'action' => 'Configuration mise à jour',
            'description' => 'Paramètres système mis à jour',
            'subject_type' => null,
            'subject_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => Carbon::now()->subDays(1),
            'updated_at' => Carbon::now()->subDays(1),
        ];

        // Insert all activities
        foreach ($activities as $activity) {
            ActivityLog::create($activity);
        }

        $this->command->info('✅ Activity logs seeded successfully!');
    }
}
