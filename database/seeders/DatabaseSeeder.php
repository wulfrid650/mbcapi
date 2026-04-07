<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * Ordre d'exécution important pour respecter les dépendances.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱 Démarrage du seeding de la base de données MBC...');
        $this->command->info('');

        // 1. D'abord les rôles (si pas déjà créés)
        $this->call(RolesSeeder::class);

        // 2. Les utilisateurs (dépend des rôles)
        $this->call(UsersSeeder::class);

        // 3. Les paramètres du site
        $this->call(SiteSettingsSeeder::class);

        // 4. Les services
        $this->call(ServicesSeeder::class);

        // 5. Les formations (dépend des users pour le formateur)
        $this->call(FormationsSeeder::class);

        // 6. Les témoignages
        $this->call(TestimonialsSeeder::class);

        // 7. Le portfolio
        $this->call(PortfolioSeeder::class);

        // 8. Les pages légales (CGU, CGV, Politique de confidentialité)
        $this->call(LegalPagesSeeder::class);

        // 9. Données Chef de Chantier (Équipes, Avancements, Rapports, Messages) - Ajouté le 13/01/2026
        $this->call(ChefChantierSeeder::class);

        $this->command->info('');
        $this->command->info('✅ Seeding terminé avec succès !');
        $this->command->info('');
        $this->command->info('📝 Comptes créés :');
        $this->command->info('   Admin: admin@madibabc.com / Admin@MBC2026!');
        $this->command->info('   Formateur: formateur@madibabc.com / Formateur@MBC2026!');
        $this->command->info('   Chef chantier: chantier@madibabc.com / Chef@MBC2026!');
        $this->command->info('');
    }
}
