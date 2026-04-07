<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\ActivityLogService;
use App\Services\FormationSessionSchedulerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contrôleur pour la gestion des paramètres du site (admin)
 */
class SettingsController extends Controller
{
    private const AUTO_SESSION_SETTING_KEYS = [
        'formation_sessions_auto_enabled',
        'formation_sessions_start_date',
        'formation_sessions_interval_months',
        'formation_sessions_months_ahead',
    ];

    /**
     * Récupérer tous les paramètres groupés
     */
    public function index(): JsonResponse
    {
        $settings = SiteSetting::orderBy('group')->orderBy('key')->get();

        // Grouper par catégorie
        $grouped = $settings->groupBy('group')->map(function ($items) {
            return $items->map(function ($item) {
                $value = $item->value;
                
                // Décoder le JSON si nécessaire
                if ($item->type === 'json' && $value) {
                    $value = json_decode($value, true);
                } elseif ($item->type === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                return [
                    'id' => $item->id,
                    'key' => $item->key,
                    'value' => $value,
                    'type' => $item->type,
                    'label' => $item->label,
                    'description' => $item->description,
                    'is_public' => $item->is_public,
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    /**
     * Récupérer les paramètres d'un groupe
     */
    public function group(string $group): JsonResponse
    {
        $settings = SiteSetting::where('group', $group)->orderBy('key')->get();

        $data = [];
        foreach ($settings as $setting) {
            $value = $setting->value;
            
            if ($setting->type === 'json' && $value) {
                $value = json_decode($value, true);
            } elseif ($setting->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $data[$setting->key] = [
                'id' => $setting->id,
                'value' => $value,
                'type' => $setting->type,
                'label' => $setting->label,
                'description' => $setting->description,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Mettre à jour plusieurs paramètres
     */
    public function updateBatch(Request $request, FormationSessionSchedulerService $scheduler): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        $updated = [];

        foreach ($request->settings as $item) {
            $setting = SiteSetting::where('key', $item['key'])->first();
            
            if ($setting) {
                $value = $item['value'];

                // Encoder en JSON si nécessaire
                if ($setting->type === 'json' && is_array($value)) {
                    $value = json_encode($value);
                } elseif ($setting->type === 'boolean') {
                    $value = $value ? '1' : '0';
                }

                $setting->update(['value' => $value]);
                $updated[] = $setting->key;
            }
        }

        // Vider le cache des paramètres
        Cache::forget('site_settings_public');

        if ($this->shouldRegenerateSessions($updated)) {
            $scheduler->ensureUpcomingSessions();
        }

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' paramètre(s) mis à jour',
            'data' => ['updated_keys' => $updated]
        ]);
    }

    /**
     * Mettre à jour un paramètre spécifique
     */
    public function update(Request $request, string $key, FormationSessionSchedulerService $scheduler): JsonResponse
    {
        $setting = SiteSetting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètre non trouvé'
            ], 404);
        }

        $value = $request->input('value');

        // Gérer l'upload d'image
        if ($setting->type === 'image' && $request->hasFile('value')) {
            $file = $request->file('value');
            
            // Validation de l'image
            $request->validate([
                'value' => 'required|image|mimes:jpeg,jpg,png,svg,ico|max:2048'
            ]);
            
            // Déterminer le sous-dossier selon le type d'image
            $folder = 'settings';
            if (str_contains($key, 'logo')) {
                $folder = 'settings/logos';
            } elseif (str_contains($key, 'favicon') || str_contains($key, 'icon')) {
                $folder = 'settings/icons';
            } elseif (str_contains($key, 'og_image') || str_contains($key, 'banner')) {
                $folder = 'settings/banners';
            }
            
            $path = $file->store($folder, 'public');
            $value = Storage::url($path);

            // Supprimer l'ancienne image si elle existe
            if ($setting->value) {
                $oldPath = str_replace('/storage/', '', $setting->value);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        } elseif ($setting->type === 'json' && is_array($value)) {
            $value = json_encode($value);
        } elseif ($setting->type === 'boolean') {
            $value = $value ? '1' : '0';
        }

        $setting->update(['value' => $value]);

        // Vider le cache
        Cache::forget('site_settings_public');

        if ($this->shouldRegenerateSessions([$setting->key])) {
            $scheduler->ensureUpcomingSessions();
        }

        return response()->json([
            'success' => true,
            'message' => 'Paramètre mis à jour',
            'data' => $setting->fresh()
        ]);
    }

    /**
     * Upload d'une image pour un paramètre spécifique (endpoint dédié)
     */
    public function uploadImage(Request $request, string $key): JsonResponse
    {
        $setting = SiteSetting::where('key', $key)->where('type', 'image')->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètre image non trouvé'
            ], 404);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,svg,ico|max:2048'
        ]);

        $file = $request->file('image');
        
        // Déterminer le sous-dossier selon le type d'image
        $folder = 'settings';
        if (str_contains($key, 'logo')) {
            $folder = 'settings/logos';
        } elseif (str_contains($key, 'favicon') || str_contains($key, 'icon')) {
            $folder = 'settings/icons';
        } elseif (str_contains($key, 'og_image') || str_contains($key, 'banner')) {
            $folder = 'settings/banners';
        }
        
        $path = $file->store($folder, 'public');
        $url = Storage::url($path);

        // Supprimer l'ancienne image si elle existe
        if ($setting->value) {
            $oldPath = str_replace('/storage/', '', $setting->value);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $setting->update(['value' => $url]);
        Cache::forget('site_settings_public');

        return response()->json([
            'success' => true,
            'message' => 'Image uploadée avec succès',
            'data' => [
                'key' => $setting->key,
                'url' => $url,
                'full_url' => asset('storage/' . $path)
            ]
        ]);
    }

    /**
     * Créer un nouveau paramètre (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:site_settings,key|max:255',
            'value' => 'nullable',
            'type' => 'required|in:text,textarea,image,boolean,json',
            'group' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_public' => 'boolean',
        ]);

        if ($validated['type'] === 'json' && is_array($validated['value'])) {
            $validated['value'] = json_encode($validated['value']);
        }

        $setting = SiteSetting::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Paramètre créé',
            'data' => $setting
        ], 201);
    }

    /**
     * Supprimer un paramètre (admin)
     */
    public function destroy(string $key): JsonResponse
    {
        $setting = SiteSetting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètre non trouvé'
            ], 404);
        }

        // Supprimer l'image associée si nécessaire
        if ($setting->type === 'image' && $setting->value) {
            $path = str_replace('/storage/', '', $setting->value);
            Storage::disk('public')->delete($path);
        }

        $setting->delete();

        Cache::forget('site_settings_public');

        return response()->json([
            'success' => true,
            'message' => 'Paramètre supprimé'
        ]);
    }

