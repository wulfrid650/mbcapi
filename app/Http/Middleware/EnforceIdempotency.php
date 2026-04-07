<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotency
{
    private const DEFAULT_TTL_HOURS = 24;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('post')) {
            return $next($request);
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            return $next($request);
        }
        if (strlen($idempotencyKey) > 48) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key trop longue (48 caractères max).',
            ], 400);
        }

        ApiIdempotencyKey::query()
            ->where('expires_at', '<=', now())
            ->delete();

        $scope = $this->resolveScope($request);
        $method = strtoupper((string) $request->method());
        $route = (string) $request->path();
        $requestHash = $this->hashRequestPayload($request);
        $expiresAt = now()->addHours(self::DEFAULT_TTL_HOURS);

        $record = ApiIdempotencyKey::query()
            ->valid()
            ->where('idempotency_key', $idempotencyKey)
            ->where('user_scope', $scope)
            ->where('method', $method)
            ->where('route', $route)
            ->first();

        if ($record) {
            if ($record->request_hash !== $requestHash) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idempotency-Key déjà utilisée avec un payload différent.',
                ], 409);
            }

            if ($record->status_code !== null) {
                return $this->buildStoredResponse($record);
            }

            return response()->json([
                'success' => false,
                'message' => 'Une requête identique est déjà en cours de traitement.',
            ], 409);
        }

        try {
            $record = ApiIdempotencyKey::query()->create([
                'idempotency_key' => $idempotencyKey,
                'user_scope' => $scope,
                'method' => $method,
                'route' => $route,
                'request_hash' => $requestHash,
                'expires_at' => $expiresAt,
            ]);
        } catch (QueryException) {
            $existing = ApiIdempotencyKey::query()
                ->valid()
                ->where('idempotency_key', $idempotencyKey)
                ->where('user_scope', $scope)
                ->where('method', $method)
                ->where('route', $route)
                ->first();

            if ($existing && $existing->request_hash === $requestHash && $existing->status_code !== null) {
                return $this->buildStoredResponse($existing);
            }

            return response()->json([
                'success' => false,
                'message' => 'Requête dupliquée détectée. Réessayez dans quelques secondes.',
            ], 409);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $record->delete();
            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            $record->delete();
            return $response;
        }

        $responseBody = $response->getContent();
        if ($responseBody !== false && strlen($responseBody) > 65535) {
            $responseBody = substr($responseBody, 0, 65535);
        }

        $headers = $response->headers->allPreserveCase();
        $storableHeaders = [
            'Content-Type' => $headers['Content-Type'][0] ?? 'application/json',
        ];

        $record->update([
            'status_code' => $response->getStatusCode(),
            'response_body' => $responseBody ?: null,
            'response_headers' => $storableHeaders,
            'completed_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return $response;
    }

    private function resolveScope(Request $request): string
    {
        $userId = $request->user()?->id;
        if ($userId) {
            return "user:{$userId}";
        }

        $fingerprint = hash('sha256', ($request->ip() ?? '0.0.0.0') . '|' . (string) $request->userAgent());
        return 'guest:' . substr($fingerprint, 0, 32);
    }

    private function hashRequestPayload(Request $request): string
    {
        $payload = $request->all();
        $normalized = $this->sortRecursively($payload);
        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        if (array_is_list($value)) {
            return $value;
        }

        ksort($value);
        return $value;
    }

    private function buildStoredResponse(ApiIdempotencyKey $record): JsonResponse
    {
        $payload = null;
        if ($record->response_body) {
            $decoded = json_decode($record->response_body, true);
            $payload = json_last_error() === JSON_ERROR_NONE ? $decoded : ['message' => $record->response_body];
        }

        $response = response()->json($payload ?? ['success' => true], $record->status_code ?? 200);
        $contentType = $record->response_headers['Content-Type'] ?? 'application/json';
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('X-Idempotent-Replay', 'true');

        return $response;
    }
}
