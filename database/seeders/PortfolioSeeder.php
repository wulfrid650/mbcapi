<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PortfolioProject;

/**
 * Seeder pour les projets du portfolio
 * Basé sur les données du frontend (api.ts)
 */
class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            [
                'title' => 'Construction Immeuble Résidentiel',
                'slug' => 'construction-immeuble-residentiel',
                'description' => 'Construction d\'un immeuble résidentiel de 5 étages à Douala. Un projet ambitieux combinant modernité et fonctionnalité pour offrir des logements de qualité.',
                'cover_image' => '/images/portfolio/immeuble-1.jpg',
                'images' => json_encode([
                    '/images/portfolio/immeuble-1.jpg',
                    '/images/portfolio/immeuble-2.jpg',
                    '/images/portfolio/immeuble-3.jpg',
                ]),
                'category' => 'Construction',
                'client' => 'Promoteur privé',
                'location' => 'Douala, Cameroun',
                'year' => 2024,
                'duration' => '18 mois',
                'budget' => '150 000 000 FCFA',
                'status' => 'completed',
                'is_featured' => true,
                'services' => json_encode(['Gros œuvre', 'Second œuvre', 'Finitions']),
                'challenges' => 'Terrain en pente nécessitant des fondations spéciales et un mur de soutènement.',
                'results' => '20 appartements livrés, taux de satisfaction client de 100%.',
            ],
            [
                'title' => 'Rénovation Bureau Commercial',
                'slug' => 'renovation-bureau-commercial',
                'description' => 'Rénovation complète d\'un espace de bureau commercial à Yaoundé. Modernisation des installations et création d\'un open space moderne.',
                'cover_image' => '/images/portfolio/bureau-1.jpg',
                'images' => json_encode([
                    '/images/portfolio/bureau-1.jpg',
                    '/images/portfolio/bureau-2.jpg',
                ]),
                'category' => 'Rénovation',
                'client' => 'Entreprise de services',
                'location' => 'Yaoundé, Cameroun',
                'year' => 2024,
                'duration' => '6 mois',
                'budget' => '25 000 000 FCFA',
                'status' => 'completed',
                'is_featured' => false,
                'services' => json_encode(['Démolition', 'Cloisonnement', 'Électricité', 'Peinture']),
                'challenges' => 'Travaux réalisés en site occupé avec maintien de l\'activité.',
                'results' => 'Augmentation de 30% de la capacité d\'accueil des bureaux.',
            ],
            [
                'title' => 'Villa Contemporaine',
                'slug' => 'villa-contemporaine-bonaberi',
                'description' => 'Construction d\'une villa contemporaine de 400m² avec piscine à Bonabéri. Architecture moderne et finitions haut de gamme.',
                'cover_image' => '/images/portfolio/villa-1.jpg',
                'images' => json_encode([
                    '/images/portfolio/villa-1.jpg',
                    '/images/portfolio/villa-2.jpg',
                    '/images/portfolio/villa-3.jpg',
                    '/images/portfolio/villa-4.jpg',
                ]),
                'category' => 'Construction',
                'client' => 'Particulier',
                'location' => 'Bonabéri, Douala',
                'year' => 2023,
                'duration' => '14 mois',
                'budget' => '180 000 000 FCFA',
                'status' => 'completed',
                'is_featured' => true,
                'services' => json_encode(['Architecture', 'Gros œuvre', 'Piscine', 'Aménagement extérieur']),
                'challenges' => 'Intégration d\'une piscine et d\'un système domotique complet.',
                'results' => 'Villa livrée clé en main avec toutes les fonctionnalités smart home.',
            ],
            [
                'title' => 'Voirie et Assainissement',
                'slug' => 'voirie-assainissement-logpom',
                'description' => 'Aménagement de voirie et réseau d\'assainissement pour un nouveau quartier à Logpom.',
                'cover_image' => '/images/portfolio/voirie-1.jpg',
                'images' => json_encode([
                    '/images/portfolio/voirie-1.jpg',
                    '/images/portfolio/voirie-2.jpg',
                ]),
                'category' => 'Génie Civil',
                'client' => 'Mairie de Douala',
                'location' => 'Logpom, Douala',
                'year' => 2024,
                'duration' => '8 mois',
                'budget' => '75 000 000 FCFA',
                'status' => 'completed',
                'is_featured' => false,
                'services' => json_encode(['VRD', 'Assainissement', 'Caniveaux', 'Revêtement']),
                'challenges' => 'Coordination avec les concessionnaires (eau, électricité).',
                'results' => '2 km de voirie aménagée et système de drainage fonctionnel.',
            ],
            [
                'title' => 'Extension Entrepôt Industriel',
                'slug' => 'extension-entrepot-industriel',
                'description' => 'Extension d\'un entrepôt industriel avec création de quais de chargement.',
                'cover_image' => '/images/portfolio/entrepot-1.jpg',
                'images' => json_encode([
                    '/images/portfolio/entrepot-1.jpg',
                    '/images/portfolio/entrepot-2.jpg',
                ]),
                'category' => 'Construction',
                'client' => 'Société de logistique',
                'location' => 'Zone Industrielle, Douala',
                'year' => 2023,
                'duration' => '10 mois',
                'budget' => '95 000 000 FCFA',
                'status' => 'completed',
                'is_featured' => false,
                'services' => json_encode(['Charpente métallique', 'Dalle industrielle', 'Quais']),
                'challenges' => 'Construction sans interrompre l\'activité de l\'entrepôt existant.',
                'results' => 'Doublement de la capacité de stockage.',
            ],
            [
                'title' => 'Maison Individuelle Moderne',
                'slug' => 'maison-individuelle-akwa',
                'description' => 'Construction d\'une maison individuelle R+1 de 250m² à Akwa.',
                'cover_image' => '/images/portfolio/maison-1.jpg',
                'images' => json_encode([
                    '/images/portfolio/maison-1.jpg',
                    '/images/portfolio/maison-2.jpg',
                ]),
                'category' => 'Construction',
                'client' => 'Famille Mbarga',
                'location' => 'Akwa, Douala',
                'year' => 2024,
                'duration' => '12 mois',
                'budget' => '85 000 000 FCFA',
                'status' => 'in_progress',
                'is_featured' => false,
                'services' => json_encode(['Plans', 'Gros œuvre', 'Second œuvre']),
                'challenges' => 'Terrain étroit en zone urbaine dense.',
                'results' => 'Projet en cours - Livraison prévue Q2 2026.',
            ],
        ];

        foreach ($projects as $project) {
            PortfolioProject::updateOrCreate(
                ['slug' => $project['slug']],
                $project
            );
        }

        $this->command->info('✓ Portfolio projects seeded successfully (6 projets)');
    }
}
