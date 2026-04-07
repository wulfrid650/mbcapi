<?php

namespace App\Services;

use App\Models\PortfolioProject;
use App\Models\DailyLog;
use App\Models\Media;
use App\Models\SafetyIncident;
use App\Models\ProjectUpdate;
use App\Models\ProjectReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\ChantierUpdate;

/**
 * Service pour la gestion des chantiers
 */
class ChantierService
{
    /**
     * Obtenir les statistiques d'un chantier
     */
    public function getProjectStats(PortfolioProject $project): array
    {
        return [
            'progress' => $project->progress ?? 0,
            'days_elapsed' => $project->start_date 
                ? now()->diffInDays($project->start_date) 
                : 0,
            'days_remaining' => $project->expected_end_date 
                ? now()->diffInDays($project->expected_end_date, false) 
                : null,
            'updates_count' => $project->progressUpdates()->count(),
            'incidents_count' => SafetyIncident::where('project_id', $project->id)->count(),
            'unresolved_incidents' => SafetyIncident::where('project_id', $project->id)
                ->unresolved()
                ->count(),
            'daily_logs_count' => DailyLog::where('project_id', $project->id)->count(),
            'reports_count' => ProjectReport::where('portfolio_project_id', $project->id)->count(),
        ];
    }

    /**
     * Créer un log journalier
     */
    public function createDailyLog(array $data, int $userId): DailyLog
    {
        $photos = $this->storeUploadedPhotos($data['photos'] ?? [], 'daily-logs');

        $payload = [
            'project_id' => $data['project_id'],
            'date' => $data['date'],
            DailyLog::authorColumn() => $userId,
        ];

        $payload['notes'] = DailyLog::buildCompatibleNotes($data['notes'] ?? null, [
            'weather' => $data['weather'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'workforce_count' => $data['workforce_count'] ?? 0,
            'workers_present' => $data['workers_present'] ?? [],
            'workers_absent' => $data['workers_absent'] ?? [],
            'work_performed' => $data['work_performed'] ?? null,
            'materials_used' => $data['materials_used'] ?? [],
            'equipment_used' => $data['equipment_used'] ?? [],
            'issues' => $data['issues'] ?? null,
            'photos' => $photos,
        ]);

        $this->setIfColumnExists($payload, DailyLog::class, 'weather', $data['weather'] ?? null);
        $this->setIfColumnExists($payload, DailyLog::class, 'temperature', $data['temperature'] ?? null);
        $this->setIfColumnExists($payload, DailyLog::class, 'workforce_count', $data['workforce_count'] ?? 0);
        $this->setIfColumnExists($payload, DailyLog::class, 'workers_present', $data['workers_present'] ?? []);
        $this->setIfColumnExists($payload, DailyLog::class, 'workers_absent', $data['workers_absent'] ?? []);
        $this->setIfColumnExists($payload, DailyLog::class, 'work_performed', $data['work_performed'] ?? null);
        $this->setIfColumnExists($payload, DailyLog::class, 'materials_used', $data['materials_used'] ?? []);
        $this->setIfColumnExists($payload, DailyLog::class, 'equipment_used', $data['equipment_used'] ?? []);
        $this->setIfColumnExists($payload, DailyLog::class, 'issues', $data['issues'] ?? null);
        $this->setIfColumnExists($payload, DailyLog::class, 'photos', $photos);

        $dailyLog = DailyLog::create($payload);

        $this->persistMedia($photos, [
            'daily_log_id' => $dailyLog->id,
            'type' => 'DURING',
        ]);

        return $dailyLog->fresh()->loadMissing(['project', 'creator']);
    }

    /**
     * Signaler un incident de sécurité
     */
    public function reportIncident(array $data, int $userId): SafetyIncident
    {
        $photos = $this->storeUploadedPhotos($data['photos'] ?? [], 'safety-incidents');
        $severity = SafetyIncident::normalizeSeverityForStorage($data['severity'] ?? null) ?? 'LOW';

        $payload = [
            'project_id' => $data['project_id'],
            'date' => $data['date'],
            'description' => SafetyIncident::buildCompatibleDescription($data['description'], [
                'title' => $data['title'] ?? null,
                'time' => $data['time'] ?? null,
                'type' => $data['type'] ?? null,
                'location' => $data['location'] ?? null,
                'persons_involved' => $data['persons_involved'] ?? [],
                'injuries' => $data['injuries'] ?? [],
                'actions_taken' => $data['actions_taken'] ?? null,
                'preventive_measures' => $data['preventive_measures'] ?? null,
                'witnesses' => $data['witnesses'] ?? [],
                'photos' => $photos,
            ]),
            'status' => 'open',
            SafetyIncident::reporterColumn() => $userId,
        ];

        $this->setIfColumnExists($payload, SafetyIncident::class, 'daily_log_id', $data['daily_log_id'] ?? null);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'time', $data['time'] ?? null);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'type', $data['type'] ?? null);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'severity', $severity);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'title', $data['title'] ?? null);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'location', $data['location'] ?? null);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'persons_involved', $data['persons_involved'] ?? []);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'injuries', $data['injuries'] ?? []);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'actions_taken', $data['actions_taken'] ?? null);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'preventive_measures', $data['preventive_measures'] ?? null);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'witnesses', $data['witnesses'] ?? []);
        $this->setIfColumnExists($payload, SafetyIncident::class, 'photos', $photos);

        $incident = SafetyIncident::create($payload);

        $this->persistMedia($photos, [
            'safety_incident_id' => $incident->id,
            'type' => 'DOCUMENT',
        ]);

        // Notifier l'admin pour les incidents graves
        if (in_array($data['severity'], ['serious', 'critical'])) {
            $this->notifyAdminOfIncident($incident);
        }

        return $incident->fresh()->loadMissing(['project', 'reporter']);
    }

    /**
     * Résoudre un incident
     */
    public function resolveIncident(SafetyIncident $incident, array $data, int $userId): SafetyIncident
    {
        $updateData = [
            'status' => 'resolved',
        ];

        $this->setIfColumnExists(
            $updateData,
            SafetyIncident::class,
            'preventive_measures',
            $data['preventive_measures'] ?? $incident->preventive_measures
        );
        $this->setIfColumnExists($updateData, SafetyIncident::class, 'resolved_by', $userId);
        $this->setIfColumnExists($updateData, SafetyIncident::class, 'resolved_at', now());

        $incident->update($updateData);

        return $incident;
    }

    /**
     * Mettre à jour la progression d'un chantier
     */
    public function updateProgress(PortfolioProject $project, int $progress, ?string $note = null, ?int $userId = null): PortfolioProject
    {
        $project->update([
            'progress' => $progress,
        ]);

        // Créer une mise à jour automatique
        if ($note || true) {
            $user = $userId ? User::find($userId) : null;
            
            ProjectUpdate::create([
                'portfolio_project_id' => $project->id,
                'title' => 'Progression mise à jour',
                'description' => $note ?? "Progression mise à jour à {$progress}%",
                'date' => now(),
                'status' => $progress >= 100 ? 'Publié' : 'Brouillon',
                'author_name' => $user?->name ?? 'Système',
                'images_count' => 0,
            ]);
        }

        // Notifier le client
        $this->notifyClientOfUpdate($project, "Progression mise à jour à {$progress}%");

        return $project;
    }

    /**
     * Notifier le client d'une mise à jour
     */
    public function notifyClientOfUpdate(PortfolioProject $project, string $message): void
    {
        $clientEmail = $project->client_email;
        
        if (!$clientEmail && $project->client_id) {
            $client = User::find($project->client_id);
            $clientEmail = $client?->email;
        }

        if (!$clientEmail) {
            return;
        }

        $clientName = $project->client ?? 'Client';
        $clientObj = new \stdClass();
        $clientObj->first_name = $clientName;
        $clientObj->email = $clientEmail;

        try {
            Mail::to($clientEmail)->send(new ChantierUpdate($project, $clientObj, $message));
        } catch (\Exception $e) {
            \Log::error('Failed to send chantier update email: ' . $e->getMessage());
        }
    }

    /**
     * Notifier l'admin d'un incident grave
     */
    private function notifyAdminOfIncident(SafetyIncident $incident): void
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new \App\Mail\SafetyIncidentAlert($incident));
            } catch (\Exception $e) {
                \Log::error('Failed to send safety incident alert: ' . $e->getMessage());
            }
        }
    }

    /**
     * Obtenir le récapitulatif hebdomadaire d'un chantier
     */
    public function getWeeklySummary(PortfolioProject $project, ?\Carbon\Carbon $weekStart = null): array
    {
        $start = $weekStart ?? now()->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $dailyLogs = DailyLog::where('project_id', $project->id)
            ->dateRange($start, $end)
            ->get();

        $incidents = SafetyIncident::where('project_id', $project->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $updates = ProjectUpdate::where('portfolio_project_id', $project->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        return [
            'period' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'daily_logs' => $dailyLogs->count(),
            'total_workforce' => $dailyLogs->sum('workforce_count'),
            'average_workforce' => $dailyLogs->avg('workforce_count'),
            'incidents' => [
                'total' => $incidents->count(),
                'by_severity' => $incidents->groupBy('severity')->map->count(),
            ],
            'updates' => $updates->count(),
            'work_summary' => $dailyLogs->pluck('work_performed')->filter()->values(),
            'issues' => $dailyLogs->pluck('issues')->filter()->values(),
        ];
    }

    private function storeUploadedPhotos(array $photos, string $directory): array
    {
        $urls = [];

        foreach ($photos as $photo) {
            $path = $photo->store($directory, 'public');
            $urls[] = Storage::url($path);
        }

        return $urls;
    }

    private function persistMedia(array $urls, array $baseAttributes): void
    {
        if ($urls === []) {
            return;
        }

        foreach ($urls as $url) {
            Media::create(array_merge($baseAttributes, ['url' => $url]));
        }
    }

    private function setIfColumnExists(array &$payload, string $modelClass, string $column, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if ($modelClass::hasTableColumn($column)) {
            $payload[$column] = $value;
        }
    }
}
