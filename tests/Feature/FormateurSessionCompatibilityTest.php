<?php

namespace Tests\Feature;

use App\Models\Formation;
use App\Models\FormationEnrollment;
use App\Models\FormationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormateurSessionCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeFormation(User $formateur): Formation
    {
        return Formation::withoutEvents(function () use ($formateur) {
            return Formation::create([
                'title' => 'Formation BIM ' . uniqid(),
                'slug' => 'formation-bim-' . uniqid(),
                'description' => 'Formation de test',
                'duration_hours' => 24,
                'duration_days' => 3,
                'price' => 150000,
                'registration_fees' => 0,
                'inscription_fee' => 10000,
                'level' => 'debutant',
                'category' => 'BIM',
                'max_students' => 12,
                'is_active' => true,
                'is_featured' => false,
                'display_order' => 0,
                'formateur_id' => $formateur->id,
            ]);
        });
    }

    public function test_formateur_creates_evaluation_from_enrollments_using_session_id(): void
    {
        $formateur = User::factory()->create(['role' => 'formateur']);
        $apprenant = User::factory()->create(['role' => 'apprenant']);

        $formation = $this->makeFormation($formateur);
        $session = FormationSession::create([
            'formation_id' => $formation->id,
            'formateur_id' => $formateur->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'location' => 'Douala',
            'max_students' => 12,
            'status' => 'planned',
        ]);

        FormationEnrollment::create([
            'user_id' => $apprenant->id,
            'formation_id' => $formation->id,
            'session_id' => $session->id,
            'status' => 'confirmed',
            'amount_paid' => 10000,
            'payment_complete' => true,
            'progression' => 0,
            'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($formateur);

        $response = $this->postJson('/api/formateur/evaluations', [
            'titre' => 'Examen session',
            'formation_session_id' => $session->id,
            'type' => 'exam',
            'date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.formation_session_id', $session->id);

        $evaluationId = $response->json('data.id');

        $this->assertDatabaseHas('evaluation_results', [
            'evaluation_id' => $evaluationId,
            'user_id' => $apprenant->id,
            'status' => 'pending',
        ]);
    }

    public function test_formateur_can_load_attendance_roster_from_session_id_enrollments(): void
    {
        $formateur = User::factory()->create(['role' => 'formateur']);
        $apprenant = User::factory()->create([
            'role' => 'apprenant',
            'name' => 'Apprenant Session',
        ]);

        $formation = $this->makeFormation($formateur);
        $session = FormationSession::create([
            'formation_id' => $formation->id,
            'formateur_id' => $formateur->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'location' => 'Yaounde',
            'max_students' => 12,
            'status' => 'planned',
        ]);

        FormationEnrollment::create([
            'user_id' => $apprenant->id,
            'formation_id' => $formation->id,
            'session_id' => $session->id,
            'status' => 'confirmed',
            'amount_paid' => 10000,
            'payment_complete' => true,
            'progression' => 0,
            'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($formateur);

        $response = $this->getJson('/api/formateur/presences?' . http_build_query([
            'date' => now()->toDateString(),
            'formation_session_id' => $session->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.apprenants.0.name', 'Apprenant Session');
    }
}
