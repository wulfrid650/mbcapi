<?php

namespace Tests\Feature;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChantierValidationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_chef_creation_is_marked_pending_validation(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        Sanctum::actingAs($chef);

        $response = $this->postJson('/api/chef-chantier/chantiers', [
            'title' => 'Chantier à valider',
            'description' => 'En attente de validation',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.metadata.creation_validation.status', 'pending');
    }

    public function test_secretaire_can_validate_chantier_creation(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $project = PortfolioProject::create([
            'title' => 'Chantier test',
            'slug' => 'chantier-test-' . uniqid(),
            'category' => 'Construction',
            'status' => 'pending',
            'metadata' => [
                'creation_validation' => ['status' => 'pending'],
            ],
        ]);

        Sanctum::actingAs($secretaire);

        $response = $this->postJson("/api/secretaire/projets/{$project->id}/validate-creation", [
            'decision' => 'approved',
            'note' => 'Conforme.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.decision', 'approved');

        $project->refresh();
        $this->assertSame('approved', data_get($project->metadata, 'creation_validation.status'));
    }

    public function test_client_only_sees_approved_projects(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        PortfolioProject::create([
            'title' => 'Chantier en attente',
            'slug' => 'chantier-attente-' . uniqid(),
            'category' => 'Construction',
            'status' => 'in_progress',
            'client_id' => $client->id,
            'client_email' => $client->email,
            'metadata' => [
                'creation_validation' => ['status' => 'pending'],
            ],
        ]);

        $approvedProject = PortfolioProject::create([
            'title' => 'Chantier validé',
            'slug' => 'chantier-valide-' . uniqid(),
            'category' => 'Construction',
            'status' => 'in_progress',
            'client_id' => $client->id,
            'client_email' => $client->email,
            'metadata' => [
                'creation_validation' => ['status' => 'approved'],
            ],
        ]);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/client/projets');

        $response->assertOk()
            ->assertJsonMissing(['title' => 'Chantier en attente'])
            ->assertJsonFragment(['id' => $approvedProject->id]);
    }
}

