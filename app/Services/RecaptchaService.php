<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function shouldVerify(string $formType): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return in_array($formType, $this->getProtectedForms(), true);
    }

    public function verifyToken(?string $token, string $formType): array
    {
        if (!$this->shouldVerify($formType)) {
            return ['success' => true, 'skipped' => true];
        }

        $secret = trim((string) SiteSetting::get('recaptcha_secret_key', ''));
        if ($secret === '') {
            return [
                'success' => false,
                'message' => 'reCAPTCHA non configuré. Contactez l\'administrateur.',
            ];
        }

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Token reCAPTCHA manquant.',
            ];
        }

        try {
            $response = Http::timeout(5)
                ->withOptions([
                    'verify' => !app()->environment('local'),
                ])
                ->asForm()
                ->post(self::VERIFY_URL, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => request()?->ip(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA request failed', [
                'message' => $e->getMessage(),
                'form' => $formType,
            ]);
            return [
                'success' => false,
                'message' => 'Vérification reCAPTCHA indisponible. Réessayez.',
            ];
        }

        if (!$response->ok()) {
            Log::warning('reCAPTCHA verify HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'form' => $formType,
            ]);
            return [
                'success' => false,
                'message' => 'Vérification reCAPTCHA échouée.',
            ];
        }

        $payload = $response->json();
        $success = (bool) ($payload['success'] ?? false);

        if (!$success) {
            return [
                'success' => false,
                'message' => 'reCAPTCHA invalide. Veuillez cocher la case.',
            ];
        }

        return [
            'success' => true,
        ];
    }

    private function isEnabled(): bool
    {
        $enabled = SiteSetting::get('recaptcha_enabled', false);
        return $enabled === true || $enabled === '1' || $enabled === 1 || $enabled === 'true';
    }

    private function getProtectedForms(): array
    {
        $forms = SiteSetting::get('recaptcha_forms', ['contact', 'login', 'register']);
        if (is_array($forms)) {
            return $forms;
        }
        if (is_string($forms)) {
            $decoded = json_decode($forms, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return ['contact', 'login', 'register'];
    }

    private function getMinScore(): float
    {
        $score = SiteSetting::get('recaptcha_min_score', 0.5);
        if (is_numeric($score)) {
            return (float) $score;
        }
        return 0.5;
    }
}
