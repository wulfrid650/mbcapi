<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Modèle pour les incidents de sécurité sur chantier
 */
class SafetyIncident extends Model
{
    protected const COMPAT_META_PREFIX = '[MBC_INCIDENT_META]';

    protected static array $schemaColumnCache = [];

    protected $fillable = [
        'project_id',
        'daily_log_id',
        'date',
        'time',
        'type',
        'severity',
        'title',
        'description',
        'location',
        'persons_involved',
        'injuries',
        'actions_taken',
        'preventive_measures',
        'witnesses',
        'photos',
        'status',
        'reporter_id',
        'reported_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime',
        'persons_involved' => 'array',
        'injuries' => 'array',
        'witnesses' => 'array',
        'photos' => 'array',
        'resolved_at' => 'datetime',
    ];

    protected $appends = [
        'title',
        'time',
        'type',
        'location',
        'persons_involved',
        'injuries',
        'actions_taken',
        'preventive_measures',
        'witnesses',
        'photos',
        'type_label',
        'severity_label',
    ];

    /**
     * Types d'incidents
     */
    const TYPES = [
        'accident' => 'Accident de travail',
        'near_miss' => 'Presque accident',
        'unsafe_act' => 'Acte dangereux',
        'unsafe_condition' => 'Condition dangereuse',
        'equipment_failure' => 'Défaillance équipement',
        'environmental' => 'Incident environnemental',
        'security' => 'Incident de sécurité',
        'other' => 'Autre',
    ];

    /**
     * Niveaux de gravité
     */
    const SEVERITIES = [
        'minor' => 'Mineur',
        'moderate' => 'Modéré',
        'serious' => 'Grave',
        'critical' => 'Critique',
    ];

    public static function hasTableColumn(string $column): bool
    {
        $table = (new static())->getTable();

        if (!array_key_exists($table, static::$schemaColumnCache)) {
            static::$schemaColumnCache[$table] = array_flip(Schema::getColumnListing($table));
        }

        return isset(static::$schemaColumnCache[$table][$column]);
    }

    public static function reporterColumn(): string
    {
        return static::hasTableColumn('reporter_id') ? 'reporter_id' : 'reported_by';
    }

    public static function normalizeSeverityForStorage(?string $severity): ?string
    {
        if ($severity === null) {
            return null;
        }

        return match (strtolower($severity)) {
            'low', 'minor' => 'LOW',
            'medium', 'moderate' => 'MEDIUM',
            'high', 'serious' => 'HIGH',
            'critical' => 'CRITICAL',
            default => $severity,
        };
    }

    public static function buildCompatibleDescription(string $description, array $metadata): string
    {
        $filtered = array_filter($metadata, static function ($value) {
            return $value !== null && $value !== [] && $value !== '';
        });

        if ($filtered === []) {
            return $description;
        }

        $json = json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return static::COMPAT_META_PREFIX . $json . "\n\n" . trim($description);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PortfolioProject::class, 'project_id');
    }

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, static::reporterColumn());
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    protected function compatibilityMeta(): array
    {
        $rawDescription = $this->getRawOriginal('description');

        if (!is_string($rawDescription) || !str_starts_with($rawDescription, static::COMPAT_META_PREFIX)) {
            return [];
        }

        $payload = substr($rawDescription, strlen(static::COMPAT_META_PREFIX));
        $parts = preg_split("/\R\R/", $payload, 2);
        $json = trim((string) ($parts[0] ?? ''));
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function decodeArrayValue(mixed $value, string $key): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $fallback = data_get($this->compatibilityMeta(), $key, []);

        return is_array($fallback) ? $fallback : [];
    }

    public function getDescriptionAttribute($value): ?string
    {
        if (!is_string($value) || !str_starts_with($value, static::COMPAT_META_PREFIX)) {
            return $value;
        }

        $payload = substr($value, strlen(static::COMPAT_META_PREFIX));
        $parts = preg_split("/\R\R/", $payload, 2);
        $body = isset($parts[1]) ? trim((string) $parts[1]) : '';

        return $body !== '' ? $body : null;
    }

    public function getTitleAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'title');
    }

    public function getTimeAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'time');
    }

    public function getTypeAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'type');
    }

    public function getLocationAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'location');
    }

    public function getPersonsInvolvedAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'persons_involved');
    }

    public function getInjuriesAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'injuries');
    }

    public function getActionsTakenAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'actions_taken');
    }

    public function getPreventiveMeasuresAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'preventive_measures');
    }

    public function getWitnessesAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'witnesses');
    }

    public function getPhotosAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'photos');
    }

    /**
     * Obtenir le label du type
     */
    public function getTypeLabelAttribute(): string
    {
        $type = $this->type;

        return self::TYPES[$type] ?? ($type ?: 'Incident');
    }

    /**
     * Obtenir le label de la gravité
     */
    public function getSeverityLabelAttribute(): string
    {
        return match (strtolower((string) $this->severity)) {
            'low', 'minor' => self::SEVERITIES['minor'],
            'medium', 'moderate' => self::SEVERITIES['moderate'],
            'high', 'serious' => self::SEVERITIES['serious'],
            'critical' => self::SEVERITIES['critical'],
            default => (string) $this->severity,
        };
    }

    /**
     * Vérifier si l'incident est résolu
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function scopeUnresolved($query)
    {
        return $query->whereRaw('LOWER(status) != ?', ['resolved']);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', static::normalizeSeverityForStorage($severity) ?? $severity);
    }
}
