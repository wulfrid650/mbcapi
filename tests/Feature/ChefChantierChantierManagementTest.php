<?php

namespace Tests\Feature;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChefChantierChantierManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(array $overrides = []): PortfolioProject
    {
        $owner = User::factory()->create(['role' => 'chef_chantier']);

        return PortfolioProject::create(array_merge([
            'title' => 'Chantier test',
            'slug' => 'chantier-test-' . uniqid(),
            'description' => 'Description',
            'category' => 'Construction',
            'status' => 'planned',
            'progress' => 0,
            'is_published' => false,
            'created_by' => $owner->id,
            'chef_chantier_id' => $owner->id,
        ], $overrides));
    }

    public function test_chef_chantier_can_create_chantier(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        Sanctum::actingAs($chef);

        $response = $this->postJson('/api/chef-chantier/chantiers', [
            'title' => 'Nouveau chantier',
            'description' => 'Chantier pilote',
            'location' => 'Douala',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Nouveau chantier')
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.progress', 0);

        $project = PortfolioProject::findOrFail($response->json('data.id'));
        $this->assertSame($chef->id, (int) $project->created_by);
        $this->assertSame($chef->id, (int) $project->chef_chantier_id);
    }

    public function test_chef_chantier_can_update_own_chantier(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $project = $this->makeProject([
            'created_by' => $chef->id,
            'chef_chantier_id' => $chef->id,
        ]);
        Sanctum::actingAs($chef);

        $response = $this->putJson("/api/chef-chantier/chantiers/{$project->id}", [
            'title' => 'Chantier mis à jour',
            'status' => 'in_progress',
            'progress' => 35,
            'location' => 'Yaoundé',
            'start_date' => '2026-03-01',
            'expected_end_date' => '2026-05-01',
            'budget' => '25000000',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Chantier mis à jour')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.progress', 35);

        $project->refresh();
        $this->assertSame('Yaoundé', $project->location);
        $this->assertSame('25000000', (string) $project->budget);
    }

    public function test_chef_chantier_cannot_update_unassigned_chantier(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $otherChef = User::factory()->create(['role' => 'chef_chantier']);
        $project = $this->makeProject([
            'created_by' => $otherChef->id,
            'chef_chantier_id' => $otherChef->id,
            'team_ids' => [],
        ]);
        Sanctum::actingAs($chef);

        $response = $this->putJson("/api/chef-chantier/chantiers/{$project->id}", [
            'status' => 'in_progress',
            'progress' => 20,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_update_any_chantier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = $this->makeProject();
        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/chef-chantier/chantiers/{$project->id}", [
            'status' => 'completed',
            'progress' => 100,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.progress', 100);
    }
}
