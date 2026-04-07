<?php

namespace Tests\Feature;

use App\Mail\LoginTwoFactorCode;
use App\Models\LegalAcceptance;
use App\Models\SiteSetting;
use App\Models\TwoFactorLoginChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_two_factor_and_sends_email_code(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'accept_terms' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_two_factor', true);

        $this->assertDatabaseCount('two_factor_login_challenges', 1);
        $this->assertDatabaseCount('two_factor_login_challenge_sends', 1);
        $this->assertDatabaseCount('legal_acceptances', 4);
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'slug' => 'cgu',
            'accepted_via' => 'login',
        ]);

        Mail::assertSent(LoginTwoFactorCode::class, 1);
    }

    public function test_user_can_finalize_login_with_received_two_factor_code(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => 'formateur',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'accept_terms' => true,
        ]);

        $challengeToken = $loginResponse->json('two_factor.challenge_token');
        $sentCode = null;

        Mail::assertSent(LoginTwoFactorCode::class, function (LoginTwoFactorCode $mail) use (&$sentCode, $user) {
            if ($mail->user->id !== $user->id) {
                return false;
            }

            $sentCode = $mail->code;
            return true;
        });

        $verifyResponse = $this->postJson('/api/auth/two-factor/verify', [
            'challenge_token' => $challengeToken,
            'code' => $sentCode,
        ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.email', $user->email);

        $this->assertNotEmpty($verifyResponse->json('token'));

        $challenge = TwoFactorLoginChallenge::query()->first();
        $this->assertNotNull($challenge?->verified_at);
        $this->assertNotNull($challenge?->consumed_at);
    }

    public function test_two_factor_resend_is_limited_to_five_sends_per_rolling_hour(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-03-30 08:00:00'));

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'accept_terms' => true,
        ]);

        $challengeToken = $loginResponse->json('two_factor.challenge_token');

        for ($attempt = 0; $attempt < 4; $attempt++) {
            Carbon::setTestNow(now()->addSeconds(121));

            $this->postJson('/api/auth/two-factor/resend', [
                'challenge_token' => $challengeToken,
            ])->assertOk();
        }

        Carbon::setTestNow(now()->addSeconds(121));

        $blockedResponse = $this->postJson('/api/auth/two-factor/resend', [
            'challenge_token' => $challengeToken,
        ]);

        $blockedResponse
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->assertGreaterThan(0, (int) $blockedResponse->json('retry_after_seconds'));

        Carbon::setTestNow();
    }

    public function test_placeholder_mail_host_in_site_settings_does_not_override_configured_smtp_host(): void
    {
        Mail::fake();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'relay-user',
            'mail.mailers.smtp.password' => 'relay-password',
        ]);

        SiteSetting::create([
            'key' => 'mail_mailer',
            'value' => 'smtp',
            'type' => 'select',
            'group' => 'email',
            'label' => 'Type de service mail',
            'description' => 'Test runtime settings',
            'is_public' => false,
        ]);

        SiteSetting::create([
            'key' => 'mail_host',
            'value' => 'smtp.example.com',
            'type' => 'text',
            'group' => 'email',
            'label' => 'Serveur SMTP',
            'description' => 'Test runtime settings',
            'is_public' => false,
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'accept_terms' => true,
        ])->assertOk();

        $this->assertSame('smtp-relay.brevo.com', config('mail.mailers.smtp.host'));
        Mail::assertSent(LoginTwoFactorCode::class, 1);
    }

    public function test_repeated_login_does_not_duplicate_or_overwrite_legal_acceptances_for_same_version(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-06 09:00:00'));

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'accept_terms' => true,
        ])->assertOk();

        $acceptance = LegalAcceptance::query()
            ->where('user_id', $user->id)
            ->where('slug', 'cgu')
            ->firstOrFail();

        $firstAcceptedAt = $acceptance->accepted_at?->toISOString();

        Carbon::setTestNow(Carbon::parse('2026-04-06 10:30:00'));

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'accept_terms' => true,
        ])->assertOk();

        $this->assertDatabaseCount('legal_acceptances', 4);

        $acceptance->refresh();

        $this->assertSame($firstAcceptedAt, $acceptance->accepted_at?->toISOString());

        Carbon::setTestNow();
    }
}
