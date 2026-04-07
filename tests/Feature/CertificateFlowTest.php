<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateRequest;
use App\Models\Formation;
use App\Models\FormationEnrollment;
use App\Models\FormationSession;
use App\Models\Payment;
use App\Models\User;
use App\Services\EnrollmentOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeFormation(User $formateur): Formation
    {
        return Formation::withoutEvents(function () use ($formateur) {
            return Formation::create([
                'title' => 'Formation Certificat ' . uniqid(),
                'slug' => 'formation-certificat-' . uniqid(),
                'description' => 'Formation de test pour les certificats',
                'duration_hours' => 40,
                'duration_days' => 5,
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

    private function makeSession(Formation $formation, User $formateur): FormationSession
    {
        return FormationSession::create([
            'formation_id' => $formation->id,
            'formateur_id' => $formateur->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'location' => 'Douala',
            'max_students' => 12,
            'status' => 'completed',
        ]);
    }

    private function makeEnrollment(Formation $formation, FormationSession $session, ?User $apprenant, array $overrides = []): FormationEnrollment
    {
        return FormationEnrollment::create(array_merge([
            'user_id' => $apprenant?->id,
            'formation_id' => $formation->id,
            'session_id' => $session->id,
            'first_name' => 'Apprenant',
            'last_name' => 'Test',
            'email' => $apprenant?->email ?? 'learner@example.test',
            'status' => 'completed',
            'amount_paid' => 150000,
            'payment_complete' => true,
            'progression' => 100,
            'enrolled_at' => now()->subDays(12),
            'completed_at' => now()->subDay(),
            'paid_at' => now()->subDays(11),
        ], $overrides));
    }

    public function test_secretaire_marking_enrollment_completed_no_longer_generates_certificate_automatically(): void
    {
        Storage::fake('public');

        $secretaire = User::factory()->create(['role' => 'secretaire', 'name' => 'Secretaire Test']);
        $formateur = User::factory()->create(['role' => 'formateur']);
        $apprenant = User::factory()->create(['role' => 'apprenant']);

        $formation = $this->makeFormation($formateur);
        $session = $this->makeSession($formation, $formateur);
        $enrollment = $this->makeEnrollment($formation, $session, $apprenant, [
            'status' => 'confirmed',
            'completed_at' => null,
        ]);

        Sanctum::actingAs($secretaire);

        $response = $this->putJson("/api/secretaire/enrollments/{$enrollment->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseMissing('certificates', [
            'formation_enrollment_id' => $enrollment->id,
        ]);
        $this->assertDatabaseMissing('certificate_requests', [
            'formation_enrollment_id' => $enrollment->id,
        ]);
    }

    public function test_formateur_can_request_certificate_for_assigned_learner_and_mark_enrollment_completed(): void
    {
        Storage::fake('public');

        $formateur = User::factory()->create(['role' => 'formateur']);
        $apprenant = User::factory()->create(['role' => 'apprenant']);

        $formation = $this->makeFormation($formateur);
        $session = $this->makeSession($formation, $formateur);
        $enrollment = $this->makeEnrollment($formation, $session, $apprenant);
        $enrollment->update([
            'status' => 'confirmed',
            'completed_at' => null,
        ]);

        Sanctum::actingAs($formateur);

        $this->postJson("/api/formateur/certificats/enrollments/{$enrollment->id}/request", [
            'notes' => 'Validation terrain effectuée.',
        ])
            ->assertOk()
            ->assertJsonPath('data.workflow_status', 'pending_secretary')
            ->assertJsonPath('data.enrollment_status', 'completed');

        $this->assertDatabaseHas('certificate_requests', [
            'formation_enrollment_id' => $enrollment->id,
            'status' => 'pending',
            'requested_by' => $formateur->id,
        ]);

        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
    }

    public function test_secretaire_can_approve_request_then_certificate_is_generated_and_downloadable(): void
    {
        Storage::fake('public');
        config(['app.frontend_url' => 'https://mbc.aureusprime.com']);

        $secretaire = User::factory()->create(['role' => 'secretaire', 'name' => 'Secretaire Test']);
        $formateur = User::factory()->create(['role' => 'formateur', 'name' => 'Formateur Test']);
        $apprenant = User::factory()->create(['role' => 'apprenant', 'name' => 'Apprenant Certifie']);

        $formation = $this->makeFormation($formateur);
        $session = $this->makeSession($formation, $formateur);
        $enrollment = $this->makeEnrollment($formation, $session, $apprenant);
        $enrollment->update([
            'status' => 'confirmed',
            'completed_at' => null,
        ]);

        Sanctum::actingAs($formateur);
        $this->postJson("/api/formateur/certificats/enrollments/{$enrollment->id}/request")
            ->assertOk();

        $certificateRequest = CertificateRequest::query()->where('formation_enrollment_id', $enrollment->id)->firstOrFail();

        Sanctum::actingAs($secretaire);
        $this->postJson("/api/secretaire/certificate-requests/{$certificateRequest->id}/approve", [
            'notes' => 'Validation OK',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.certificate_generated', true);

        $certificate = Certificate::query()
            ->where('formation_enrollment_id', $enrollment->id)
            ->firstOrFail();
        $reference = (string) $certificate->reference;
        $this->assertNotEmpty($reference);

        Storage::disk('public')->assertExists($certificate->pdf_path);

        Sanctum::actingAs($apprenant);
        $listResponse = $this->getJson('/api/apprenant/certificats');
        $listResponse->assertOk()
            ->assertJsonPath('data.issued.0.reference', $reference);

        $downloadResponse = $this->get("/api/apprenant/certificats/{$reference}/download");
        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_invalidating_a_certificate_revokes_it_for_download_and_public_verification(): void
    {
        Storage::fake('public');
        config(['app.frontend_url' => 'https://mbc.aureusprime.com']);

        $secretaire = User::factory()->create(['role' => 'secretaire', 'name' => 'Secretaire Test']);
        $formateur = User::factory()->create(['role' => 'formateur', 'name' => 'Formateur Verif']);
        $apprenant = User::factory()->create(['role' => 'apprenant', 'name' => 'Apprenant Verifie']);

        $formation = $this->makeFormation($formateur);
        $session = $this->makeSession($formation, $formateur);
        $enrollment = $this->makeEnrollment($formation, $session, $apprenant);
        $enrollment->update([
            'status' => 'confirmed',
            'completed_at' => null,
        ]);

        Sanctum::actingAs($formateur);
        $this->postJson("/api/formateur/certificats/enrollments/{$enrollment->id}/request")->assertOk();

        $certificateRequest = CertificateRequest::query()->where('formation_enrollment_id', $enrollment->id)->firstOrFail();

        Sanctum::actingAs($secretaire);
        $this->postJson("/api/secretaire/certificate-requests/{$certificateRequest->id}/approve")->assertOk();

        $reference = (string) Certificate::query()
            ->where('formation_enrollment_id', $enrollment->id)
            ->value('reference');
        $this->assertNotEmpty($reference);

        Sanctum::actingAs($secretaire);
        $this->postJson("/api/secretaire/certificate-requests/{$certificateRequest->id}/invalidate", [
            'reason' => 'Erreur administrative',
        ])->assertOk()
            ->assertJsonPath('data.status', 'invalidated');

        Sanctum::actingAs($apprenant);
        $this->get("/api/apprenant/certificats/{$reference}/download")
            ->assertStatus(410);

        $this->getJson("/api/public/certificats/verify/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.revoked_reason', 'Erreur administrative');
    }

    public function test_enrollment_ownership_service_attaches_orphan_enrollment_and_payment_to_user(): void
    {
        $user = User::factory()->create([
            'role' => 'apprenant',
            'email' => 'orphelin@example.test',
        ]);
        $formateur = User::factory()->create(['role' => 'formateur']);
        $formation = $this->makeFormation($formateur);
        $session = $this->makeSession($formation, $formateur);

        $enrollment = $this->makeEnrollment($formation, $session, null, [
            'email' => $user->email,
            'status' => 'confirmed',
            'completed_at' => null,
        ]);

        $payment = Payment::create([
            'reference' => 'PAY-TEST-OWNERSHIP',
            'user_id' => null,
            'payable_type' => FormationEnrollment::class,
            'payable_id' => $enrollment->id,
            'amount' => 10000,
            'currency' => 'XAF',
            'method' => 'cash',
            'status' => 'completed',
            'description' => 'Frais inscription',
            'purpose' => 'formation_payment',
            'payer_name' => $user->name,
            'payer_email' => $user->email,
            'validated_at' => now(),
        ]);

        $result = app(EnrollmentOwnershipService::class)->attachByEmail($user);

        $this->assertSame(1, $result['enrollments']);
        $this->assertSame(1, $result['payments']);
        $this->assertSame($user->id, $enrollment->fresh()->user_id);
        $this->assertSame($user->id, $payment->fresh()->user_id);
    }

    public function test_apprenant_dashboard_ignores_conflicting_orphan_enrollments_during_ownership_sync(): void
    {
        $user = User::factory()->create([
            'role' => 'apprenant',
            'email' => 'doublon@example.test',
        ]);
        $formateur = User::factory()->create(['role' => 'formateur']);
        $formation = $this->makeFormation($formateur);
        $sessionA = $this->makeSession($formation, $formateur);
        $sessionB = $this->makeSession($formation, $formateur);

        $attachedEnrollment = $this->makeEnrollment($formation, $sessionA, $user, [
            'status' => 'completed',
        ]);

        $conflictingOrphan = $this->makeEnrollment($formation, $sessionA, null, [
            'email' => $user->email,
            'status' => 'cancelled',
            'completed_at' => null,
        ]);

        $attachableOrphan = $this->makeEnrollment($formation, $sessionB, null, [
            'email' => $user->email,
            'status' => 'confirmed',
            'completed_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/apprenant/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($user->id, $attachedEnrollment->fresh()->user_id);
        $this->assertNull($conflictingOrphan->fresh()->user_id);
        $this->assertSame($user->id, $attachableOrphan->fresh()->user_id);
    }

}
