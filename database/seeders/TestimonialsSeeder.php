<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Testimonial;

/**
 * Seeder pour les témoignages clients
 * Basé sur les données du frontend (TestimonialCarousel)
 */
class TestimonialsSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'author_name' => 'Jean-Pierre M.',
                'author_role' => 'Propriétaire à Douala',
                'author_company' => null,
                'author_image' => null,
                'content' => 'Équipe professionnelle et réactive. Notre villa a été livrée dans les délais avec une finition impeccable.',
                'rating' => 5,
                'project_type' => 'Construction',
                'is_approved' => true,
                'is_featured' => true,
                'display_order' => 1,
            ],
            [
                'author_name' => 'Marie K.',
                'author_role' => 'Directrice d\'entreprise',
                'author_company' => null,
                'author_image' => null,
                'content' => 'Excellent accompagnement du début à la fin. Je recommande MBC pour tout projet de construction.',
                'rating' => 5,
                'project_type' => 'Accompagnement',
                'is_approved' => true,
                'is_featured' => true,
                'display_order' => 2,
            ],
            [
                'author_name' => 'Patrick L.',
                'author_role' => 'Ingénieur civil',
                'author_company' => null,
                'author_image' => null,
                'content' => 'Très satisfait des travaux de génie civil. Qualité et respect des normes au rendez-vous.',
                'rating' => 5,
                'project_type' => 'Génie Civil',
                'is_approved' => true,
                'is_featured' => true,
                'display_order' => 3,
            ],
            [
                'author_name' => 'Sylvie N.',
                'author_role' => 'Architecte junior',
                'author_company' => null,
                'author_image' => null,
                'content' => 'La formation AutoCAD m\'a permis de décrocher un emploi. Formateurs compétents et pédagogues.',
                'rating' => 5,
                'project_type' => 'Formation',
                'is_approved' => true,
                'is_featured' => true,
                'display_order' => 4,
            ],
            [
                'author_name' => 'David T.',
                'author_role' => 'Promoteur immobilier',
                'author_company' => null,
                'author_image' => null,
                'content' => 'Un partenaire fiable pour nos projets immobiliers. Transparence et professionnalisme exemplaires.',
                'rating' => 5,
                'project_type' => 'Construction',
                'is_approved' => true,
                'is_featured' => true,
                'display_order' => 5,
            ],
            // Témoignages supplémentaires
            [
                'author_name' => 'Emmanuel B.',
                'author_role' => 'Particulier',
                'author_company' => null,
                'author_image' => null,
                'content' => 'MBC a su écouter nos besoins et nous proposer une solution adaptée à notre budget. La maison est exactement comme nous l\'avions imaginée.',
                'rating' => 5,
                'project_type' => 'Construction',
                'is_approved' => true,
                'is_featured' => false,
                'display_order' => 6,
            ],
            [
                'author_name' => 'Christelle A.',
                'author_role' => 'Étudiante en architecture',
                'author_company' => null,
                'author_image' => null,
                'content' => 'Formation BIM très complète. J\'ai appris à maîtriser ArchiCAD et les rendus 3D en seulement 3 mois. Je recommande vivement !',
                'rating' => 5,
                'project_type' => 'Formation',
                'is_approved' => true,
                'is_featured' => false,
                'display_order' => 7,
            ],
            [
                'author_name' => 'Michel F.',
                'author_role' => 'Chef d\'entreprise',
                'author_company' => 'MF Industries',
                'author_image' => null,
                'content' => 'Construction de notre entrepôt réalisée dans les règles de l\'art. Équipe professionnelle et respect des délais.',
                'rating' => 4,
                'project_type' => 'Construction',
                'is_approved' => true,
                'is_featured' => false,
                'display_order' => 8,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::updateOrCreate(
                [
                    'author_name' => $testimonial['author_name'],
                    'content' => $testimonial['content'],
                ],
                $testimonial
            );
        }

        $this->command->info('✓ Testimonials seeded successfully (8 témoignages)');
    }
}
