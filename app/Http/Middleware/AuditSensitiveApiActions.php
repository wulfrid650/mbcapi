<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditSensitiveApiActions
{
    private const AUDITED_PATHS = [
        'api/admin/*',
        'api/secretaire/*',
        'api/chef-chantier/*',
        'api/apprenant/*',
        'api/client/*',
        'api/formateur/*',
        'api/payments/*',
        'api/auth/*',
    ];

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'mail_password',
        'recaptcha_secret_key',
        'moneroo_secret_key',
        'card_number',
        'card_pan',
        'cvv',
        'cvc',
        'pin',
        'otp',
    ];

    private const AREA_LABELS = [
        'api/admin/' => 'Administration',
        'api/secretaire/' => 'Secretariat',
        'api/chef-chantier/' => 'Chantier',
        'api/apprenant/' => 'Espace apprenant',
        'api/client/' => 'Espace client',
        'api/formateur/' => 'Espace formateur',
        'api/payments/' => 'Paiements',
        'api/auth/' => 'Authentification',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldAudit($request)) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $response = null;
        $exception = null;

        try {
            $response = $next($request);
            return $response;
        } catch (Throwable $e) {
            $exception = $e;
            throw $e;
        } finally {
            $this->writeAuditLog($request, $response, $exception, $startedAt);
        }
    }

    private function shouldAudit(Request $request): bool
    {
        if (!$request->is('api/*')) {
            return false;
        }

        foreach (self::AUDITED_PATHS as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function writeAuditLog(
        Request $request,
        ?Response $response,
        ?Throwable $exception,
        float $startedAt
    ): void {
        try {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $status = $response?->getStatusCode() ?? 500;
            $path = '/' . ltrim($request->path(), '/');

            if (!$this->shouldPersistAuditLog($request, $path, $status, $exception)) {
                return;
            }

            $payload = $this->sanitizeData($request->except(['_token']));

            $action = $this->buildAction($request, $path, $status, $exception);
            $description = $this->buildDescription(
                request: $request,
                path: $path,
                status: $status,
                durationMs: $durationMs,
                payload: $payload,
                exception: $exception
            );

            ActivityLogService::log(
                action: $action,
                description: $description,
                subject: null,
                userId: $request->user()?->id
            );
        } catch (Throwable $e) {
            // Never block the request lifecycle if audit logging fails.
        }
    }

    private function shouldPersistAuditLog(
        Request $request,
        string $path,
        int $status,
        ?Throwable $exception
    ): bool {
        if ($exception !== null || $status >= 500) {
            return true;
        }

        if ($path === '/api/auth/login' && $status >= 400) {
            return true;
        }

        return in_array($status, [401, 403, 419, 429], true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
                continue;
            }

            if ($value instanceof UploadedFile) {
                $sanitized[$key] = [
                    'name' => $value->getClientOriginalName(),
                    'size' => $value->getSize(),
                    'mime' => $value->getClientMimeType(),
                ];
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = Str::limit($value, 500, '...');
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * @param string $normalizedKey
     */
    private function isSensitiveKey(string $normalizedKey): bool
    {
        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        $partialMatches = [
            'password',
            'token',
            'secret',
            'authorization',
            'card',
            'cvv',
            'cvc',
            'pin',
            'otp',
        ];

        foreach ($partialMatches as $needle) {
            if (Str::contains($normalizedKey, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private function summarizeFiles(array $files): array
    {
        $summary = [];

        foreach ($files as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = $this->summarizeFiles($value);
                continue;
            }

            if ($value instanceof UploadedFile) {
                $summary[$key] = [
                    'name' => $value->getClientOriginalName(),
                    'size' => $value->getSize(),
                    'mime' => $value->getClientMimeType(),
                ];
            }
        }

        return $summary;
    }

    private function buildAction(
        Request $request,
        string $path,
        int $status,
        ?Throwable $exception
    ): string
    {
        $area = $this->resolveAreaLabel($path);

        if ($path === '/api/auth/login' && $status >= 400) {
            return 'Connexion refusée';
        }

        if ($status === 401) {
            return Str::limit("Accès refusé - {$area}", 190, '...');
        }

        if ($status === 403) {
            return Str::limit("Action refusée - {$area}", 190, '...');
        }

        if ($status === 419) {
            return Str::limit("Session expirée - {$area}", 190, '...');
        }

        if ($status === 429) {
            return Str::limit("Trop de tentatives - {$area}", 190, '...');
        }

        return Str::limit("Erreur serveur - {$area}", 190, '...');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildDescription(
        Request $request,
        string $path,
        int $status,
        int $durationMs,
        array $payload,
        ?Throwable $exception
    ): string {
        $actor = $request->user()?->name ?: 'Un visiteur';
        $area = $this->resolveAreaLabel($path);
        $context = $this->resolveActionContext($path);

        if ($path === '/api/auth/login' && $status >= 400) {
            $email = is_string($payload['email'] ?? null) ? trim((string) $payload['email']) : null;
            $target = $email ? " pour le compte {$email}" : '';

            return "Une tentative de connexion{$target} a été refusée. Vérifiez les identifiants saisis avant de réessayer.";
        }

        if ($status === 401) {
            return "{$actor} a tenté d'accéder à {$context} dans {$area} sans session valide.";
        }

        if ($status === 403) {
            return "{$actor} a tenté une action non autorisée sur {$context} dans {$area}.";
        }

        if ($status === 419) {
            return "La session de {$actor} a expiré pendant une action sur {$context} dans {$area}.";
        }

        if ($status === 429) {
            return "{$actor} a effectué trop de tentatives rapprochées sur {$context} dans {$area}.";
        }

        $parts = [
            "Une erreur a empêché le traitement de {$context} dans {$area}.",
            "Le système n'a pas pu terminer l'opération demandée.",
            "Temps de traitement observé : {$durationMs} ms.",
        ];

        if ($exception !== null) {
            $parts[] = 'Une vérification technique est nécessaire.';
        }

        return Str::limit(implode(' ', $parts), 1200, '...');
    }

    private function resolveAreaLabel(string $path): string
    {
        $normalizedPath = ltrim($path, '/');

        foreach (self::AREA_LABELS as $prefix => $label) {
            if (Str::startsWith($normalizedPath, $prefix)) {
                return $label;
            }
        }

        return 'API';
    }

    private function resolveActionContext(string $path): string
    {
        return match (true) {
            Str::contains($path, '/roles') => 'la gestion des rôles',
            Str::contains($path, '/users') => 'la gestion des utilisateurs',
            Str::contains($path, '/settings') => 'la configuration de la plateforme',
            Str::contains($path, '/reports') => 'la génération de rapports',
            Str::contains($path, '/paiements'),
            Str::contains($path, '/payments') => 'la gestion des paiements',
            Str::contains($path, '/formations') => 'la gestion des formations',
            Str::contains($path, '/projets'),
            Str::contains($path, '/portfolio'),
            Str::contains($path, '/chantiers') => 'la gestion des chantiers',
            $path === '/api/auth/login' => 'la connexion au compte',
            default => 'une opération sensible',
        };
    }

    private function resolveResultLabel(int $status): string
    {
        if ($status >= 200 && $status < 300) {
            return 'succes';
        }

        if ($status >= 300 && $status < 400) {
            return 'redirection';
        }

        if ($status >= 400 && $status < 500) {
            return 'echec utilisateur';
        }

        return 'erreur serveur';
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $files
     */
    private function buildInputSummary(array $query, array $payload, array $files): ?string
    {
        $summaryItems = [];

        $queryItems = $this->flattenSummary($query, 'query');
        $payloadItems = $this->flattenSummary($payload, 'payload');
        $fileItems = $this->flattenSummary($files, 'fichiers');

        $summaryItems = array_merge($summaryItems, $queryItems, $payloadItems, $fileItems);
        $summaryItems = array_slice($summaryItems, 0, 10);

        if (empty($summaryItems)) {
            return null;
        }

        return implode('; ', $summaryItems);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function flattenSummary(array $data, string $prefix = '', int $depth = 0): array
    {
        if ($depth > 2 || empty($data)) {
            return [];
        }

        $items = [];

        foreach ($data as $key => $value) {
            $label = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                if ($this->isAssocArray($value)) {
                    $items = array_merge($items, $this->flattenSummary($value, $label, $depth + 1));
                } else {
                    $items[] = "{$label}=[{$this->countLeafItems($value)} element(s)]";
                }
                continue;
            }

            $items[] = "{$label}={$this->formatSummaryValue($value)}";
        }

        return $items;
    }

    /**
     * @param mixed $value
     */
    private function formatSummaryValue($value): string
    {
        if ($value === null) {
            return 'vide';
        }

        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }

        if (is_string($value)) {
            $cleaned = preg_replace('/\s+/', ' ', trim($value)) ?? $value;
            return Str::limit($cleaned, 80, '...');
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return Str::limit((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 80, '...');
    }

    /**
     * @param array<mixed> $array
     */
    private function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param array<mixed> $array
     */
    private function countLeafItems(array $array): int
    {
        $count = 0;

        array_walk_recursive($array, static function () use (&$count): void {
            $count++;
        });

        return $count;
    }
}
