<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Formation;
use App\Models\FormationSession;
use App\Models\User;
use Carbon\Carbon;

/**
 * Seeder pour les formations MBC
 * Basé sur les données du frontend (page training)
 */
class FormationsSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer ou créer un formateur
        $formateur = User::whereHas('roles', function($q) {
            $q->where('slug', 'formateur');
        })->first();

        if (!$formateur) {
            $this->command->warn('⚠ Aucun formateur trouvé. Les formations seront créées sans formateur assigné.');
        }

        $formations = [
            // ==========================================
            // Formation BIM (Formation phare)
            // ==========================================
            [
                'title' => 'Formation BIM',
                'slug' => 'formation-bim',
                'description' => 'Formation pratique complète couvrant la conception architecturale avec ArchiCAD, le rendu photo/vidéo avec SketchUp et Enscape/Lumion/Twinmotion, et le calcul de structure avec Robot. Deviens un professionnel du bâtiment !',
                'objectives' => json_encode([
                    'Maîtriser ArchiCAD pour la conception architecturale',
                    'Créer des rendus photoréalistes avec SketchUp et Enscape',
                    'Réaliser des calculs de structure avec Robot',
                    'Produire des documents techniques professionnels',
                    'Travailler en méthodologie BIM'
                ]),
                'prerequisites' => json_encode([
                    'Connaissances de base en dessin technique',
                    'Machine avec carte graphique dédiée de 4 Go minimum',
                    'Nvidia RTX ou GTX recommandé'
                ]),
                'program' => json_encode([
                    ['name' => 'Conception Architecturale', 'software' => 'ArchiCAD', 'duration' => '4 semaines'],
                    ['name' => 'Rendu Photo et Vidéo', 'software' => 'SketchUp + Enscape / Lumion / Twinmotion', 'duration' => '4 semaines'],
                    ['name' => 'Calcul de Structure', 'software' => 'Robot', 'duration' => '4 semaines'],
                ]),
                'duration_hours' => 360, // 3 mois x 30 heures/semaine
                'duration_days' => 90,
                'price' => 250000,
                'level' => 'debutant',
                'category' => 'BIM',
                'cover_image' => '/images/formations/bim.jpg',
                'max_students' => 15,
                'is_active' => true,
                'is_featured' => true,
                'display_order' => 1,
                'formateur_id' => $formateur?->id,
            ],
            // ==========================================
            // Formation Enscape
            // ==========================================
            [
                'title' => 'Formation Enscape',
                'slug' => 'formation-enscape',
                'description' => 'Rendu temps réel pour architectes et designers 3D. Apprenez à créer des visuels époustouflants avec Enscape.',
                'objectives' => json_encode([
                    'Maîtriser l\'interface Enscape',
                    'Créer des rendus temps réel de qualité',
                    'Optimiser les paramètres de rendu',
                    'Produire des visuels professionnels'
                ]),
                'prerequisites' => json_encode([
                    'Connaissances de base en modélisation 3D',
                    'Machine avec carte graphique dédiée de 4 Go minimum',
                    'Nvidia RTX ou GTX recommandé'
                ]),
                'program' => json_encode([
                    ['name' => 'Prise en main', 'duration' => '1 semaine'],
                    ['name' => 'Techniques de base', 'duration' => '1 semaine'],
                    ['name' => 'Projets pratiques', 'duration' => '1 semaine'],
                ]),
                'duration_hours' => 90, // 3 semaines x 30h
                'duration_days' => 21,
                'price' => 100000,
                'level' => 'intermediaire',
                'category' => 'Rendu 3D',
                'cover_image' => '/images/formations/enscape.jpg',
                'max_students' => 10,
                'is_active' => true,
                'is_featured' => false,
                'display_order' => 2,
                'formateur_id' => $formateur?->id,
            ],
            // ==========================================
            // Formation Twinmotion
            // ==========================================
            [
                'title' => 'Formation Twinmotion',
                'slug' => 'formation-twinmotion',
                'description' => 'Rendus immersifs et animations architecturales. Créez des expériences en réalité virtuelle impressionnantes.',
                'objectives' => json_encode([
                    'Maîtriser Twinmotion',
                    'Créer des animations architecturales',
                    'Produire des expériences VR',
                    'Réaliser des rendus photoréalistes'
                ]),
                'prerequisites' => json_encode([
                    'Connaissances de base en modélisation 3D',
                    'Machine avec carte graphique dédiée de 4 Go minimum'
                ]),
                'program' => json_encode([
                    ['name' => 'Bases de Twinmotion', 'duration' => '1 semaine'],
                    ['name' => 'Animations et VR', 'duration' => '1 semaine'],
                    ['name' => 'Projets avancés', 'duration' => '1 semaine'],
                ]),
                'duration_hours' => 90,
                'duration_days' => 21,
                'price' => 100000,
                'level' => 'intermediaire',
                'category' => 'Rendu 3D',
                'cover_image' => '/images/formations/twinmotion.jpg',
                'max_students' => 10,
                'is_active' => true,
                'is_featured' => false,
                'display_order' => 3,
                'formateur_id' => $formateur?->id,
            ],
            // ==========================================
            // Formation Enscape Accélérée
            // ==========================================
            [
                'title' => 'Formation Enscape Accélérée',
                'slug' => 'formation-enscape-acceleree',
                'description' => 'Programme intensif : techniques de base et avancées. Certification complète incluse.',
                'objectives' => json_encode([
                    'Maîtrise complète d\'Enscape',
                    'Techniques de rendu avancées',
                    'Optimisation des performances',
                    'Certification professionnelle'
                ]),
                'prerequisites' => json_encode([
                    'Connaissances de base en modélisation 3D',
                    'Machine avec carte graphique dédiée de 4 Go minimum',
                    'Nvidia RTX ou GTX recommandé'
                ]),
                'program' => json_encode([
                    ['name' => 'Fondamentaux', 'duration' => '1 semaine'],
                    ['name' => 'Techniques intermédiaires', 'duration' => '1 semaine'],
                    ['name' => 'Techniques avancées', 'duration' => '2 semaines'],
                    ['name' => 'Certification', 'duration' => '1 semaine'],
                ]),
                'duration_hours' => 150, // 5 semaines x 30h
                'duration_days' => 35,
                'price' => 150000,
                'level' => 'avance',
                'category' => 'Rendu 3D',
                'cover_image' => '/images/formations/enscape-accelere.jpg',
                'max_students' => 10,
                'is_active' => true,
                'is_featured' => false,
                'display_order' => 4,
                'formateur_id' => $formateur?->id,
            ],
        ];

        foreach ($formations as $formationData) {
            $formation = Formation::updateOrCreate(
                ['slug' => $formationData['slug']],
                $formationData
            );

            // Créer une session pour Janvier 2026 pour chaque formation
            $durationWeeks = match($formation->slug) {
                'formation-bim' => 12,
                'formation-enscape-acceleree' => 5,
                default => 3,
            };

            FormationSession::updateOrCreate(
                [
                    'formation_id' => $formation->id,
                    'start_date' => Carbon::create(2026, 1, 15),
                ],
                [
                    'formation_id' => $formation->id,
                    'formateur_id' => $formation->formateur_id,
                    'start_date' => Carbon::create(2026, 1, 15),
                    'end_date' => Carbon::create(2026, 1, 15)->addWeeks($durationWeeks),
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                    'location' => 'Douala, Entrée principale IUC Logbesou',
                    'max_students' => $formation->max_students,
                    'status' => 'planned',
                ]
            );
        }

        $this->command->info('✓ Formations seeded successfully (4 formations avec sessions Janvier 2026)');
    }
}
