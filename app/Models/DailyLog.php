<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Modèle pour les logs journaliers de chantier
 */
class DailyLog extends Model
{
    protected const COMPAT_META_PREFIX = '[MBC_DAILYLOG_META]';

    protected static array $schemaColumnCache = [];

    protected $fillable = [
        'project_id',
        'date',
        'weather',
        'temperature',
        'workforce_count',
        'workers_present',
        'workers_absent',
        'work_performed',
        'materials_used',
        'equipment_used',
        'issues',
        'notes',
        'safety_incidents',
        'photos',
        'author_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'workers_present' => 'array',
        'workers_absent' => 'array',
        'materials_used' => 'array',
        'equipment_used' => 'array',
        'photos' => 'array',
        'safety_incidents' => 'array',
        'temperature' => 'float',
    ];

    protected $appends = [
        'weather',
        'temperature',
        'workforce_count',
        'workers_present',
        'workers_absent',
        'work_performed',
        'materials_used',
        'equipment_used',
        'issues',
        'photos',
    ];

    public static function hasTableColumn(string $column): bool
    {
        $table = (new static())->getTable();

        if (!array_key_exists($table, static::$schemaColumnCache)) {
            static::$schemaColumnCache[$table] = array_flip(Schema::getColumnListing($table));
        }

        return isset(static::$schemaColumnCache[$table][$column]);
    }

    public static function authorColumn(): string
    {
        return static::hasTableColumn('author_id') ? 'author_id' : 'created_by';
    }

    public static function buildCompatibleNotes(?string $notes, array $metadata): ?string
    {
        $filtered = array_filter($metadata, static function ($value) {
            return $value !== null && $value !== [] && $value !== '';
        });

        if ($filtered === []) {
            return $notes;
        }

        $json = json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = trim((string) $notes);

        return static::COMPAT_META_PREFIX . $json . ($body !== '' ? "\n\n{$body}" : '');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PortfolioProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, static::authorColumn());
    }

    protected function compatibilityMeta(): array
    {
        $rawNotes = $this->getRawOriginal('notes');

        if (!is_string($rawNotes) || !str_starts_with($rawNotes, static::COMPAT_META_PREFIX)) {
            return [];
        }

        $payload = substr($rawNotes, strlen(static::COMPAT_META_PREFIX));
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

    public function getNotesAttribute($value): ?string
    {
        if (!is_string($value) || !str_starts_with($value, static::COMPAT_META_PREFIX)) {
            return $value;
        }

        $payload = substr($value, strlen(static::COMPAT_META_PREFIX));
        $parts = preg_split("/\R\R/", $payload, 2);
        $body = isset($parts[1]) ? trim((string) $parts[1]) : '';

        return $body !== '' ? $body : null;
    }

    public function getWeatherAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'weather');
    }

    public function getTemperatureAttribute($value): ?float
    {
        $resolved = $value !== null ? $value : data_get($this->compatibilityMeta(), 'temperature');

        return $resolved !== null ? (float) $resolved : null;
    }

    public function getWorkforceCountAttribute($value): int
    {
        $resolved = $value !== null ? $value : data_get($this->compatibilityMeta(), 'workforce_count', 0);

        return (int) $resolved;
    }

    public function getWorkersPresentAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'workers_present');
    }

    public function getWorkersAbsentAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'workers_absent');
    }

    public function getWorkPerformedAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'work_performed');
    }

    public function getMaterialsUsedAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'materials_used');
    }

    public function getEquipmentUsedAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'equipment_used');
    }

    public function getIssuesAttribute($value): ?string
    {
        return $value ?? data_get($this->compatibilityMeta(), 'issues');
    }

    public function getPhotosAttribute($value): array
    {
        return $this->decodeArrayValue($value, 'photos');
    }

    /**
     * Scope pour un projet spécifique
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope pour une plage de dates
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
