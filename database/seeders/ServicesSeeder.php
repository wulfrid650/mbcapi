<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

/**
 * Seeder pour les services proposés par MBC
 * Basé sur les données du frontend
 */
class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            // ==========================================
            // 01 - Architecture & Études
            // ==========================================
            [
                'title' => 'Architecture & Études',
                'slug' => 'architecture-etudes',
                'short_description' => 'Conceptions modernes et fonctionnelles, respectant les normes les plus strictes.',
                'description' => '# Architecture & Études

Conceptions modernes et fonctionnelles, respectant les normes les plus strictes.

## Nos prestations

- **Conception de plans 2D et 3D** : Modélisation complète de votre projet
- **Études architecturales et structurelles** : Analyse approfondie et dimensionnement
- **Dimensionnement et calcul de structure** : Calculs techniques conformes aux normes
- **Rendu réaliste et visites virtuelles** : Visualisation immersive de votre futur projet

## Outils utilisés

- AutoCAD
- ArchiCAD
- SketchUp
- Revit

## Notre approche

Nous travaillons en étroite collaboration avec vous pour transformer vos idées en plans concrets et réalisables.',
                'icon' => 'Compass',
                'cover_image' => '/images/services/architecture.jpg',
                'features' => json_encode([
                    'Conception de plans 2D et 3D',
                    'Études architecturales et structurelles',
                    'Dimensionnement et calcul de structure',
                    'Rendu réaliste et visites virtuelles'
                ]),
                'starting_price' => null,
                'is_active' => true,
                'is_featured' => true,
                'display_order' => 1,
            ],
            // ==========================================
            // 02 - Construction & BTP
            // ==========================================
            [
                'title' => 'Construction & BTP',
                'slug' => 'construction-btp',
                'short_description' => 'Exécution rigoureuse pour des ouvrages durables. Réalisation intégrale de vos chantiers.',
                'description' => '# Construction & BTP

Réalisation de bâtiments résidentiels et industriels, travaux de gros œuvre et second œuvre, expertise chantier.

## Nos prestations

- **Gros œuvre et second œuvre** : Construction complète de A à Z
- **Suivi et expertise des travaux** : Contrôle qualité permanent
- **Location de matériel de génie civil** : Équipements professionnels
- **Maçonnerie, carrelage, peinture** : Finitions soignées

## Types de projets

- Maisons individuelles
- Immeubles résidentiels
- Bâtiments commerciaux et industriels
- Rénovation et extension

## Nos garanties

- Respect des délais et du budget
- Matériaux de qualité certifiés
- Suivi de chantier personnalisé
- Main d\'œuvre qualifiée',
                'icon' => 'Building',
                'cover_image' => '/images/services/construction.jpg',
                'features' => json_encode([
                    'Gros œuvre et second œuvre',
                    'Suivi et expertise des travaux',
                    'Location de matériel de génie civil',
                    'Maçonnerie, carrelage, peinture'
                ]),
                'starting_price' => null,
                'is_active' => true,
                'is_featured' => true,
                'display_order' => 2,
            ],
            // ==========================================
            // 03 - Génie Civil
            // ==========================================
            [
                'title' => 'Génie Civil',
                'slug' => 'genie-civil',
                'short_description' => 'Solutions d\'ingénierie pour infrastructures publiques et privées.',
                'description' => '# Génie Civil

Solutions d\'ingénierie pour infrastructures publiques et privées.

## Nos prestations

- **Ouvrages d\'art** : Ponts, dalots, passages
- **Assainissement et caniveaux** : Gestion des eaux pluviales
- **Murs de soutènement** : Stabilisation des terrains
- **Voiries et Réseaux Divers (VRD)** : Infrastructures complètes

## Domaines d\'intervention

- Travaux publics
- Aménagement urbain
- Infrastructures routières
- Réseaux d\'assainissement

## Notre expertise

Une équipe d\'ingénieurs spécialisés en génie civil pour des ouvrages durables et conformes aux normes.',
                'icon' => 'Layers',
                'cover_image' => '/images/services/genie-civil.jpg',
                'features' => json_encode([
                    'Ouvrages d\'art (ponts, dalots)',
                    'Assainissement et caniveaux',
                    'Murs de soutènement',
                    'Voiries et Réseaux Divers (VRD)'
                ]),
                'starting_price' => null,
                'is_active' => true,
                'is_featured' => true,
                'display_order' => 3,
            ],
            // ==========================================
            // 04 - Formations CAO & DAO
            // ==========================================
            [
                'title' => 'Formations CAO & DAO',
                'slug' => 'formations-cao-dao',
                'short_description' => 'Apprenez à maîtriser AutoCAD, Revit, ArchiCAD, SketchUp, Twinmotion et les méthodes BIM.',
                'description' => '# Formations CAO & DAO

Apprenez à maîtriser AutoCAD, Revit, ArchiCAD, SketchUp, Twinmotion et les méthodes BIM pour vos projets professionnels.

## Nos formations

- **Formation BIM complète** : 3 mois - ArchiCAD, SketchUp, Robot
- **Formation Enscape** : 3 semaines - Rendu temps réel
- **Formation Twinmotion** : 3 semaines - Animations 3D et réalité virtuelle
- **Formation Enscape Accélérée** : 5 semaines - Programme intensif

## Points forts

- Formation 100% pratique
- Certificat reconnu
- Projets réels sur chantiers MBC
- Accompagnement personnalisé

## Pré-requis matériel

Machine avec carte graphique dédiée de 4 Go minimum (Nvidia RTX ou GTX recommandé)',
                'icon' => 'GraduationCap',
                'cover_image' => '/images/services/formation.jpg',
                'features' => json_encode([
                    'Formation 100% pratique',
                    'Certificat reconnu',
                    'Projets réels',
                    'Accompagnement personnalisé'
                ]),
                'starting_price' => 100000,
                'is_active' => true,
                'is_featured' => true,
                'display_order' => 4,
            ],
            // ==========================================
            // 05 - Accompagnement de projet
            // ==========================================
            [
                'title' => 'Accompagnement de projet',
                'slug' => 'accompagnement-projet',
                'short_description' => 'De la première idée à la remise des clés : nous sommes à vos côtés à chaque étape clé.',
                'description' => '# Accompagnement de projet

Un accompagnement complet de la première idée à la remise des clés.

## Nos services

### Choix du Terrain
Conseil et expertise pour le choix optimal de votre site.

### Achat Matériaux
Sélection et fourniture de matériaux de qualité.

### Permis de Bâtir
Assistance complète dans les démarches administratives.

### Contrôle Technique
Suivi rigoureux de la conformité des travaux.

## Notre approche

- Un interlocuteur unique
- Réunions de suivi régulières
- Transparence totale sur l\'avancement
- Respect des délais contractuels',
                'icon' => 'ClipboardCheck',
                'cover_image' => '/images/services/accompagnement.jpg',
                'features' => json_encode([
                    'Choix du terrain',
                    'Achat des matériaux',
                    'Permis de bâtir',
                    'Contrôle technique'
                ]),
                'starting_price' => null,
                'is_active' => true,
                'is_featured' => false,
                'display_order' => 5,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['slug' => $service['slug']],
                $service
            );
        }

        $this->command->info('✓ Services seeded successfully (5 services)');
    }
}