    /**
     * Activer/désactiver le mode maintenance
     */
    public function toggleMaintenance(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled', false);
        $message = $request->input('message', 'Le site est en maintenance. Veuillez réessayer plus tard.');

        SiteSetting::set('maintenance_mode', $enabled ? '1' : '0');
        SiteSetting::set('maintenance_message', $message);

        Cache::forget('site_settings_public');

        ActivityLogService::log(
            action: $enabled ? 'Mode maintenance activé' : 'Mode maintenance désactivé',
            description: $enabled
                ? "Le mode maintenance a été activé pour informer les visiteurs que la plateforme est temporairement indisponible."
                : 'Le mode maintenance a été désactivé et la plateforme est de nouveau accessible.',
            subject: null,
            userId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => $enabled ? 'Mode maintenance activé' : 'Mode maintenance désactivé',
            'data' => [
                'maintenance_mode' => $enabled,
                'maintenance_message' => $message
            ]
        ]);
    }

    /**
     * Envoyer un email de test pour valider la configuration SMTP
     */
    public function testEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => 'nullable|email|max:255',
            'mail_mailer' => 'nullable|string|max:50',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|string|max:20',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
        ]);

        $storedEmailSettings = SiteSetting::getGroup('email');

        $resolvedSettings = [
            'mail_mailer' => $validated['mail_mailer'] ?? ($storedEmailSettings['mail_mailer'] ?? config('mail.default')),
            'mail_host' => $validated['mail_host'] ?? ($storedEmailSettings['mail_host'] ?? config('mail.mailers.smtp.host')),
            'mail_port' => $validated['mail_port'] ?? ($storedEmailSettings['mail_port'] ?? config('mail.mailers.smtp.port')),
            'mail_username' => $validated['mail_username'] ?? ($storedEmailSettings['mail_username'] ?? config('mail.mailers.smtp.username')),
            'mail_password' => $validated['mail_password'] ?? ($storedEmailSettings['mail_password'] ?? config('mail.mailers.smtp.password')),
            'mail_encryption' => $validated['mail_encryption'] ?? ($storedEmailSettings['mail_encryption'] ?? config('mail.mailers.smtp.encryption')),
            'mail_from_address' => $validated['mail_from_address'] ?? ($storedEmailSettings['mail_from_address'] ?? config('mail.from.address')),
            'mail_from_name' => $validated['mail_from_name'] ?? ($storedEmailSettings['mail_from_name'] ?? config('mail.from.name')),
        ];

        $recipient = $validated['recipient']
            ?? $resolvedSettings['mail_from_address']
            ?? $request->user()?->email;

        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun destinataire disponible pour le test email.',
            ], 422);
        }

        try {
            if (!empty($resolvedSettings['mail_mailer'])) {
                Config::set('mail.default', $resolvedSettings['mail_mailer']);
            }

            if (!empty($resolvedSettings['mail_host'])) {
                Config::set('mail.mailers.smtp.host', $resolvedSettings['mail_host']);
            }

            if (!empty($resolvedSettings['mail_port'])) {
                Config::set('mail.mailers.smtp.port', (int) $resolvedSettings['mail_port']);
            }

            if (!empty($resolvedSettings['mail_username'])) {
                Config::set('mail.mailers.smtp.username', $resolvedSettings['mail_username']);
            }

            if (array_key_exists('mail_password', $resolvedSettings) && $resolvedSettings['mail_password'] !== null) {
                Config::set('mail.mailers.smtp.password', $resolvedSettings['mail_password']);
            }

            if (array_key_exists('mail_encryption', $resolvedSettings)) {
                $encryption = strtolower(trim((string) $resolvedSettings['mail_encryption']));
                Config::set(
                    'mail.mailers.smtp.encryption',
                    in_array($encryption, ['', 'null', 'none'], true) ? null : $encryption
                );
            }

            if (!empty($resolvedSettings['mail_from_address'])) {
                Config::set('mail.from.address', $resolvedSettings['mail_from_address']);
            }

            if (!empty($resolvedSettings['mail_from_name'])) {
                Config::set('mail.from.name', $resolvedSettings['mail_from_name']);
            }

            Mail::raw(
                'Ceci est un email de test envoye depuis le panneau admin MBC le ' . now()->format('d/m/Y H:i:s'),
                static function ($message) use ($recipient): void {
                    $message
                        ->to($recipient)
                        ->subject('Test configuration email - MBC');
                }
            );

            ActivityLogService::log(
                action: 'Test email envoye',
                description: "Test SMTP envoye a {$recipient}",
                subject: null,
                userId: $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Email de test envoye avec succes a {$recipient}.",
                'data' => [
                    'recipient' => $recipient,
                    'sent_at' => now()->toISOString(),
                ],
            ]);
        } catch (\Throwable $e) {
            ActivityLogService::log(
                action: 'Echec test email',
                description: 'Test SMTP en erreur: ' . Str::limit($e->getMessage(), 280, '...'),
                subject: null,
                userId: $request->user()?->id
            );

            return response()->json([
                'success' => false,
                'message' => 'Echec envoi email de test: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function shouldRegenerateSessions(array $keys): bool
    {
        return !empty(array_intersect($keys, self::AUTO_SESSION_SETTING_KEYS));
    }
}
