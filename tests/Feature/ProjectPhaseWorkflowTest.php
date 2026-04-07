<?php

namespace Tests\Feature;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectPhaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createProjectForChef(User $chef): PortfolioProject
    {
        return PortfolioProject::create([
            'title' => 'Projet phases',
            'slug' => 'projet-phases-' . uniqid(),
            'description' => 'Test phase workflow',
            'category' => 'Construction',
            'status' => 'planned',
            'progress' => 5,
            'is_published' => false,
            'created_by' => $chef->id,
            'chef_chantier_id' => $chef->id,
            'metadata' => [],
        ]);
    }

    public function test_chef_phase_transition_is_pending_until_staff_approval(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $project = $this->createProjectForChef($chef);
        Sanctum::actingAs($chef);

        $response = $this->postJson("/api/chef-chantier/chantiers/{$project->id}/phase-transition", [
            'to_phase' => 'fondations',
            'note' => 'Fondations prêtes à démarrer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'pending_approval')
            ->assertJsonPath('data.phase_state.pending_request.to_phase', 'fondations');

        $project->refresh();
        $this->assertSame('planned', $project->status);
        $this->assertSame(5, (int) $project->progress);
        $this->assertSame('fondations', data_get($project->metadata, 'phase_workflow.pending_request.to_phase'));
        $this->assertNull(data_get($project->metadata, 'phase_workflow.current_phase'));
    }

    public function test_secretaire_can_approve_pending_phase_transition(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $project = $this->createProjectForChef($chef);

        Sanctum::actingAs($chef);
        $this->postJson("/api/chef-chantier/chantiers/{$project->id}/phase-transition", [
            'to_phase' => 'fondations',
        ])->assertOk();

        Sanctum::actingAs($secretaire);
        $response = $this->postJson("/api/secretaire/projets/{$project->id}/phase-transition", [
            'action' => 'approve',
            'note' => 'Approuvé par secrétariat',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'approved')
            ->assertJsonPath('data.phase_state.current_phase', 'fondations');

        $project->refresh();
        $this->assertSame('fondations', data_get($project->metadata, 'phase_workflow.current_phase'));
        $this->assertNull(data_get($project->metadata, 'phase_workflow.pending_request'));
        $this->assertSame('in_progress', $project->status);
        $this->assertGreaterThanOrEqual(20, (int) $project->progress);
    }

    public function test_staff_can_apply_phase_directly_without_pending_request(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $secretaire = User::factory()->create(['role' => 'secretaire']);
        $project = $this->createProjectForChef($chef);

        Sanctum::actingAs($secretaire);
        $response = $this->postJson("/api/secretaire/projets/{$project->id}/phase-transition", [
            'action' => 'apply',
            'to_phase' => 'gros_oeuvre',
            'note' => 'Phase avancée par staff',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mode', 'applied')
            ->assertJsonPath('data.phase_state.current_phase', 'gros_oeuvre');

        $project->refresh();
        $this->assertSame('gros_oeuvre', data_get($project->metadata, 'phase_workflow.current_phase'));
        $this->assertNull(data_get($project->metadata, 'phase_workflow.pending_request'));
        $this->assertGreaterThanOrEqual(35, (int) $project->progress);
    }
}
