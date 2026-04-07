<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres du site configurables par l'admin
 */
class SiteSetting extends Model
{
    use HasFactory;

    private const SENSITIVE_KEYS = [
        'mail_password',
        'recaptcha_secret_key',
        'moneroo_secret_key',
    ];

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function getValueAttribute($value)
    {
        $key = $this->attributes['key'] ?? null;
        if (!$this->isSensitiveKey($key)) {
            return $value;
        }

        return $this->maybeDecrypt($value);
    }

    public function setValueAttribute($value): void
    {
        $key = $this->attributes['key'] ?? null;

        if ($this->isSensitiveKey($key) && $value !== null && $value !== '') {
            if (!$this->isEncrypted($value)) {
                $this->attributes['value'] = encrypt((string) $value);
                return;
            }
        }

        $this->attributes['value'] = $value;
    }

    private function isSensitiveKey(?string $key): bool
    {
        return $key !== null && in_array($key, self::SENSITIVE_KEYS, true);
    }

    private function isEncrypted($value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        try {
            decrypt($value);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function maybeDecrypt($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Récupérer une valeur de paramètre par sa clé
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        // Décoder si c'est du JSON
        if ($setting->type === 'json') {
            return json_decode($setting->value, true) ?? $default;
        }

        // Convertir en boolean si nécessaire
        if ($setting->type === 'boolean') {
            return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
        }

        return $setting->value ?? $default;
    }

    /**
     * Définir une valeur de paramètre
     */
    public static function set(string $key, $value): void
    {
        $setting = static::where('key', $key)->first();
        
        if ($setting) {
            $setting->value = is_array($value) ? json_encode($value) : $value;
            $setting->save();
        }
    }

    /**
     * Récupérer tous les paramètres d'un groupe
     */
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            })
            ->toArray();
    }

    /**
     * Récupérer tous les paramètres publics
     */
    public static function getPublic(): array
    {
        $settings = static::where('is_public', true)->get();
        $result = [];

        foreach ($settings as $setting) {
            $value = $setting->value;
            
            if ($setting->type === 'json') {
                $value = json_decode($value, true);
            } elseif ($setting->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $result[$setting->key] = $value;
        }

        return $result;
    }
}
