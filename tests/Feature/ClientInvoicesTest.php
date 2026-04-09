<?php

namespace Tests\Feature;

use App\Models\Formation;
use App\Models\FormationEnrollment;
use App\Models\FormationSession;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_list_invoices_with_mixed_payable_types(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'email' => 'client@example.com',
        ]);

        $formation = Formation::query()->create([
            'title' => 'Maçonnerie avancée',
            'slug' => 'maconnerie-avancee',
            'description' => 'Formation test',
            'price' => 150000,
            'inscription_fee' => 25000,
            'duration_hours' => 40,
            'level' => 'intermediate',
            'category' => 'BTP',
            'is_active' => true,
        ]);

        $session = FormationSession::query()->create([
            'formation_id' => $formation->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'location' => 'Douala',
            'status' => 'planned',
        ]);

        $enrollment = FormationEnrollment::query()->create([
            'user_id' => $client->id,
            'formation_id' => $formation->id,
            'session_id' => $session->id,
            'first_name' => 'Client',
            'last_name' => 'Test',
            'email' => $client->email,
            'phone' => '+237600000000',
            'status' => 'pending_payment',
        ]);

        Payment::query()->create([
            'user_id' => $client->id,
            'payable_type' => FormationEnrollment::class,
            'payable_id' => $enrollment->id,
            'amount' => 25000,
            'currency' => 'XAF',
            'method' => 'link',
            'status' => 'pending',
            'description' => 'Frais inscription',
            'reference' => 'PAY-TEST-000001',
        ]);

        Payment::query()->create([
            'user_id' => $client->id,
            'payable_type' => User::class,
            'payable_id' => $client->id,
            'amount' => 90000,
            'currency' => 'XAF',
            'method' => 'cash',
            'status' => 'completed',
            'description' => 'Facture chantier',
            'reference' => 'PAY-TEST-000002',
        ]);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/client/factures');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['reference' => 'PAY-TEST-000001'])
            ->assertJsonFragment(['reference' => 'PAY-TEST-000002'])
            ->assertJsonFragment(['title' => 'Maçonnerie avancée']);
    }

    public function test_client_can_get_frontend_payment_link_for_pending_invoice(): void
    {
        config(['app.frontend_url' => 'https://www.madibabc.com']);

        $client = User::factory()->create([
            'role' => 'client',
        ]);

        $payment = Payment::query()->create([
            'user_id' => $client->id,
            'payable_type' => User::class,
            'payable_id' => $client->id,
            'amount' => 50000,
            'currency' => 'XAF',
            'method' => 'link',
            'status' => 'pending',
            'description' => 'Facture client',
            'reference' => 'PAY-TEST-000003',
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson("/api/client/factures/{$payment->id}/pay");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_url', 'https://www.madibabc.com/paiement/link/PAY-TEST-000003')
            ->assertJsonPath('data.reference', 'PAY-TEST-000003');
    }
}
