<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\FormationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\ActivityLog;

class FormationController extends Controller
{
    /**
     * Liste toutes les formations (admin/formateur)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Formation::with(['formateur:id,name,email', 'sessions'])
            ->withCount([
                'sessions as active_sessions_count' => function ($q) {
                    $q->whereIn('status', ['planned', 'ongoing']);
                }
            ]);

        // Filtrage par statut actif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filtrage par niveau
        if ($request->filled('level')) {
            $query->where('level', $this->normalizeLevel($request->level));
        }

        // Filtrage par catégorie
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Pour les formateurs, ne montrer que leurs formations
        if ($request->user()->hasRole('formateur') && !$request->user()->hasRole('admin')) {
            $query->where('formateur_id', $request->user()->id);
        }

        $formations = $query->orderBy('display_order')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $formations
        ]);
    }

    /**
     * Créer une nouvelle formation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'objectives' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'program' => 'nullable|array',
            'duration_hours' => 'nullable|integer|min:1',
            'duration_days' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'registration_fees' => 'nullable|numeric|min:0',
            'level' => ['required', Rule::in(['debutant', 'intermediaire', 'avance'])],
            'category' => 'nullable|string|max:100',
            'cover_image' => 'nullable|string',
            'max_students' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'formateur_id' => 'nullable|exists:users,id',
        ]);

        $validated['level'] = $this->normalizeLevel($validated['level']);

        // Générer le slug
        $validated['slug'] = Str::slug($validated['title']);

        // Si doublon, ajouter un suffixe
        $existingSlug = Formation::where('slug', $validated['slug'])->exists();
        if ($existingSlug) {
            $validated['slug'] .= '-' . Str::random(5);
        }

        // Si formateur, assigner automatiquement
        if ($request->user()->hasRole('formateur') && !$request->user()->hasRole('admin')) {
            $validated['formateur_id'] = $request->user()->id;
        }

        $formation = Formation::create($validated);

        // Log activity
        ActivityLog::log(
            $request->user(),
            'Nouvelle formation',
            'A créé la formation "' . $formation->title . '"',
            $formation
        );

        return response()->json([
            'success' => true,
            'message' => 'Formation créée avec succès',
            'data' => $formation->load('formateur:id,name,email')
        ], 201);
    }

    /**
     * Afficher une formation
     */
    public function show(Formation $formation): JsonResponse
    {
        if ($response = $this->ensureCanManageFormation(request(), $formation)) {
            return $response;
        }

        $formation->load([
            'formateur:id,name,email',
            'sessions' => function ($query) {
                $query->withCount('enrollments')->orderByDesc('start_date')->orderByDesc('id');
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => $formation
        ]);
    }

    /**
     * Mettre à jour une formation
     */
    public function update(Request $request, Formation $formation): JsonResponse
    {
        if ($response = $this->ensureCanManageFormation($request, $formation)) {
            return $response;
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'objectives' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'program' => 'nullable|array',
            'duration_hours' => 'nullable|integer|min:1',
            'duration_days' => 'nullable|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'registration_fees' => 'nullable|numeric|min:0',
            'level' => ['sometimes', Rule::in(['debutant', 'intermediaire', 'avance'])],
            'category' => 'nullable|string|max:100',
            'cover_image' => 'nullable|string',
            'max_students' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'formateur_id' => 'nullable|exists:users,id',
        ]);

        if (isset($validated['level'])) {
            $validated['level'] = $this->normalizeLevel($validated['level']);
        }

        // Mettre à jour le slug si le titre change
        if (isset($validated['title']) && $validated['title'] !== $formation->title) {
            $newSlug = Str::slug($validated['title']);
            if (Formation::where('slug', $newSlug)->where('id', '!=', $formation->id)->exists()) {
                $newSlug .= '-' . Str::random(5);
            }
            $validated['slug'] = $newSlug;
        }

        $formation->update($validated);

        // Log activity
        ActivityLog::log(
            $request->user(),
            'Formation modifiée',
            'A mis à jour la formation "' . $formation->title . '"',
            $formation
        );

        return response()->json([
            'success' => true,
            'message' => 'Formation mise à jour avec succès',
            'data' => $formation->load('formateur:id,name,email')
        ]);
    }

