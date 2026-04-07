<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConstructionTeam;
use App\Models\ContactRequest;
use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class PortfolioProjectController extends Controller
{
    private function isQuoteRequest(ContactRequest $contactRequest): bool
    {
        return $contactRequest->type === 'quote_request'
            || str_contains(Str::lower($contactRequest->subject ?? ''), 'devis')
            || $contactRequest->quote_number !== null
            || $contactRequest->response_document !== null
            || $contactRequest->response_message !== null;
    }

    private function resolveLinkedQuoteRequest(int|string|null $reference): ?ContactRequest
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        $contactRequest = ContactRequest::query()->find($reference);

        if (!$contactRequest || !$this->isQuoteRequest($contactRequest)) {
            return null;
        }

        return $contactRequest;
    }

    private function serializeLinkedQuoteRequest(?ContactRequest $contactRequest): ?array
    {
        if (!$contactRequest) {
            return null;
        }

        return [
            'id' => $contactRequest->id,
            'quote_number' => $contactRequest->quote_number,
            'name' => $contactRequest->name,
            'email' => $contactRequest->email,
            'company' => $contactRequest->company,
            'subject' => $contactRequest->subject,
            'status' => $contactRequest->status,
            'service_type' => $contactRequest->service_type,
            'created_at' => $contactRequest->created_at?->toISOString(),
        ];
    }

    private function extractConstructionTeamIds(PortfolioProject $project): array
    {
        $metadata = is_array($project->metadata) ? $project->metadata : [];
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

    private function buildConstructionTeamMemberMap(iterable $projects): array
    {
        $teamIds = collect($projects)
            ->flatMap(fn (PortfolioProject $project) => $this->extractConstructionTeamIds($project))
            ->unique()
            ->values();

        if ($teamIds->isEmpty()) {
            return [];
        }

        return ConstructionTeam::query()
            ->whereIn('id', $teamIds)
            ->pluck('members_count', 'id')
            ->map(fn ($members) => (int) $members)
            ->all();
    }

    private function serializeAdminProject(PortfolioProject $project, array $constructionTeamMemberMap = []): array
    {
        $data = $project->toArray();
        $constructionTeamIds = $this->extractConstructionTeamIds($project);
        $constructionTeamMembersCount = collect($constructionTeamIds)
            ->sum(fn ($teamId) => (int) ($constructionTeamMemberMap[$teamId] ?? 0));

        $fallbackAssignedUsersCount = is_array($project->team_ids) ? count($project->team_ids) : 0;

        $data['chef_chantier_public_id'] = $project->chefChantierUser?->getPublicId();
        $data['chef_chantier_name'] = $project->chefChantierUser?->name;
        $data['linked_quote_request'] = $this->serializeLinkedQuoteRequest($project->linkedQuoteRequest);
        $data['construction_team_ids'] = $constructionTeamIds;
        $data['assigned_construction_teams_count'] = count($constructionTeamIds);
        $data['assigned_construction_team_members_count'] = $constructionTeamMembersCount;
        $data['assigned_people_count'] = $constructionTeamMembersCount > 0
            ? $constructionTeamMembersCount
            : $fallbackAssignedUsersCount;

        return $data;
    }

    private function resolveUserReference(int|string|null $reference): ?User
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        $value = (string) $reference;

        return User::query()
            ->where('public_id', $value)
            ->orWhere('id', is_numeric($value) ? (int) $value : 0)
            ->first();
    }

    private function hydrateProjectAssignments(array $validated): array
    {
        if (array_key_exists('chef_chantier_id', $validated)) {
            $chef = $this->resolveUserReference($validated['chef_chantier_id']);

            if ($validated['chef_chantier_id'] && !$chef) {
                throw ValidationException::withMessages([
                    'chef_chantier_id' => ['Le chef de chantier sélectionné est invalide.'],
                ]);
            }

            if ($chef && !($chef->hasRole('chef_chantier') || $chef->hasRole('admin'))) {
                throw ValidationException::withMessages([
                    'chef_chantier_id' => ['L utilisateur selectionne n a pas le role chef de chantier.'],
                ]);
            }

            $validated['chef_chantier_id'] = $chef?->id;
        }

        if (array_key_exists('team_ids', $validated)) {
            $teamIds = collect($validated['team_ids'] ?? [])
                ->map(fn ($reference) => $this->resolveUserReference($reference))
                ->filter();

            if ($teamIds->count() !== count($validated['team_ids'] ?? [])) {
                throw ValidationException::withMessages([
                    'team_ids' => ['Un ou plusieurs membres d’équipe sont invalides.'],
                ]);
            }

            $validated['team_ids'] = $teamIds
                ->map(fn (User $user) => $user->id)
                ->values()
                ->all();
        }

        if (array_key_exists('linked_quote_request_id', $validated)) {
            $linkedQuoteRequest = $this->resolveLinkedQuoteRequest($validated['linked_quote_request_id']);

            if ($validated['linked_quote_request_id'] && !$linkedQuoteRequest) {
                throw ValidationException::withMessages([
                    'linked_quote_request_id' => ['Le devis sélectionné est invalide.'],
                ]);
            }

            $validated['linked_quote_request_id'] = $linkedQuoteRequest?->id;

            if ($linkedQuoteRequest) {
                if (empty($validated['client'] ?? null)) {
                    $validated['client'] = $linkedQuoteRequest->company ?: $linkedQuoteRequest->name;
                }

                if (empty($validated['client_email'] ?? null)) {
                    $validated['client_email'] = $linkedQuoteRequest->email;
                }
            }
        }

        return $validated;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = PortfolioProject::where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'slug' => $project->slug, // Ensure your migration has this or use Str::slug($title) if dynamic
                    'description' => $project->description,
                    'category' => $project->category,
                    'location' => $project->location,
                    'completion_date' => $project->completion_date,
                    'client_name' => $project->client_name,
                    'cover_image' => $project->cover_image_url ?? $this->getPlaceholderImage(), // Handle generic accessor if needed
                    'is_published' => (bool) $project->is_published,
                ];
            });

        return response()->json($projects);
    }

    /**
     * Display the specified resource.
     */
    public function show($slug)
    {
        // Assuming you have a 'slug' column. If only ID, adjust parameter.
        $project = PortfolioProject::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return response()->json([
            'id' => $project->id,
            'title' => $project->title,
            'slug' => $project->slug,
            'description' => $project->description,
            'category' => $project->category,
            'location' => $project->location,
            'completion_date' => $project->completion_date,
            'client_name' => $project->client_name,
            'cover_image' => $project->cover_image_url ?? $this->getPlaceholderImage(),
            'images' => $project->media->pluck('file_path')->map(fn($path) => asset('storage/' . $path)), // Assuming media relation
            'is_published' => (bool) $project->is_published,
        ]);
    }

    /**
     * Liste portfolio pour l'administration (inclut non publiés)
     */
    public function adminIndex(Request $request)
    {
        $query = PortfolioProject::with(['chefChantierUser', 'linkedQuoteRequest'])->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('client', 'like', "%{$search}%");
            });
        }

        $projects = $query->paginate((int) $request->integer('per_page', 15));
        $constructionTeamMemberMap = $this->buildConstructionTeamMemberMap($projects->getCollection());
        $projects->setCollection(
            $projects->getCollection()->map(
                fn (PortfolioProject $project) => $this->serializeAdminProject($project, $constructionTeamMemberMap)
            )
        );

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    public function adminShow(PortfolioProject $project)
    {
        $project->loadMissing(['chefChantierUser', 'linkedQuoteRequest']);
        $constructionTeamMemberMap = $this->buildConstructionTeamMemberMap([$project]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeAdminProject($project, $constructionTeamMemberMap),
        ]);
    }

    /**
     * Créer un projet portfolio
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:portfolio_projects,slug'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'digits:4'],
            'duration' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'challenges' => ['nullable', 'string'],
            'results' => ['nullable', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:2048'],
            'client_id' => ['nullable'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'linked_quote_request_id' => ['nullable'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date'],
            'chef_chantier_id' => ['nullable'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['nullable'],
        ]);

        $validated = $this->hydrateClientLink($validated);
        $validated = $this->hydrateProjectAssignments($validated);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']);

        if (PortfolioProject::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $validated['slug'] . '-' . Str::lower(Str::random(4));
        }

        if ($request->user()) {
            $validated['created_by'] = $request->user()->id;
        }

        $project = PortfolioProject::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Projet portfolio créé avec succès',
            'data' => $project,
        ], 201);
    }

    /**
     * Mettre à jour un projet portfolio
     */
    public function update(Request $request, PortfolioProject $project)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('portfolio_projects', 'slug')->ignore($project->id)],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'digits:4'],
            'duration' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'challenges' => ['nullable', 'string'],
            'results' => ['nullable', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:2048'],
            'client_id' => ['nullable'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'linked_quote_request_id' => ['nullable'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date'],
            'chef_chantier_id' => ['nullable'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['nullable'],
        ]);

        $validated = $this->hydrateClientLink($validated);
        $validated = $this->hydrateProjectAssignments($validated);

        if (isset($validated['title']) && empty($validated['slug'])) {
            $generatedSlug = Str::slug($validated['title']);
            $exists = PortfolioProject::where('slug', $generatedSlug)
                ->where('id', '!=', $project->id)
                ->exists();
            $validated['slug'] = $exists ? $generatedSlug . '-' . Str::lower(Str::random(4)) : $generatedSlug;
        }

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Projet portfolio mis à jour avec succès',
            'data' => $project->fresh(),
        ]);
    }

    /**
     * Supprimer un projet portfolio
     */
    public function destroy(PortfolioProject $project)
    {
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Projet portfolio supprimé avec succès',
        ]);
    }

    /**
     * Upload d'image portfolio (cover ou galerie)
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $file = $request->file('image');
        $path = $file->store('portfolio', 'public');

        return response()->json([
            'success' => true,
            'message' => 'Image uploadée avec succès',
            'data' => [
                'url' => Storage::url($path),
                'full_url' => asset('storage/' . $path),
                'path' => $path,
            ],
        ]);
    }

    private function getPlaceholderImage()
    {
        return 'https://via.placeholder.com/800x600.png?text=BTP+Project';
    }

    private function hydrateClientLink(array $validated): array
    {
        if (!array_key_exists('client_id', $validated)) {
            return $validated;
        }

        $clientId = $validated['client_id'];

        if (empty($clientId)) {
            $validated['client_id'] = null;
            $validated['client_email'] = null;
            $validated['client'] = null;
            return $validated;
        }

        $client = $this->resolveUserReference($clientId);

        if (!$client || !$this->isClientUser($client)) {
            throw ValidationException::withMessages([
                'client_id' => ['Le client sélectionné est invalide.'],
            ]);
        }

        $validated['client_id'] = $client->id;
        $validated['client_email'] = $client->email;
        $validated['client'] = $client->name;

        return $validated;
    }

    private function isClientUser(User $user): bool
    {
        if ($user->role === 'client') {
            return true;
        }

        if (method_exists($user, 'getRoleSlugs')) {
            return in_array('client', $user->getRoleSlugs(), true);
        }

        return false;
    }
}
