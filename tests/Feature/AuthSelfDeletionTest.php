<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthSelfDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_delete_own_account(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'current_password' => 'password',
            'confirmation' => 'SUPPRIMER',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Compte supprimé par son titulaire',
        ]);
    }

    public function test_staff_account_cannot_delete_itself(): void
    {
        $user = User::factory()->create([
            'role' => 'secretaire',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/account', [
            'current_password' => 'password',
            'confirmation' => 'SUPPRIMER',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }
}
