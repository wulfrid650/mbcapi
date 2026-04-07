<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder pour les utilisateurs initiaux
 * Crée les utilisateurs de base avec leurs rôles
 */
class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // Admin principal
        // ==========================================
        $admin = User::updateOrCreate(
            ['email' => 'admin@madibabc.com'],
            [
                'name' => 'Administrateur MBC',
                'email' => 'admin@madibabc.com',
                'phone' => '+237692653590',
                'password' => Hash::make('Admin@MBC2026!'),
                'email_verified_at' => now(),
            ]
        );
        $this->assignRole($admin, 'admin');

        // ==========================================
        // Formateur principal
        // ==========================================
        $formateur = User::updateOrCreate(
            ['email' => 'formateur@madibabc.com'],
            [
                'name' => 'Jean-Claude TAGNE',
                'email' => 'formateur@madibabc.com',
                'phone' => '+237676949103',
                'password' => Hash::make('Formateur@MBC2026!'),
                'email_verified_at' => now(),
            ]
        );
        $this->assignRole($formateur, 'formateur');

        // ==========================================
        // Chef de chantier
        // ==========================================
        $chefChantier = User::updateOrCreate(
            ['email' => 'chantier@madibabc.com'],
            [
                'name' => 'Paul MBARGA',
                'email' => 'chantier@madibabc.com',
                'phone' => '+237655443322',
                'password' => Hash::make('Chef@MBC2026!'),
                'email_verified_at' => now(),
            ]
        );
        $this->assignRole($chefChantier, 'chef-chantier');

        // ==========================================
        // Apprenants de test
        // ==========================================
        $apprenants = [
            [
                'name' => 'Sylvie NDOM',
                'email' => 'sylvie.ndom@gmail.com',
                'phone' => '+237699112233',
            ],
            [
                'name' => 'Eric FOTSO',
                'email' => 'eric.fotso@gmail.com',
                'phone' => '+237677889900',
            ],
            [
                'name' => 'Christelle ATEMKENG',
                'email' => 'christelle.a@gmail.com',
                'phone' => '+237655667788',
            ],
        ];

        foreach ($apprenants as $data) {
            $apprenant = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('Apprenant@2026!'),
                    'email_verified_at' => now(),
                ]
            );
            $this->assignRole($apprenant, 'apprenant');
        }

        // ==========================================
        // Clients de test
        // ==========================================
        $clients = [
            [
                'name' => 'Jean-Pierre MBOUOMBOUO',
                'email' => 'jp.mbouombouo@gmail.com',
                'phone' => '+237699445566',
                'company_name' => null,
            ],
            [
                'name' => 'Marie KOUAM',
                'email' => 'marie.kouam@entreprise.cm',
                'phone' => '+237677223344',
                'company_name' => 'Kouam Investments',
            ],
        ];

        foreach ($clients as $data) {
            $client = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'company_name' => $data['company_name'] ?? null,
                    'password' => Hash::make('Client@2026!'),
                    'email_verified_at' => now(),
                ]
            );
            $this->assignRole($client, 'client');
        }

        $this->command->info('✓ Users seeded successfully (1 admin, 1 formateur, 1 chef chantier, 3 apprenants, 2 clients)');
    }

    /**
     * Assigner un rôle à un utilisateur
     */
    private function assignRole(User $user, string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        
        if ($role && !$user->roles()->where('role_id', $role->id)->exists()) {
            $user->roles()->attach($role->id);
        }
    }
}
