<?php

namespace Tests\Feature;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecretaireProjetClientAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(): PortfolioProject
    {
        return PortfolioProject::create([
            'title' => 'Chantier suivi client',
            'slug' => 'chantier-suivi-client-' . uniqid(),
            'description' => 'Description',
            'category' => 'Construction',
            'status' => 'pending',
            'progress' => 0,
            'is_published' => false,
        ]);
    }

    public function test_secretaire_can_assign_client_to_project(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $client = User::factory()->create(['role' => 'client', 'email' => 'client@example.com', 'name' => 'Client Test']);
        $project = $this->makeProject();
        Sanctum::actingAs($secretaire);

        $response = $this->postJson("/api/secretaire/projets/{$project->id}/assign-client", [
            'client_id' => $client->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.client_id', $client->id);

        $project->refresh();
        $this->assertSame($client->id, (int) $project->client_id);
        $this->assertSame($client->email, $project->client_email);
        $this->assertSame($client->name, $project->client);
    }

    public function test_admin_can_assign_client_to_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $project = $this->makeProject();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/secretaire/projets/{$project->id}/assign-client", [
            'client_id' => $client->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_assignment_rejects_non_client_user(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $notClient = User::factory()->create(['role' => 'admin']);
        $project = $this->makeProject();
        Sanctum::actingAs($secretaire);

        $response = $this->postJson("/api/secretaire/projets/{$project->id}/assign-client", [
            'client_id' => $notClient->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_assignment_requires_creation_validation_first(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $client = User::factory()->create(['role' => 'client']);
        $project = $this->makeProject();
        $project->update([
            'metadata' => [
                'creation_validation' => ['status' => 'pending'],
            ],
        ]);

        Sanctum::actingAs($secretaire);

        $response = $this->postJson("/api/secretaire/projets/{$project->id}/assign-client", [
            'client_id' => $client->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
