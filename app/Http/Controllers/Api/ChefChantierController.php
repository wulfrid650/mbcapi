<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConstructionTeam;
use App\Models\DailyLog;
use App\Models\Message;
use App\Models\PortfolioProject;
use App\Models\ProgressUpdate;
use App\Models\ProjectReport;
use App\Models\ProjectUpdate;
use App\Models\SafetyIncident;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ChantierService;
use App\Services\ProjectPhaseWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ChantierUpdate;

class ChefChantierController extends Controller
{
    private ChantierService $chantierService;
    private ProjectPhaseWorkflowService $projectPhaseWorkflowService;

    public function __construct(
        ChantierService $chantierService,
        ProjectPhaseWorkflowService $projectPhaseWorkflowService
    )
    {
        $this->chantierService = $chantierService;
        $this->projectPhaseWorkflowService = $projectPhaseWorkflowService;
    }

    private function isAdminUser(User $user): bool
    {
        return $user->isAdmin() || $user->getActiveRoleSlug() === 'admin' || $user->hasRole('admin');
    }

    private function applyChantierAccessScope(Builder $query, User $user): Builder
    {
        if ($this->isAdminUser($user)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('chef_chantier_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereJsonContains('team_ids', $user->id);
        });
    }

    private function canAccessChantier(User $user, PortfolioProject $project): bool
    {
        if ($this->isAdminUser($user)) {
            return true;
        }

        if ((int) $project->chef_chantier_id === (int) $user->id) {
            return true;
        }

        if ((int) $project->created_by === (int) $user->id) {
            return true;
        }

        $teamIds = is_array($project->team_ids) ? $project->team_ids : [];
        return in_array((int) $user->id, array_map('intval', $teamIds), true);
    }