    /**
     * Supprimer une formation
     */
    public function destroy(Request $request, Formation $formation): JsonResponse
    {
        if ($response = $this->ensureCanManageFormation($request, $formation)) {
            return $response;
        }

        // Vérifier si des sessions actives existent
        if ($formation->sessions()->whereIn('status', ['planned', 'ongoing'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une formation avec des sessions actives'
            ], 422);
        }

        // Capture title before deletion for log
        $title = $formation->title;
        $formation->delete();

        // Log activity
        ActivityLog::log(
            $request->user(),
            'Formation supprimée',
            'A supprimé la formation "' . $title . '"',
            null // Subject is deleted
        );

        return response()->json([
            'success' => true,
            'message' => 'Formation supprimée avec succès'
        ]);
    }

    /**
     * Changer le statut d'une formation
     */
    public function toggleStatus(Request $request, Formation $formation): JsonResponse
    {
        if ($response = $this->ensureCanManageFormation($request, $formation)) {
            return $response;
        }

        $formation->update([
            'is_active' => !$formation->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => $formation->is_active ? 'Formation activée' : 'Formation désactivée',
            'data' => $formation
        ]);
    }

    /**
     * Sessions d'une formation
     */
    public function sessions(Formation $formation): JsonResponse
    {
        if ($response = $this->ensureCanManageFormation(request(), $formation)) {
            return $response;
        }

        $sessions = $formation->sessions()
            ->with(['enrollments.user:id,name,email'])
            ->withCount('enrollments')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    /**
     * Créer une session pour une formation
     */
    public function createSession(Request $request, Formation $formation): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'max_students' => 'nullable|integer|min:1',
            'status' => 'in:planned,ongoing,completed,cancelled',
        ]);

        if ($response = $this->ensureCanManageFormation($request, $formation)) {
            return $response;
        }

        if (
            !empty($validated['start_time'])
            && !empty($validated['end_time'])
            && strtotime($validated['end_time']) <= strtotime($validated['start_time'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'L\'heure de fin doit être postérieure à l\'heure de début',
            ], 422);
        }

        $validated['formation_id'] = $formation->id;
        $validated['formateur_id'] = $formation->formateur_id ?: $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'planned';
        $validated['max_students'] = $validated['max_students'] ?? $formation->max_students;

        $session = FormationSession::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Session créée avec succès',
            'data' => $session
        ], 201);
    }

    /**
     * Liste des catégories disponibles
     */
    public function categories(): JsonResponse
    {
        $categories = Formation::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Statistiques des formations
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Formation::query();

        // Pour les formateurs, ne montrer que leurs stats
        if ($request->user()->hasRole('formateur') && !$request->user()->hasRole('admin')) {
            $query->where('formateur_id', $request->user()->id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'featured' => (clone $query)->where('is_featured', true)->count(),
            'by_level' => [
                'debutant' => (clone $query)->where('level', 'debutant')->count(),
                'intermediaire' => (clone $query)->where('level', 'intermediaire')->count(),
                'avance' => (clone $query)->where('level', 'avance')->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    private function ensureCanManageFormation(Request $request, Formation $formation): ?JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->hasRole('admin')) {
            return null;
        }

        if ($user->hasRole('formateur') && (int) $formation->formateur_id === (int) $user->id) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Vous n\'êtes pas autorisé à gérer cette formation',
        ], 403);
    }

    private function normalizeLevel(?string $level): ?string
    {
        if ($level === null) {
            return null;
        }

        $value = Str::lower(trim($level));

        return match ($value) {
            'débutant', 'debutant' => 'debutant',
            'intermédiaire', 'intermediaire' => 'intermediaire',
            'avancé', 'avance' => 'avance',
            default => $value,
        };
    }
}
