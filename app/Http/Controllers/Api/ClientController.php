<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortfolioProject;
use App\Models\Payment;
use App\Models\ContactRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\ChantierConsulted;
use Illuminate\Database\Eloquent\Builder;

class ClientController extends Controller
{
    /**
     * Dashboard du client
     */
    public function dashboard(): JsonResponse
    {
        $user = Auth::user();

        // Projets associés au client avec leurs mises à jour
        $projectsQuery = PortfolioProject::query();
        $this->applyClientProjectScope($projectsQuery, $user);
        $projects = $projectsQuery->with('progressUpdates')->get();

        // Projet actif (premier projet en cours)
        $activeProject = $projects->where('status', 'in_progress')->first();

        // Calculer la progression du projet actif
        $projectProgress = $activeProject ? ($activeProject->progress ?? 0) : 0;

        // Prochaine étape (depuis les métadonnées ou message par défaut)
        $nextMilestone = 'Aucune étape définie';
        $nextMilestoneDate = '-';

        if ($activeProject) {
            // Essayer de récupérer depuis les métadonnées
            if (isset($activeProject->metadata['next_milestone'])) {
                $nextMilestone = $activeProject->metadata['next_milestone'];
            } else {
                // Sinon, utiliser un message basé sur le statut
                $nextMilestone = 'Poursuite des travaux';
            }

            // Date prévue de fin
            if ($activeProject->expected_end_date) {
                $nextMilestoneDate = \Carbon\Carbon::parse($activeProject->expected_end_date)
                    ->format('d/m/Y');
            }
        }

        // Messages non lus
        $unreadMessages = ContactRequest::where('email', $user->email)
            ->where('status', 'new')
            ->count();

        // Factures en attente (paiements "pending")
        $pendingInvoices = Payment::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        // Documents (nombre total de photos dans les projets et mises à jour)
        $documents = 0;
        foreach ($projects as $project) {
            // Photos du projet
            if (is_array($project->images)) {
                $documents += count($project->images);
            }
            // Photos des mises à jour
            if ($project->progressUpdates) {
                foreach ($project->progressUpdates as $update) {
                    if (is_array($update->photos)) {
                        $documents += count($update->photos);
                    }
                }
            }
        }

        // Mises à jour récentes (depuis ProgressUpdate)
        $recentUpdates = [];
        foreach ($projects as $project) {
            if ($project->progressUpdates) {
                foreach ($project->progressUpdates->take(10) as $update) {
                    $recentUpdates[] = [
                        'id' => $update->id,
                        'title' => $update->title ?? 'Mise à jour du projet',
                        'description' => $update->description ?? 'Nouvelle progression du chantier',
                        'date' => $update->created_at->toIso8601String(),
                        'type' => 'progress',
                    ];
                }
            }
        }

        // Trier par date et prendre les 5 plus récents
        usort($recentUpdates, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        $recentUpdates = array_slice($recentUpdates, 0, 5);

        // Construire la réponse dans le format attendu par le frontend
        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'projectProgress' => $projectProgress,
                    'nextMilestone' => $nextMilestone,
                    'nextMilestoneDate' => $nextMilestoneDate,
                    'unreadMessages' => $unreadMessages,
                    'pendingInvoices' => $pendingInvoices,
                    'documents' => $documents,
                ],
                'recentUpdates' => $recentUpdates,
                'currentProject' => $activeProject ? [
                    'id' => $activeProject->id,
                    'name' => $activeProject->title,
                    'status' => $activeProject->status,
                ] : null,
            ],
        ]);
    }

    /**
     * Mes projets
     */
    public function myProjets(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = PortfolioProject::query();
        $this->applyClientProjectScope($query, $user);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->orderByDesc('created_at')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Détail d'un projet
     */
    public function getProjet(int $projetId): JsonResponse
    {
        $user = Auth::user();

        $project = PortfolioProject::where('id', $projetId)
            ->where(function ($q) use ($user) {
                $q->where('client_id', $user->id)
                    ->orWhere('client_email', $user->email);
            })
            ->where(function ($q) {
                $q->whereNull('metadata')
                    ->orWhereNull('metadata->creation_validation')
                    ->orWhere('metadata->creation_validation->status', 'approved');
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    /**
     * Suivi de chantier - Liste
     */
    public function suiviChantier(): JsonResponse
    {
        $user = Auth::user();

        $projects = PortfolioProject::where(function ($q) use ($user) {
            $q->where('client_id', $user->id)
                ->orWhere('client_email', $user->email);
        })
            ->where(function ($q) {
                $q->whereNull('metadata')
                    ->orWhereNull('metadata->creation_validation')
                    ->orWhere('metadata->creation_validation->status', 'approved');
            })
            ->with([
                'progressUpdates' => function ($q) {
                    $q->orderByDesc('created_at')->limit(1);
                }
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'status' => $project->status,
                    'progress' => $project->progress ?? 0,
                    'last_update' => $project->progressUpdates->first(),
                    'start_date' => $project->start_date,
                    'expected_end_date' => $project->expected_end_date,
                    'location' => $project->location,
                    'category' => $project->category,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Suivi de chantier - Détail d'un projet
     */
    public function getSuiviChantier(int $projetId): JsonResponse
    {
        $user = Auth::user();

        $project = PortfolioProject::where('id', $projetId)
            ->where(function ($q) use ($user) {
                $q->where('client_id', $user->id)
                    ->orWhere('client_email', $user->email);
            })
            ->where(function ($q) {
                $q->whereNull('metadata')
                    ->orWhereNull('metadata->creation_validation')
                    ->orWhere('metadata->creation_validation->status', 'approved');
            })
            ->with([
                'progressUpdates' => function ($q) {
                    $q->orderByDesc('created_at');
                }
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'project' => $project,
                'progress_updates' => $project->progressUpdates,
                'current_progress' => $project->progress ?? 0,
            ],
        ]);
    }

    /**
     * Photos du chantier
     */
    public function getPhotosChantier(int $projetId): JsonResponse
    {
        $user = Auth::user();

        $project = PortfolioProject::where('id', $projetId)
            ->where(function ($q) use ($user) {
                $q->where('client_id', $user->id)
                    ->orWhere('client_email', $user->email);
            })
            ->where(function ($q) {
                $q->whereNull('metadata')
                    ->orWhereNull('metadata->creation_validation')
                    ->orWhere('metadata->creation_validation->status', 'approved');
            })
            ->with(['progressUpdates', 'media'])
            ->firstOrFail();

        // Photos directement liées au projet.
        $photos = collect($this->extractImageList($project->images))
            ->filter()
            ->map(fn ($image) => $this->normalizeProjectImageUrl((string) $image));

        // Photos postées dans les mises à jour d'avancement.
        $project->progressUpdates->each(function ($update) use (&$photos) {
            $updatePhotos = collect($this->extractImageList($update->photos))
                ->filter()
                ->map(fn ($photo) => $this->normalizeProjectImageUrl((string) $photo));
            $photos = $photos->merge($updatePhotos);
        });

        // Photos uploadées depuis l'espace chantier (table media).
        $project->media->each(function ($media) use (&$photos) {
            if (!empty($media->file_path)) {
                $photos->push($this->normalizeProjectImageUrl((string) $media->file_path));
            }
        });

        return response()->json([
            'success' => true,
            'data' => $photos->filter()->unique()->values(),
        ]);
    }

    /**
     * Mes devis
     */
    public function myDevis(): JsonResponse
    {
        $user = Auth::user();

        // Les devis sont stockés dans ContactRequest basés sur l'email du client
        $devis = ContactRequest::where('email', $user->email)
            ->where(function($q) {
                $q->where('service_type', '!=', null)
                  ->orWhere('subject', 'like', '%devis%');
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function($contact) {
                return [
                    'id' => $contact->id,
                    'quote_number' => $contact->quote_number ?? 'En attente',
                    'subject' => $contact->subject,
                    'service_type' => $contact->service_type,
                    'message' => $contact->message,
                    'status' => $contact->status,
                    'created_at' => $contact->created_at,
                    'responded_at' => $contact->responded_at,
                    'response_message' => $contact->response_message,
                    'response_document' => $contact->response_document,
                    'response_document_url' => $contact->response_document_url,
                    'response_sent_at' => $contact->response_sent_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $devis,
        ]);
    }

    /**
     * Mes factures
     */
    public function myFactures(): JsonResponse
    {
        $user = Auth::user();

        // Inclure TOUS les paiements (pending ET completed)
        $payments = Payment::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'completed'])
            ->with('payable.formation') // Eager load pour afficher le nom
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Initier le paiement en ligne d'une facture
     */
    public function payInvoice(Payment $payment): JsonResponse
    {
        $user = Auth::user();

        // Vérifier que le paiement appartient au client
        if ($payment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        // Vérifier que le paiement est en attente
        if ($payment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement ne peut pas être traité'
            ], 400);
        }

        // TODO: Intégrer avec Moneroo pour créer le paiement
        // Pour l'instant, retourner une URL factice
        $paymentUrl = env('APP_URL') . '/paiement/moneroo?ref=' . $payment->reference;

        return response()->json([
            'success' => true,
            'payment_url' => $paymentUrl,
        ]);
    }

    /**
     * Mes messages
     */
    public function myMessages(Request $request): JsonResponse
    {
        $user = Auth::user();

        $messages = ContactRequest::where('email', $user->email)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Envoyer un message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'project_id' => 'nullable|exists:portfolio_projects,id',
        ]);

        $contact = ContactRequest::create([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'type' => 'client_message',
            'metadata' => [
                'user_id' => $user->id,
                'project_id' => $validated['project_id'] ?? null,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => $contact,
        ], 201);
    }

    /**
     * Mon profil
     */
    public function getProfil(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'company_name' => $user->company_name,
                'company_address' => $user->company_address,
                'address' => $user->address,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Mettre à jour mon profil
     */
    public function updateProfil(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:30',
            'company_name' => 'sometimes|string|max:255',
            'company_address' => 'sometimes|string|max:500',
            'address' => 'sometimes|string|max:500',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'data' => $user->fresh(),
        ]);
    }

    private function applyClientProjectScope(Builder $query, \App\Models\User $user): Builder
    {
        return $query
            ->where(function (Builder $q) use ($user) {
                $q->where('client_id', $user->id)
                    ->orWhere('client_email', $user->email);
            })
            ->where(function (Builder $q) {
                $q->whereNull('metadata')
                    ->orWhereNull('metadata->creation_validation')
                    ->orWhere('metadata->creation_validation->status', 'approved');
            });
    }

    private function normalizeProjectImageUrl(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (preg_match('/^https?:\/\//i', $trimmed)) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/')) {
            return url($trimmed);
        }

        if (str_starts_with($trimmed, 'storage/')) {
            return url('/' . $trimmed);
        }

        return url(Storage::disk('public')->url($trimmed));
    }

    private function extractImageList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            return [$trimmed];
        }

        return [];
    }
}
