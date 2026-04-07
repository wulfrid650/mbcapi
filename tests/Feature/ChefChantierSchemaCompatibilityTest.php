<?php

namespace Tests\Feature;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChefChantierSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(User $chef): PortfolioProject
    {
        return PortfolioProject::create([
            'title' => 'Projet compat ' . uniqid(),
            'slug' => 'projet-compat-' . uniqid(),
            'description' => 'Projet pour test compatibilite schema',
            'category' => 'Construction',
            'status' => 'in_progress',
            'progress' => 10,
            'is_published' => false,
            'created_by' => $chef->id,
            'chef_chantier_id' => $chef->id,
        ]);
    }

    public function test_chef_chantier_can_create_daily_log_against_current_schema(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $project = $this->makeProject($chef);

        Sanctum::actingAs($chef);

        $response = $this->postJson('/api/chef-chantier/daily-logs', [
            'project_id' => $project->id,
            'date' => now()->toDateString(),
            'weather' => 'ensoleille',
            'workforce_count' => 8,
            'work_performed' => 'Ferraillage termine',
            'issues' => 'Aucun blocage',
            'notes' => 'RAS',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.work_performed', 'Ferraillage termine');

        $dailyLog = DB::table('daily_logs')
            ->where('project_id', $project->id)
            ->where('author_id', $chef->id)
            ->first();

        $this->assertNotNull($dailyLog);
        $this->assertStringStartsWith(now()->toDateString(), (string) $dailyLog->date);
    }

    public function test_chef_chantier_can_report_incident_against_current_schema(): void
    {
        $chef = User::factory()->create(['role' => 'chef_chantier']);
        $project = $this->makeProject($chef);

        Sanctum::actingAs($chef);

        $response = $this->postJson('/api/chef-chantier/incidents', [
            'project_id' => $project->id,
            'date' => now()->toDateString(),
            'type' => 'accident',
            'severity' => 'critical',
            'title' => 'Chute mineure',
            'description' => 'Un ouvrier a glisse sur une zone humide.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.title', 'Chute mineure')
            ->assertJsonPath('data.type', 'accident');

        $incident = DB::table('safety_incidents')
            ->where('project_id', $project->id)
            ->where('reporter_id', $chef->id)
            ->where('severity', 'CRITICAL')
            ->first();

        $this->assertNotNull($incident);
        $this->assertStringStartsWith(now()->toDateString(), (string) $incident->date);
    }
}