    private function forbiddenChantierResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Accès refusé pour ce chantier.',
        ], 403);
    }

    private function normalizeProjectStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'planned';
        }

        if ($status === 'pending') {
            return 'planned';
        }

        return $status;
    }

    private function isPendingProjectStatus(?string $status): bool
    {
        return in_array($this->normalizeProjectStatus($status), ['planned', 'on_hold'], true);
    }

    private function isActiveTeamStatus(?string $status): bool
    {
        return in_array(Str::lower(trim((string) $status)), ['active', 'actif'], true);
    }

    private function dashboardProjectStatusLabel(?string $status): string
    {
        return match ($this->normalizeProjectStatus($status)) {
            'completed' => 'Terminé',
            'in_progress' => 'En cours',
            'on_hold' => 'En pause',
            default => 'Planifié',
        };
    }

    private function extractProjectMetadata(PortfolioProject $project): array
    {
        return is_array($project->metadata) ? $project->metadata : [];
    }

    private function normalizeProjectPriority(?string $priority): string
    {
        $normalized = Str::lower(trim((string) $priority));

        return in_array($normalized, ['low', 'medium', 'high'], true)
            ? $normalized
            : 'medium';
    }

    private function extractConstructionTeamIdsFromMetadata(array $metadata): array
    {
        $teamIds = is_array($metadata['construction_team_ids'] ?? null)
            ? $metadata['construction_team_ids']
            : [];

        return collect($teamIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function buildProjectMetadata(array $existingMetadata, array $validated): array
    {
        $metadata = $existingMetadata;

        if (array_key_exists('priority', $validated)) {
            $metadata['priority'] = $this->normalizeProjectPriority($validated['priority']);
        } elseif (!array_key_exists('priority', $metadata)) {
            $metadata['priority'] = 'medium';
        }

        if (array_key_exists('construction_team_ids', $validated)) {
            $metadata['construction_team_ids'] = $this->extractConstructionTeamIdsFromMetadata([
                'construction_team_ids' => $validated['construction_team_ids'] ?? [],
            ]);
        } elseif (!array_key_exists('construction_team_ids', $metadata)) {
            $metadata['construction_team_ids'] = [];
        }

        return $metadata;
    }

    private function countAssignedTeams(PortfolioProject $project): int
    {
        $metadata = $this->extractProjectMetadata($project);
        $constructionTeamIds = $this->extractConstructionTeamIdsFromMetadata($metadata);

        if (!empty($constructionTeamIds)) {
            return count($constructionTeamIds);
        }

        $userTeamIds = is_array($project->team_ids) ? $project->team_ids : [];

        return count($userTeamIds);
    }

    private function transformProject(PortfolioProject $project): array
    {
        $metadata = $this->extractProjectMetadata($project);
        $constructionTeamIds = $this->extractConstructionTeamIdsFromMetadata($metadata);
        $constructionTeams = empty($constructionTeamIds)
            ? collect()
            : ConstructionTeam::query()
                ->whereIn('id', $constructionTeamIds)
                ->orderBy('name')
                ->get(['id', 'name']);

        $data = $project->toArray();
        $data['priority'] = $this->normalizeProjectPriority($metadata['priority'] ?? null);
        $data['construction_team_ids'] = $constructionTeamIds;
        $data['construction_teams'] = $constructionTeams
            ->map(fn ($team) => ['id' => $team->id, 'name' => $team->name])
            ->values()
            ->all();

        return $data;
    }

    private function constructionTeamProjectCounts(): array
    {
        $counts = [];

        PortfolioProject::query()
            ->select(['id', 'metadata'])
            ->chunk(200, function ($projects) use (&$counts) {
                foreach ($projects as $project) {
                    foreach ($this->extractConstructionTeamIdsFromMetadata($this->extractProjectMetadata($project)) as $teamId) {
                        $counts[$teamId] = ($counts[$teamId] ?? 0) + 1;
                    }
                }
            });

        return $counts;
    }

    private function isPublishedUpdateStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        $normalized = Str::lower(trim($status));
        return in_array($normalized, ['publié', 'publie', 'published'], true);
    }

    private function syncProjectStatusFromAvancement(PortfolioProject $project, ?int $progress, ?string $updateStatus): void
    {
        $nextStatus = null;
        $nextProgress = $progress;

        if ($nextProgress !== null && $nextProgress >= 100) {
            $nextStatus = 'completed';
        } elseif ($this->isPublishedUpdateStatus($updateStatus) && in_array($project->status, ['planned', 'pending'], true)) {
            $nextStatus = 'in_progress';
        }

        if ($nextStatus === null && $nextProgress === null) {
            return;
        }

        $payload = [];
        if ($nextStatus !== null) {
            $payload['status'] = $nextStatus;
        }
        if ($nextProgress !== null) {
            $payload['progress'] = $nextProgress;
        }

        $project->update($payload);
    }

    /**
     * Dashboard du chef de chantier
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        $chantierQuery = PortfolioProject::query();
        $this->applyChantierAccessScope($chantierQuery, $user);
        $chantiers = $chantierQuery->orderByDesc('updated_at')->get();

        $projectIds = $chantiers->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $updatesQuery = ProjectUpdate::with('project:id,title,status');
        $incidentsQuery = SafetyIncident::with(['project:id,title', 'reporter:id,name']);

        if ($projectIds->isEmpty()) {
            $updatesQuery->whereRaw('1 = 0');
            $incidentsQuery->whereRaw('1 = 0');
        } else {
            $updatesQuery->whereIn('portfolio_project_id', $projectIds);
            $incidentsQuery->whereIn('project_id', $projectIds);
        }

        $updates = $updatesQuery->orderByDesc('date')->get();
        $incidents = $incidentsQuery->orderByDesc('date')->get();
        $teams = ConstructionTeam::query()->orderBy('name')->get();

        $completedCount = $chantiers->filter(
            fn ($project) => $this->normalizeProjectStatus($project->status) === 'completed'
        )->count();
        $inProgressCount = $chantiers->filter(
            fn ($project) => $this->normalizeProjectStatus($project->status) === 'in_progress'
        )->count();
        $pausedCount = $chantiers->filter(
            fn ($project) => $this->normalizeProjectStatus($project->status) === 'on_hold'
        )->count();
        $pendingCount = $chantiers->filter(
            fn ($project) => $this->isPendingProjectStatus($project->status)
        )->count();

        $activeTeams = $teams->filter(fn ($team) => $this->isActiveTeamStatus($team->status));
        $publishedUpdates = $updates->filter(fn ($update) => $this->isPublishedUpdateStatus($update->status));
        $unresolvedIncidents = $incidents->filter(
            fn ($incident) => Str::lower(trim((string) $incident->status)) !== 'resolved'
        );

        // Statistiques
        $stats = [
            'total_chantiers' => $chantiers->count(),
            'chantiers_en_cours' => $inProgressCount,
            'chantiers_termines' => $completedCount,
            'chantiers_en_pause' => $pausedCount,
            'chantiersActifs' => $inProgressCount,
            'equipes' => $activeTeams->sum(fn ($team) => (int) ($team->members_count ?? 0)),
            'avancements' => $publishedUpdates->count(),
            'alertes' => $unresolvedIncidents->count(),
        ];

        // Chantiers actifs
        $activeChantiers = $chantiers->where('status', 'in_progress')
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'progress' => $c->progress ?? 0,
                    'location' => $c->location,
                    'client' => $c->client_name ?? $c->client,
                ];
            })
            ->take(5);

        $projectsData = [[
            'name' => Str::ucfirst(now()->format('M')),
            'completed' => $completedCount,
            'inProgress' => $inProgressCount,
            'pending' => $pendingCount,
        ]];

        $statusData = [
            ['name' => 'Complétés', 'value' => $completedCount],
            ['name' => 'En cours', 'value' => $inProgressCount],
            ['name' => 'En attente', 'value' => $pendingCount],
        ];

        $recentProjects = $chantiers
            ->take(5)
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->title,
                    'progress' => (int) ($project->progress ?? 0),
                    'team' => $this->countAssignedTeams($project),
                    'status' => $this->dashboardProjectStatusLabel($project->status),
                    'priority' => $this->normalizeProjectPriority(
                        $this->extractProjectMetadata($project)['priority'] ?? null
                    ),
                ];
            })
            ->values();

        $recentUpdates = $updates
            ->take(5)
            ->map(function ($update) {
                return [
                    'id' => $update->id,
                    'project' => $update->project->title ?? 'Chantier inconnu',
                    'update' => $update->description ?: $update->title,
                    'date' => $update->date,
                    'author' => $update->author_name ?: 'Chef de chantier',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'projectsData' => $projectsData,
                'statusData' => $statusData,
                'recentProjects' => $recentProjects,
                'recentUpdates' => $recentUpdates,
                'active_chantiers' => $activeChantiers->values(),
                'all_chantiers' => $chantiers
                    ->map(fn (PortfolioProject $project) => $this->transformProject($project))
                    ->values(),
            ],
        ]);
    }

    /**
     * Liste des chantiers
     */
    public function listChantiers(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = PortfolioProject::query();

        $this->applyChantierAccessScope($query, $user);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $chantiers = $query->orderByDesc('updated_at')->paginate(10);
        $chantiers->setCollection(
            $chantiers->getCollection()->map(fn (PortfolioProject $project) => $this->transformProject($project))
        );

        return response()->json([
            'success' => true,
            'data' => $chantiers,
        ]);
    }

    /**
     * Créer un chantier
     */
    public function createChantier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:planned,pending,in_progress,completed,on_hold',
            'progress' => 'nullable|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|string|max:255',
            'priority' => 'nullable|string|in:low,medium,high',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'integer|exists:users,id',
            'chef_chantier_id' => 'nullable|integer|exists:users,id',
            'construction_team_ids' => 'nullable|array',
            'construction_team_ids.*' => 'integer|exists:construction_teams,id',
        ]);

        $user = Auth::user();

        $slugBase = Str::slug($validated['title']) ?: 'chantier';
        $slug = $slugBase;
        $counter = 1;
        while (PortfolioProject::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$counter}";
            $counter++;
        }

        $payload = [
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'category' => $validated['category'] ?? 'Construction',
            'status' => $this->normalizeProjectStatus($validated['status'] ?? 'planned'),
            'progress' => $validated['progress'] ?? 0,
            'start_date' => $validated['start_date'] ?? null,
            'expected_end_date' => $validated['expected_end_date'] ?? null,
            'budget' => $validated['budget'] ?? null,
            'team_ids' => $validated['team_ids'] ?? [],
            'created_by' => $user->id,
            'is_published' => false,
            'is_featured' => false,
            'metadata' => [],
        ];

        if ($user->getActiveRoleSlug() === 'chef_chantier') {
            $payload['chef_chantier_id'] = $user->id;
            $payload['metadata'] = [
                'creation_validation' => [
                    'status' => 'pending',
                    'requested_by' => $user->id,
                    'requested_at' => now()->toIso8601String(),
                    'note' => 'En attente de validation par admin ou secrétaire.',
                ],
            ];
        } else {
            if (!empty($validated['chef_chantier_id'])) {
                $payload['chef_chantier_id'] = (int) $validated['chef_chantier_id'];
            }
            $payload['metadata'] = [
                'creation_validation' => [
                    'status' => 'approved',
                    'validated_by' => $user->id,
                    'validated_at' => now()->toIso8601String(),
                ],
            ];
        }

        $payload['metadata'] = $this->buildProjectMetadata($payload['metadata'], $validated);

        $project = PortfolioProject::create($payload);

        ActivityLogService::logProjectCreated($project, $user);

        return response()->json([
            'success' => true,
            'message' => 'Chantier créé avec succès',
            'data' => $this->transformProject($project),
        ], 201);
    }

    /**
     * Envoyer un message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'chantier_id' => 'nullable|integer|exists:portfolio_projects,id',
            'recipient_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = Auth::user();
        $projectId = $request->integer('chantier_id');

        if ($projectId > 0) {
            $project = PortfolioProject::findOrFail($projectId);
            if (!$this->canAccessChantier($user, $project)) {
                return $this->forbiddenChantierResponse();
            }
        }

        // Create the message
        $message = Message::create([
            'sender_id' => $user->id,
            'recipient_id' => $request->input('recipient_id'),
            'sender_name' => $user->name,
            'subject' => $request->subject,
            'content' => $request->message,
            'portfolio_project_id' => $projectId > 0 ? $projectId : null,
            'type' => 'sent',
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => $message
        ]);
    }

    /**
     * Liste des avancements (Project Updates)
     */
    public function listAvancements(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = ProjectUpdate::with('project:id,title');

        if (!$this->isAdminUser($user)) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user) {
                $this->applyChantierAccessScope($projectQuery, $user);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $updates = $query->orderByDesc('date')->paginate(10);

        // Transformation pour le frontend
        $data = $updates->getCollection()->map(function ($update) {
            return [
                'id' => $update->id,
                'project_id' => $update->portfolio_project_id,
                'project' => $update->project->title ?? 'Chantier Inconnu',
                'title' => $update->title,
                'description' => $update->description,
                'date' => $update->date,
                'author' => $update->author_name,
                'images' => $update->images_count,
                'status' => $update->status,
            ];
        });

        $updates->setCollection($data);

        return response()->json([
            'success' => true,
            'data' => $updates,
        ]);
    }

    /**
     * Liste des équipes (Construction Teams)
     */
    public function listEquipes(Request $request): JsonResponse
    {
        $query = ConstructionTeam::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('leader_name', 'like', "%{$search}%")
                ->orWhere('specialization', 'like', "%{$search}%");
        }

        $teams = $query->orderBy('name')->paginate(10);
        $projectCounts = $this->constructionTeamProjectCounts();

        // Map to frontend expected format
        $data = $teams->getCollection()->map(function ($team) use ($projectCounts) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'leader' => $team->leader_name,
                'members' => $team->members_count,
                'phone' => $team->phone,
                'email' => $team->email,
                'specialization' => $team->specialization,
                'projects' => $projectCounts[$team->id] ?? (int) ($team->projects_count ?? 0),
                'status' => $team->status,
            ];
        });

        $teams->setCollection($data);

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }

    /**
     * Liste des rapports (Project Reports)
     */
    public function listRapports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = ProjectReport::with('project:id,title');

        if (!$this->isAdminUser($user)) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user) {
                $this->applyChantierAccessScope($projectQuery, $user);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $reports = $query->orderByDesc('date')->paginate(10);

        $data = $reports->getCollection()->map(function ($report) {
            return [
                'id' => $report->id,
                'title' => $report->title,
                'project' => $report->project->title ?? 'Chantier Inconnu',
                'period' => $report->period,
                'author' => $report->author_name,
                'date' => $report->date,
                'type' => $report->type,
                'status' => $report->status,
                'pages' => $report->pages_count,
                'file_url' => $report->file_path ? Storage::url($report->file_path) : null,
            ];
        });

        $reports->setCollection($data);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Liste des messages
     */
    public function listMessages(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = Message::with(['project:id,title']);

        if (!$this->isAdminUser($user)) {
            $query->where(function ($messageQuery) use ($user) {
                $messageQuery->where('recipient_id', $user->id)
                    ->orWhere('sender_id', $user->id)
                    ->orWhere('sender_name', $user->name)
                    ->orWhereHas('project', function (Builder $projectQuery) use ($user) {
                        $this->applyChantierAccessScope($projectQuery, $user);
                    });
            });
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $messages = $query->orderByDesc('created_at')->paginate(10);

        $data = $messages->getCollection()->map(function ($msg) {
            // Determine time from created_at
            $date = \Carbon\Carbon::parse($msg->created_at);
            return [
                'id' => $msg->id,
                'sender' => $msg->sender_name ?? 'Inconnu',
                'project' => $msg->project->title ?? 'Général',
                'subject' => $msg->subject,
                'message' => $msg->content,
                'date' => $date->format('Y-m-d'),
                'time' => $date->format('H:i'),
                'read' => !is_null($msg->read_at),
                'type' => $msg->type,
            ];
        });

        $messages->setCollection($data);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }
    /**
     * Créer un avancement (Project Update)
     */
    public function createAvancement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:portfolio_projects,id',
            'chantier_id' => 'nullable|exists:portfolio_projects,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
            'status' => 'required|string',
            'progress' => 'nullable|integer|min:0|max:100',
            'photos.*' => 'nullable|image|max:10240',
        ]);

        $user = Auth::user();
        $projectId = (int) ($validated['project_id'] ?? $validated['chantier_id'] ?? 0);
        if ($projectId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Le chantier est requis.',
            ], 422);
        }

        $project = PortfolioProject::findOrFail($projectId);
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        // Handle photos upload
        $photos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('project-updates', 'public');
                $photos[] = Storage::url($path);
            }
        }

        $update = ProjectUpdate::create([
            'portfolio_project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'date' => $validated['date'],
            'status' => $validated['status'],
            'author_name' => $user->name,
            'images_count' => count($photos),
        ]);

        // Persist side dedicated to client tracking timeline.
        ProgressUpdate::create([
            'portfolio_project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'progress' => $validated['progress'] ?? (int) ($project->progress ?? 0),
            'photos' => $photos,
        ]);

        $this->syncProjectStatusFromAvancement(
            $project,
            array_key_exists('progress', $validated) ? (int) $validated['progress'] : null,
            $validated['status'] ?? null
        );

        // Notify Client
        $clientEmail = $project->client_email;
        if (!$clientEmail && $project->client_id) {
            $client = User::find($project->client_id);
            $clientEmail = $client ? $client->email : null;
        }

        if ($clientEmail) {
            $clientName = $project->client ?? 'Client';
            $clientObj = new \stdClass();
            $clientObj->first_name = $clientName;
            $clientObj->email = $clientEmail;

            try {
                Mail::to($clientEmail)->send(new ChantierUpdate($project, $clientObj, $validated['description']));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send chantier update email: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Avancement créé avec succès',
            'data' => [
                ...$update->toArray(),
                'project_id' => $project->id,
                'photos' => $photos,
            ],
        ], 201);
    }

    /**
     * Détails d'un avancement
     */
    public function getAvancement(int $id): JsonResponse
    {
        $update = ProjectUpdate::with('project')->findOrFail($id);
        $user = Auth::user();

        if (!$update->project || !$this->canAccessChantier($user, $update->project)) {
            return $this->forbiddenChantierResponse();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $update->id,
                'project_id' => $update->portfolio_project_id,
                'project' => $update->project?->title,
                'title' => $update->title,
                'description' => $update->description,
                'date' => $update->date,
                'author' => $update->author_name,
                'images' => $update->images_count,
                'status' => $update->status,
            ],
        ]);
    }

    /**
     * Mettre à jour un avancement (ex: publier un brouillon)
     */
    public function updateAvancement(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'status' => 'nullable|string|in:Publié,Brouillon,Archivé',
        ]);

        $update = ProjectUpdate::with('project')->findOrFail($id);
        $user = Auth::user();

        if (!$update->project || !$this->canAccessChantier($user, $update->project)) {
            return $this->forbiddenChantierResponse();
        }

        if (!empty($validated)) {
            $update->update($validated);
        }

        $this->syncProjectStatusFromAvancement($update->project, null, $validated['status'] ?? null);

        if ($this->isPublishedUpdateStatus($validated['status'] ?? null)) {
            $this->chantierService->notifyClientOfUpdate(
                $update->project,
                $validated['description'] ?? $update->description ?? 'Nouvel avancement publié'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Avancement mis à jour avec succès',
            'data' => [
                'id' => $update->id,
                'project_id' => $update->portfolio_project_id,
                'project' => $update->project?->title,
                'title' => $update->title,
                'description' => $update->description,
                'date' => $update->date,
                'author' => $update->author_name,
                'images' => $update->images_count,
                'status' => $update->status,
            ],
        ]);
    }

    /**
     * Créer un rapport (Project Report)
     */
    public function createRapport(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|exists:portfolio_projects,id',
            'title' => 'required|string|max:255',
            'period' => 'required|string',
            'date' => 'required|date',
            'type' => 'required|in:daily,weekly,monthly,incident',
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $user = Auth::user();
        $project = PortfolioProject::findOrFail($request->project_id);

        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $path = $request->file('file')->store('project-reports', 'public');

        $report = ProjectReport::create([
            'portfolio_project_id' => $request->project_id,
            'title' => $request->title,
            'period' => $request->period,
            'date' => $request->date,
            'type' => $request->type,
            'status' => 'submitted',
            'author_name' => $user->name,
            'file_path' => $path,
            'pages_count' => 1, // Placeholder
        ]);

        // Notify Client (Optional for reports? User said "avancement du chantier" which usually means updates, but reports are similar. Let's include it for "mise a jour")
        if ($project) {
            // Determine client email
            $clientEmail = $project->client_email;
            if (!$clientEmail && $project->client_id) {
                $client = User::find($project->client_id);
                $clientEmail = $client ? $client->email : null;
            }

            if ($clientEmail) {
                $clientName = $project->client_name ?? 'Client';
                $clientObj = new \stdClass();
                $clientObj->first_name = $clientName;
                $clientObj->email = $clientEmail;

                try {
                    // Using same Mailable but maybe different note
                    Mail::to($clientEmail)->send(new ChantierUpdate($project, $clientObj, "Nouveau rapport disponible : " . $request->title));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send report update email: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapport créé avec succès',
            'data' => $report,
        ], 201);
    }

    /**
     * Obtenir les statistiques détaillées d'un chantier
     */
    public function getChantierStats(int $id): JsonResponse
    {
        $project = PortfolioProject::findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $stats = $this->chantierService->getProjectStats($project);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Créer un log journalier
     */
    public function createDailyLog(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|exists:portfolio_projects,id',
            'date' => 'required|date',
            'weather' => 'nullable|string|max:50',
            'temperature' => 'nullable|numeric',
            'workforce_count' => 'nullable|integer|min:0',
            'work_performed' => 'required|string',
            'materials_used' => 'nullable|array',
            'equipment_used' => 'nullable|array',
            'issues' => 'nullable|string',
            'notes' => 'nullable|string',
            'photos.*' => 'nullable|image|max:10240',
        ]);

        $user = Auth::user();
        $dailyLog = $this->chantierService->createDailyLog($request->all(), $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Log journalier créé avec succès',
            'data' => $dailyLog,
        ], 201);
    }

    /**
     * Liste des logs journaliers
     */
    public function listDailyLogs(Request $request): JsonResponse
    {
        $query = DailyLog::with(['project:id,title', 'creator:id,name']);

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        $logs = $query->orderByDesc('date')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Signaler un incident de sécurité
     */
    public function reportIncident(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|exists:portfolio_projects,id',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'type' => 'required|string|in:' . implode(',', array_keys(SafetyIncident::TYPES)),
            'severity' => 'required|string|in:' . implode(',', array_keys(SafetyIncident::SEVERITIES)),
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'nullable|string|max:255',
            'persons_involved' => 'nullable|array',
            'injuries' => 'nullable|array',
            'actions_taken' => 'nullable|string',
            'witnesses' => 'nullable|array',
            'photos.*' => 'nullable|image|max:10240',
        ]);

        $user = Auth::user();
        $incident = $this->chantierService->reportIncident($request->all(), $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Incident signalé avec succès',
            'data' => $incident,
        ], 201);
    }

    /**
     * Liste des incidents de sécurité
     */
    public function listIncidents(Request $request): JsonResponse
    {
        $query = SafetyIncident::with(['project:id,title', 'reporter:id,name']);

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('severity')) {
            $query->bySeverity((string) $request->severity);
        }

        if ($request->has('status')) {
            $query->whereRaw('LOWER(status) = ?', [strtolower((string) $request->status)]);
        }

        $incidents = $query->orderByDesc('date')->paginate(10);

        // Ajouter les labels
        $data = $incidents->getCollection()->map(function ($incident) {
            return [
                'id' => $incident->id,
                'title' => $incident->title,
                'project' => $incident->project?->title,
                'date' => $incident->date->format('Y-m-d'),
                'type' => $incident->type,
                'type_label' => $incident->type_label,
                'severity' => $incident->severity,
                'severity_label' => $incident->severity_label,
                'status' => $incident->status,
                'reporter' => $incident->reporter?->name,
                'description' => $incident->description,
            ];
        });
        $incidents->setCollection($data);

        return response()->json([
            'success' => true,
            'data' => $incidents,
        ]);
    }

    /**
     * Résoudre un incident
     */
    public function resolveIncident(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'preventive_measures' => 'nullable|string',
        ]);

        $incident = SafetyIncident::findOrFail($id);
        $user = Auth::user();

        $this->chantierService->resolveIncident($incident, $request->all(), $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Incident résolu avec succès',
        ]);
    }

    /**
     * Mettre à jour la progression d'un chantier
     */
    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'note' => 'nullable|string',
        ]);

        $project = PortfolioProject::findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $this->chantierService->updateProgress(
            $project,
            $request->progress,
            $request->note,
            $user->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Progression mise à jour',
            'data' => ['progress' => $request->progress],
        ]);
    }

    /**
     * Obtenir le récapitulatif hebdomadaire
     */
    public function getWeeklySummary(Request $request, int $id): JsonResponse
    {
        $project = PortfolioProject::findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $weekStart = $request->has('week_start')
            ? \Carbon\Carbon::parse($request->week_start)
            : null;

        $summary = $this->chantierService->getWeeklySummary($project, $weekStart);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Obtenir les types d'incidents disponibles
     */
    public function getIncidentTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'types' => SafetyIncident::TYPES,
                'severities' => SafetyIncident::SEVERITIES,
            ],
        ]);
    }

    /**
     * Détails d'un chantier
     */
    public function getChantier(int $id): JsonResponse
    {
        $project = PortfolioProject::with(['progressUpdates', 'media'])->findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $stats = $this->chantierService->getProjectStats($project);

        return response()->json([
            'success' => true,
            'data' => [
                'project' => $this->transformProject($project),
                'stats' => $stats,
                'phase' => $this->projectPhaseWorkflowService->getPhaseState($project),
            ],
        ]);
    }

    /**
     * État des phases chantier
     */
    public function getPhaseTransitionState(int $id): JsonResponse
    {
        $project = PortfolioProject::findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $this->projectPhaseWorkflowService->getPhaseState($project),
        ]);
    }

    /**
     * Demander un changement de phase (chef) ou appliquer direct (admin)
     */
    public function requestPhaseTransition(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'to_phase' => 'required|string|in:' . implode(',', ProjectPhaseWorkflowService::phaseKeys()),
            'note' => 'nullable|string|max:1000',
        ]);

        $project = PortfolioProject::findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $result = $this->projectPhaseWorkflowService->requestTransition(
            $project,
            $user,
            $request->string('to_phase')->toString(),
            $request->input('note')
        );

        return response()->json([
            'success' => true,
            'message' => $result['mode'] === 'pending_approval'
                ? 'Demande de changement de phase envoyée pour validation.'
                : 'Phase du chantier mise à jour.',
            'data' => $result,
        ]);
    }

    /**
     * Mettre à jour un chantier
     */
    public function updateChantier(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:planned,pending,in_progress,completed,on_hold',
            'progress' => 'nullable|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|string|max:255',
            'priority' => 'nullable|string|in:low,medium,high',
            'notes' => 'nullable|string',
            'construction_team_ids' => 'nullable|array',
            'construction_team_ids.*' => 'integer|exists:construction_teams,id',
        ]);

        $project = PortfolioProject::findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $updateData = collect($validated)->only([
            'title',
            'description',
            'location',
            'category',
            'status',
            'progress',
            'start_date',
            'expected_end_date',
            'budget',
        ])->toArray();

        if (array_key_exists('title', $updateData) && !array_key_exists('slug', $updateData)) {
            $slugBase = Str::slug((string) $updateData['title']) ?: 'chantier';
            $slug = $slugBase;
            $counter = 1;
            while (PortfolioProject::where('slug', $slug)->where('id', '!=', $project->id)->exists()) {
                $slug = "{$slugBase}-{$counter}";
                $counter++;
            }
            $updateData['slug'] = $slug;
        }

        if (array_key_exists('status', $updateData)) {
            $updateData['status'] = $this->normalizeProjectStatus((string) $updateData['status']);
        }

        $updateData['metadata'] = $this->buildProjectMetadata($this->extractProjectMetadata($project), $validated);

        if (!empty($updateData)) {
            $project->update($updateData);
            ActivityLogService::logProjectUpdated($project->fresh(), $user);

            // Notifier le client si la progression a changé
            if ($request->has('progress')) {
                $this->chantierService->notifyClientOfUpdate(
                    $project,
                    $validated['notes'] ?? "Mise à jour du chantier - Progression: {$request->progress}%"
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Chantier mis à jour avec succès',
            'data' => $this->transformProject($project->fresh()),
        ]);
    }

    /**
     * Créer une équipe
     */
    public function createEquipe(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'leader_name' => 'required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'members_count' => 'nullable|integer|min:1',
        ]);

        $team = ConstructionTeam::create([
            'name' => $request->name,
            'leader_name' => $request->leader_name,
            'specialization' => $request->specialization,
            'phone' => $request->phone,
            'email' => $request->email,
            'members_count' => $request->members_count ?? 1,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Équipe créée avec succès',
            'data' => $team,
        ], 201);
    }

    /**
     * Mettre à jour une équipe
     */
    public function updateEquipe(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'leader_name' => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'members_count' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $team = ConstructionTeam::findOrFail($id);
        $team->update($request->only([
            'name', 'leader_name', 'specialization', 'phone', 'email', 'members_count', 'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Équipe mise à jour avec succès',
            'data' => $team,
        ]);
    }

    /**
     * Uploader des photos pour un chantier
     */
    public function uploadPhotos(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|image|max:10240',
            'description' => 'nullable|string|max:255',
        ]);

        $project = PortfolioProject::findOrFail($id);
        $user = Auth::user();
        if (!$this->canAccessChantier($user, $project)) {
            return $this->forbiddenChantierResponse();
        }

        $uploadedPhotos = [];
        foreach ($request->file('photos') as $photo) {
            $path = $photo->store("chantiers/{$project->id}/photos", 'public');
            $uploadedPhotos[] = [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'uploaded_at' => now()->toISOString(),
                'uploaded_by' => $user->name,
            ];
        }

        // Ajouter les photos au projet
        $existingImages = $project->images ?? [];
        $project->update([
            'images' => array_merge($existingImages, array_column($uploadedPhotos, 'url')),
        ]);

        return response()->json([
            'success' => true,
            'message' => count($uploadedPhotos) . ' photo(s) uploadée(s) avec succès',
            'data' => $uploadedPhotos,
        ]);
    }
}
