<?php

namespace Tests\Feature;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChefChantierAvancementLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_avancement_is_linked_to_project_and_client_timeline(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $project = PortfolioProject::create([
            'title' => 'Chantier avancement',
            'slug' => 'chantier-avancement-' . uniqid(),
            'category' => 'Construction',
            'status' => 'in_progress',
            'progress' => 20,
            'chef_chantier_id' => $chef->id,
            'created_by' => $chef->id,
        ]);

        Sanctum::actingAs($chef);

        $response = $this->postJson('/api/chef-chantier/avancements', [
            'project_id' => $project->id,
            'title' => 'Coffrage terminé',
            'description' => 'Étape de coffrage terminée.',
            'date' => now()->toDateString(),
            'status' => 'Publié',
            'progress' => 35,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project_id', $project->id);

        $this->assertDatabaseHas('project_updates', [
            'portfolio_project_id' => $project->id,
            'title' => 'Coffrage terminé',
        ]);

        $this->assertDatabaseHas('progress_updates', [
            'portfolio_project_id' => $project->id,
            'title' => 'Coffrage terminé',
            'progress' => 35,
        ]);
    }
}

