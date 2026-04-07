<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\FormationSession;
use App\Models\FormationEnrollment;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FormateurController extends Controller
{
    /**
     * Liste des apprenants du formateur
     */
    public function listApprenants(Request $request): JsonResponse
    {
        $formateur = Auth::user();

        // Récupérer les sessions du formateur
        $sessionIds = FormationSession::where('formateur_id', $formateur->id)->pluck('id');

        // Récupérer les apprenants inscrits à ces sessions
        $query = User::whereHas('enrollments', function ($q) use ($sessionIds) {
            $q->whereIn('session_id', $sessionIds)
                ->where('status', 'confirmed');
        })->with([
                    'enrollments' => function ($q) use ($sessionIds) {
                        $q->whereIn('session_id', $sessionIds)
                            ->with(['formation', 'session']);
                    }
                ]);

        // Filtres
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('formation')) {
            $query->whereHas('enrollments.formation', function ($q) use ($request) {
                $q->where('id', $request->formation);
            });
        }

        $apprenants = $query->get()->map(function ($apprenant) use ($sessionIds) {
            $enrollment = $apprenant->enrollments->first();

            // Stats de présence
            $totalDays = Attendance::where('user_id', $apprenant->id)
                ->whereIn('formation_session_id', $sessionIds)
                ->distinct('date')
                ->count('date');

            $presentDays = Attendance::where('user_id', $apprenant->id)
                ->whereIn('formation_session_id', $sessionIds)
                ->where('status', 'present')
                ->count();

            $tauxPresence = $totalDays > 0 ? round(($presentDays / $totalDays) * 100) : 0;

            // Moyenne des notes
            $avgScore = EvaluationResult::where('user_id', $apprenant->id)
                ->whereHas('evaluation', function ($q) use ($sessionIds) {
                    $q->whereIn('formation_session_id', $sessionIds);
                })
                ->where('status', 'graded')
                ->avg('score');

            return [
                'id' => $apprenant->id,
                'name' => $apprenant->name,
                'email' => $apprenant->email,
                'phone' => $apprenant->phone,
                'formation' => $enrollment->formation->title ?? 'N/A',
                'enrollment_date' => $enrollment->created_at->format('Y-m-d'),
                'progression' => $enrollment->metadata['progression'] ?? 0,
                'taux_presence' => $tauxPresence,
                'derniere_connexion' => $apprenant->last_login_at?->format('Y-m-d') ?? 'N/A',
                'status' => $apprenant->is_active ? 'actif' : 'inactif',
                'notes_moyenne' => $avgScore ? round($avgScore, 1) : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $apprenants->values(),
        ]);
    }

    /**
     * Liste des évaluations
     */
    public function listEvaluations(Request $request): JsonResponse
    {
        $formateur = Auth::user();

        $query = Evaluation::where('created_by', $formateur->id)
            ->with(['session.formation']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $evaluations = $query->orderByDesc('date')->get()->map(function ($eval) {
            $totalParticipants = EvaluationResult::where('evaluation_id', $eval->id)->count();
            $corriges = EvaluationResult::where('evaluation_id', $eval->id)
                ->where('status', 'graded')
                ->count();

            $moyenne = EvaluationResult::where('evaluation_id', $eval->id)
                ->where('status', 'graded')
                ->avg('score');

            // Déterminer le statut
            $now = now();
            $evalDate = \Carbon\Carbon::parse($eval->date);

            if ($evalDate->isFuture()) {
                $status = 'a_venir';
            } elseif ($corriges === $totalParticipants && $totalParticipants > 0) {
                $status = 'corrigee';
            } elseif ($evalDate->isPast()) {
                $status = $corriges > 0 ? 'terminee' : 'en_cours';
            } else {
                $status = 'en_cours';
            }

            return [
                'id' => $eval->id,
                'titre' => $eval->title,
                'formation' => $eval->session->formation->title ?? 'N/A',
                'type' => $eval->type,
                'date' => $eval->date,
                'duree' => $eval->duration_minutes ?? 0,
                'participants' => $totalParticipants,
                'corriges' => $corriges,
                'status' => $status,
                'moyenne' => $moyenne ? round($moyenne, 1) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $evaluations,
        ]);
    }

    /**
     * Créer une évaluation
     */
    public function createEvaluation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'formation_session_id' => 'required|exists:formation_sessions,id',
            'type' => 'required|in:exam,quiz,practical,project',
            'date' => 'required|date',
            'duree' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $formateur = Auth::user();

        $evaluation = Evaluation::create([
            'title' => $validated['titre'],
            'formation_session_id' => $validated['formation_session_id'],
            'type' => $validated['type'],
            'date' => $validated['date'],
            'duration_minutes' => $validated['duree'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $formateur->id,
        ]);

        // Créer les résultats pour tous les apprenants inscrits
        $enrollments = FormationEnrollment::where('session_id', $validated['formation_session_id'])
            ->where('status', 'confirmed')
            ->get();

        foreach ($enrollments as $enrollment) {
            EvaluationResult::create([
                'evaluation_id' => $evaluation->id,
                'user_id' => $enrollment->user_id,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Évaluation créée avec succès',
            'data' => $evaluation,
        ]);
    }

    /**
     * Notes d'une évaluation
     */
    public function getEvaluationNotes(int $evaluationId): JsonResponse
    {
        $formateur = Auth::user();

        $evaluation = Evaluation::where('id', $evaluationId)
            ->where('created_by', $formateur->id)
            ->firstOrFail();

        $notes = EvaluationResult::where('evaluation_id', $evaluationId)
            ->with('user')
            ->get()
            ->map(function ($result) {
                return [
                    'id' => $result->id,
                    'apprenant_id' => $result->user_id,
                    'apprenant_name' => $result->user->name,
                    'note' => $result->score,
                    'commentaire' => $result->feedback ?? '',
                    'date_soumission' => $result->updated_at->format('Y-m-d'),
                    'status' => $result->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    /**
     * Enregistrer les notes
     */
    public function saveEvaluationNotes(Request $request, int $evaluationId): JsonResponse
    {
        $formateur = Auth::user();

        $evaluation = Evaluation::where('id', $evaluationId)
            ->where('created_by', $formateur->id)
            ->firstOrFail();

        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.id' => 'required|exists:evaluation_results,id',
            'notes.*.note' => 'nullable|numeric|min:0|max:20',
            'notes.*.commentaire' => 'nullable|string',
        ]);

        foreach ($validated['notes'] as $noteData) {
            EvaluationResult::where('id', $noteData['id'])
                ->update([
                    'score' => $noteData['note'] ?? null,
                    'feedback' => $noteData['commentaire'] ?? null,
                    'status' => $noteData['note'] !== null ? 'graded' : 'pending',
                    'graded_by' => $formateur->id,
                    'graded_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notes enregistrées avec succès',
        ]);
    }

    /**
     * Présences pour une date
     */
    public function getPresences(Request $request): JsonResponse
    {
        $formateur = Auth::user();

        $validated = $request->validate([
            'date' => 'required|date',
            'formation_session_id' => 'required|exists:formation_sessions,id',
        ]);

        $session = FormationSession::where('id', $validated['formation_session_id'])
            ->where('formateur_id', $formateur->id)
            ->with('formation')
            ->firstOrFail();

        // Récupérer les apprenants inscrits
        $enrollments = FormationEnrollment::where('session_id', $session->id)
            ->where('status', 'confirmed')
            ->with('user')
            ->get();

        $apprenants = $enrollments->map(function ($enrollment) use ($validated, $session) {
            // Chercher la présence pour cette date
            $attendance = Attendance::where('user_id', $enrollment->user_id)
                ->where('formation_session_id', $session->id)
                ->where('date', $validated['date'])
                ->first();

            return [
                'id' => $enrollment->user_id,
                'name' => $enrollment->user->name,
                'formation' => $session->formation->title,
                'status' => $attendance->status ?? null,
                'heure_arrivee' => $attendance->arrival_time ?? null,
                'commentaire' => $attendance->notes ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $validated['date'],
                'formation' => $session->formation->title,
                'cours' => $session->title ?? 'Cours du jour',
                'apprenants' => $apprenants,
            ],
        ]);
    }

    /**
     * Enregistrer les présences
     */
    public function savePresences(Request $request): JsonResponse
    {
        $formateur = Auth::user();

        $validated = $request->validate([
            'date' => 'required|date',
            'formation_session_id' => 'required|exists:formation_sessions,id',
            'presences' => 'required|array',
            'presences.*.user_id' => 'required|exists:users,id',
            'presences.*.status' => 'required|in:present,absent,late,excused',
            'presences.*.heure_arrivee' => 'nullable|date_format:H:i',
            'presences.*.commentaire' => 'nullable|string',
        ]);

        $session = FormationSession::where('id', $validated['formation_session_id'])
            ->where('formateur_id', $formateur->id)
            ->firstOrFail();

        foreach ($validated['presences'] as $presenceData) {
            Attendance::updateOrCreate(
                [
                    'user_id' => $presenceData['user_id'],
                    'formation_session_id' => $session->id,
                    'date' => $validated['date'],
                ],
                [
                    'status' => $presenceData['status'],
                    'arrival_time' => $presenceData['heure_arrivee'] ?? null,
                    'notes' => $presenceData['commentaire'] ?? null,
                    'marked_by' => $formateur->id,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Présences enregistrées avec succès',
        ]);
    }
}
