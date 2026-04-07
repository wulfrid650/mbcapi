<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

/**
 * Seeder pour les rôles
 * Les rôles sont normalement créés par la migration, ce seeder s'assure qu'ils existent
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'slug' => 'admin',
                'name' => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités',
                'is_staff' => true,
                'can_self_register' => false,
            ],
            [
                'slug' => 'secretaire',
                'name' => 'Secrétaire',
                'description' => 'Gestion administrative et suivi des dossiers',
                'is_staff' => true,
                'can_self_register' => false,
            ],
            [
                'slug' => 'formateur',
                'name' => 'Formateur',
                'description' => 'Gestion des formations et des apprenants',
                'is_staff' => true,
                'can_self_register' => false,
            ],
            [
                'slug' => 'chef-chantier',
                'name' => 'Chef de chantier',
                'description' => 'Supervision des chantiers et équipes',
                'is_staff' => true,
                'can_self_register' => false,
            ],
            [
                'slug' => 'apprenant',
                'name' => 'Apprenant',
                'description' => 'Accès aux formations et contenus pédagogiques',
                'is_staff' => false,
                'can_self_register' => true,
            ],
            [
                'slug' => 'client',
                'name' => 'Client',
                'description' => 'Suivi de projet et communication',
                'is_staff' => false,
                'can_self_register' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }

        $this->command->info('✓ Roles seeded successfully (6 rôles)');
    }
}
