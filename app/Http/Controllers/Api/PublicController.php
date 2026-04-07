<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Service;
use App\Models\Formation;
use App\Models\ContactRequest;
use App\Models\Testimonial;
use App\Models\PortfolioProject;
use App\Models\LegalPage;
use App\Models\FormationEnrollment;
use App\Models\FormationSession;
use App\Models\Payment;
use App\Models\User;
use App\Services\MonerooService;
use App\Services\FormationEnrollmentWindowService;
use App\Services\FormationSessionSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur pour les données publiques du site
 * Ces endpoints ne nécessitent pas d'authentification
 */
class PublicController extends Controller
{
    /**
     * Récupérer tous les paramètres publics du site
     */
    public function settings(): JsonResponse
    {
        $settings = SiteSetting::getPublic();

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Récupérer les informations de contact
     */
    public function contact(): JsonResponse
    {
        $contactSettings = [
            'company_name' => SiteSetting::get('company_name', 'MADIBA BTP & CONSULTING'),
            'company_slogan' => SiteSetting::get('company_slogan', 'Construisons ensemble votre avenir'),
            'phone' => SiteSetting::get('phone', '+237 6XX XX XX XX'),
            'phone_secondary' => SiteSetting::get('phone_secondary'),
            'email' => SiteSetting::get('email', 'contact@madibabc.com'),
            'address' => SiteSetting::get('address', 'Douala, Cameroun'),
            'address_full' => SiteSetting::get('address_full'),
            'working_hours' => SiteSetting::get('working_hours', 'Lun - Ven: 8h - 18h'),
            'map_url' => SiteSetting::get('map_url'),
            'map_embed' => SiteSetting::get('map_embed'),
        ];

        return response()->json([
            'success' => true,
            'data' => $contactSettings
        ]);
    }

    /**
     * Récupérer les liens vers les réseaux sociaux
     */
    public function socialLinks(): JsonResponse
    {
        $socialLinks = [
            'facebook' => SiteSetting::get('facebook_url'),
            'instagram' => SiteSetting::get('instagram_url'),
            'linkedin' => SiteSetting::get('linkedin_url'),
            'twitter' => SiteSetting::get('twitter_url'),
            'youtube' => SiteSetting::get('youtube_url'),
            'whatsapp' => SiteSetting::get('whatsapp_number'),
        ];

        // Filtrer les valeurs nulles
        $socialLinks = array_filter($socialLinks);

        return response()->json([
            'success' => true,
            'data' => $socialLinks
        ]);
    }

    /**
     * Récupérer tous les services actifs
     */
    public function services(): JsonResponse
    {
        $services = Service::active()
            ->ordered()
            ->get(['id', 'title', 'slug', 'short_description', 'description', 'features', 'icon', 'cover_image', 'starting_price', 'is_featured']);

        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }

    /**
     * Récupérer un service par son slug
     */
    public function service(string $slug): JsonResponse
    {
        $service = Service::active()->where('slug', $slug)->first();

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service
        ]);
    }

    /**
     * Récupérer toutes les formations actives
     */
    public function formations(): JsonResponse
    {
        $formations = Formation::active()
            ->ordered()
            ->with(['formateur:id,name,speciality'])
            ->get([
                'id', 'title', 'slug', 'description', 'duration_hours', 'duration_days',
                'price', 'inscription_fee', 'level', 'category', 'cover_image', 'max_students', 'is_featured', 'formateur_id'
            ]);

        // Ajouter les prochaines sessions pour chaque formation
        $formations->each(function ($formation) {
            $formation->next_sessions = $formation->activeSessions()
                ->upcoming()
                ->orderBy('start_date')
                ->take(2)
                ->get(['id', 'start_date', 'end_date', 'status', 'max_students']);
        });

        return response()->json([
            'success' => true,
            'data' => $formations
        ]);
    }

    /**
     * Récupérer une formation par son slug
     */
    public function formation(string $slug): JsonResponse
    {
        $formation = Formation::active()
            ->with(['formateur:id,name,speciality,bio'])
            ->where('slug', $slug)
            ->first();

        if (!$formation) {
            return response()->json([
                'success' => false,
                'message' => 'Formation non trouvée'
            ], 404);
        }

        // Ajouter les prochaines sessions
        $formation->upcoming_sessions = $formation->activeSessions()
            ->upcoming()
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $formation
        ]);
    }

    /**
     * Récupérer les témoignages approuvés
     */
    public function testimonials(): JsonResponse
    {
        $testimonials = Testimonial::approved()
            ->ordered()
            ->with(['portfolioProject:id,title,slug'])
            ->get([
                'id', 'author_name', 'author_role', 'author_company', 'author_image',
                'content', 'rating', 'project_type', 'portfolio_project_id', 'is_featured'
            ]);

        return response()->json([
            'success' => true,
            'data' => $testimonials
        ]);
    }

    /**
     * Récupérer les projets portfolio publiés
     */
    public function portfolio(): JsonResponse
    {
        $projects = PortfolioProject::where('is_published', true)
            ->with(['media' => function ($query) {
                $query->where('type', 'image')->orderBy('created_at');
            }])
            ->orderByDesc('year')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    /**
     * Récupérer un projet portfolio par son slug
     */
    public function portfolioProject(string $slug): JsonResponse
    {
        $project = PortfolioProject::where('slug', $slug)
            ->where('is_published', true)
            ->with(['media' => function ($query) {
                $query->orderBy('created_at');
            }])
            ->first();

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé'
            ], 404);
        }

        // Charger les témoignages liés
        $project->testimonials = Testimonial::approved()
            ->where('portfolio_project_id', $project->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    /**
     * Vérifier si le site est en maintenance
     */
    public function maintenanceStatus(): JsonResponse
    {
        $isMaintenanceMode = SiteSetting::get('maintenance_mode', false);
        $maintenanceMessage = SiteSetting::get('maintenance_message', 'Le site est en maintenance. Veuillez réessayer plus tard.');

        return response()->json([
            'success' => true,
            'data' => [
                'is_maintenance' => (bool) $isMaintenanceMode,
                'message' => $maintenanceMessage
            ]
        ]);
    }

    /**
     * Données pour la page d'accueil (agrégation)
     */
    public function homepage(): JsonResponse
    {
        $data = [
            'company' => [
                'name' => SiteSetting::get('company_name', 'MADIBA BTP & CONSULTING'),
                'slogan' => SiteSetting::get('company_slogan', 'Construisons ensemble votre avenir'),
                'description' => SiteSetting::get('company_description'),
            ],
            'hero' => [
                'title' => SiteSetting::get('hero_title', 'Excellence en Construction'),
                'subtitle' => SiteSetting::get('hero_subtitle', 'Plus de 10 ans d\'expertise en BTP au Cameroun'),
                'image' => SiteSetting::get('hero_image'),
                'cta_text' => SiteSetting::get('hero_cta_text', 'Demander un devis'),
            ],
            'stats' => [
                'projects_completed' => (int) SiteSetting::get('stats_projects', 150),
                'years_experience' => (int) SiteSetting::get('stats_years', 10),
                'happy_clients' => (int) SiteSetting::get('stats_clients', 100),
                'trained_students' => (int) SiteSetting::get('stats_students', 500),
            ],
            'services' => Service::active()->featured()->ordered()->take(6)->get([
                'id', 'title', 'slug', 'short_description', 'icon', 'cover_image'
            ]),
            'formations' => Formation::active()->featured()->ordered()->take(3)->get([
                'id', 'title', 'slug', 'description', 'duration_hours', 'price', 'level', 'category', 'cover_image'
            ]),
            'portfolio' => PortfolioProject::where('is_published', true)
                ->orderByDesc('completion_date')
                ->take(6)
                ->get(['id', 'title', 'slug', 'category', 'cover_image', 'location']),
            'testimonials' => Testimonial::approved()->featured()->ordered()->take(4)->get([
                'id', 'author_name', 'author_role', 'author_company', 'content', 'rating'
            ]),
            'contact' => [
                'phone' => SiteSetting::get('phone'),
                'email' => SiteSetting::get('email'),
                'address' => SiteSetting::get('address'),
            ],
            'social' => array_filter([
                'facebook' => SiteSetting::get('facebook_url'),
                'instagram' => SiteSetting::get('instagram_url'),
                'linkedin' => SiteSetting::get('linkedin_url'),
                'whatsapp' => SiteSetting::get('whatsapp_number'),
            ]),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Récupérer une page légale par son slug (cgu, cgv, privacy-policy)
     */
    public function legalPage(string $slug): JsonResponse
    {
        $page = LegalPage::findBySlug($slug);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page légale non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'subtitle' => $page->subtitle,
                'content' => $page->content,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'last_updated' => $page->last_updated?->format('Y-m-d'),
            ]
        ]);
    }

    /**
     * Récupérer toutes les pages légales (liste)
     */
    public function legalPages(): JsonResponse
    {
        $pages = LegalPage::where('is_active', true)
            ->get(['slug', 'title', 'subtitle', 'meta_description', 'last_updated']);

        return response()->json([
            'success' => true,
            'data' => $pages
        ]);
    }

    /**
     * Enregistrer une demande d'inscription à une formation avec paiement
     */
    public function formationInscription(
        Request $request,
        FormationSessionSchedulerService $scheduler,
        FormationEnrollmentWindowService $enrollmentWindowService
    ): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'formation_id' => 'required|exists:formations,id',
            'session_id' => 'nullable|exists:formation_sessions,id',
            'message' => 'nullable|string|max:1000',
            'return_url' => 'nullable|url',
        ]);

        // Récupérer la formation
        $formation = Formation::find($validated['formation_id']);

        // S'assurer qu'il existe des sessions planifiées si l'automatisation est active
        $scheduler->ensureUpcomingSessions($formation);

        $sessionId = $validated['session_id'] ?? null;

        if ($sessionId) {
            $session = FormationSession::where('id', $sessionId)
                ->where('formation_id', $formation->id)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'La session sélectionnée n\'est plus disponible pour cette formation.',
                ], 422);
            }
        } else {
            $session = FormationSession::query()
                ->where('formation_id', $formation->id)
                ->whereDate('start_date', '>=', now()->toDateString())
                ->orderBy('start_date')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune session n\'est actuellement planifiée pour cette formation. Merci de contacter l\'administration.',
                ], 422);
            }

            $sessionId = $session->id;
        }
        
        // Frais d'inscription (par défaut 10 000 FCFA si non défini)
        $inscriptionFee = $formation->inscription_fee ?? 10000;
        $linkedUser = User::query()->where('email', $validated['email'])->first();
        $expiresAt = now()->addMinutes($enrollmentWindowService->getWindowMinutes());

        $enrollment = FormationEnrollment::query()
            ->where('formation_id', $formation->id)
            ->where('session_id', $sessionId)
            ->where('status', 'pending_payment')
            ->where(function ($query) use ($validated, $linkedUser) {
                $query->where('email', $validated['email']);

                if ($linkedUser) {
                    $query->orWhere('user_id', $linkedUser->id);
                }
            })
            ->latest('id')
            ->first();

        if ($enrollment) {
            $enrollment->update([
                'user_id' => $linkedUser?->id ?? $enrollment->user_id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'message' => $validated['message'] ?? null,
            ]);
            $enrollment = $enrollmentWindowService->reopenPaymentWindow($enrollment, null, 'public_inscription');
        } else {
            $enrollment = FormationEnrollment::create([
                'formation_id' => $formation->id,
                'session_id' => $sessionId,
                'user_id' => $linkedUser?->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'message' => $validated['message'] ?? null,
                'status' => 'pending_payment',
                'amount_paid' => 0,
                'enrolled_at' => now(),
                'metadata' => [
                    'inscription_fee' => $inscriptionFee,
                    'formation_price' => $formation->price,
                    'payment_window_started_at' => now()->toISOString(),
                    'payment_window_expires_at' => $expiresAt->toISOString(),
                    'payment_window_reason' => 'public_inscription',
                ],
            ]);
        }

        $existingPendingPayment = Payment::query()
            ->where('payable_type', FormationEnrollment::class)
            ->where('payable_id', $enrollment->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        // Initialiser le paiement via Moneroo
        $monerooService = app(MonerooService::class);
        
        $paymentResult = $monerooService->initiatePayment([
            'amount' => (int) $inscriptionFee,
            'currency' => 'XAF',
            'description' => "Frais d'inscription - " . $formation->title,
            'customer_email' => $validated['email'],
            'customer_first_name' => $validated['first_name'],
            'customer_last_name' => $validated['last_name'],
            'customer_phone' => $validated['phone'],
            'return_url' => $validated['return_url'] ?? config('app.frontend_url') . '/training/inscription/confirmation',
            'user_id' => $linkedUser?->id,
            'payable_type' => FormationEnrollment::class,
            'payable_id' => $enrollment->id,
            'payment_id' => $existingPendingPayment?->id,
            'reference' => $existingPendingPayment?->reference,
            'purpose' => 'formation_payment',
            'purpose_detail' => 'inscription_fee',
            'metadata' => [
                'formation_id' => $formation->id,
                'formation_title' => $formation->title,
                'session_id' => $sessionId,
                'payment_window_expires_at' => $enrollmentWindowService->getExpiresAt($enrollment)->toISOString(),
            ],
        ]);

        // Mettre à jour l'enrollment avec la référence de paiement
        $enrollment->update([
            'metadata' => array_merge($enrollment->metadata ?? [], [
                'payment_reference' => $paymentResult['reference'] ?? ($existingPendingPayment?->reference),
                'payment_id' => $paymentResult['payment_id'] ?? ($existingPendingPayment?->id),
            ]),
        ]);

        $paymentReference = $paymentResult['reference'] ?? $existingPendingPayment?->reference;
        $paymentLinkUrl = $paymentReference
            ? rtrim((string) config('app.frontend_url'), '/') . '/paiement/link/' . $paymentReference
            : null;

        if (!$paymentResult['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Demande enregistree en attente pendant 1 heure. Vous pouvez reprendre le paiement.',
                'data' => [
                    'enrollment_id' => $enrollment->id,
                    'formation' => $formation->title,
                    'inscription_fee' => $inscriptionFee,
                    'checkout_url' => null,
                    'payment_url' => $paymentLinkUrl,
                    'payment_reference' => $paymentReference,
                    'payment_retry_required' => true,
                    'expires_at' => $enrollmentWindowService->getExpiresAt($enrollment)->toISOString(),
                    'status' => 'pending_payment',
                ],
                'error' => $paymentResult['error'] ?? 'Erreur inconnue',
            ], 202);
        }

        return response()->json([
            'success' => true,
            'message' => 'Redirection vers le paiement',
            'data' => [
                'enrollment_id' => $enrollment->id,
                'formation' => $formation->title,
                'inscription_fee' => $inscriptionFee,
                'checkout_url' => $paymentResult['checkout_url'],
                'payment_url' => $paymentLinkUrl,
                'payment_reference' => $paymentReference,
                'payment_retry_required' => false,
                'expires_at' => $enrollmentWindowService->getExpiresAt($enrollment)->toISOString(),
                'status' => 'pending_payment',
            ]
        ]);
    }

    /**
     * Endpoint de santé du backend utilisé par le frontend.
     */
    public function health(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'ok',
                    'checked_at' => now()->toISOString(),
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Le backend ne répond pas correctement.',
                'data' => [
                    'status' => 'down',
                    'checked_at' => now()->toISOString(),
                ],
            ], 503);
        }
    }

    /**
     * Construire le message d'inscription
     */
    private function buildInscriptionMessage(array $data, Formation $formation): string
    {
        $message = "Nouvelle demande d'inscription à une formation\n\n";
        $message .= "Formation: {$formation->title}\n";
        $message .= "Prix: " . number_format($formation->price, 0, ',', ' ') . " FCFA\n";
        $message .= "Frais d'inscription: " . number_format($formation->inscription_fee ?? 10000, 0, ',', ' ') . " FCFA\n";
        $message .= "Catégorie: {$formation->category}\n\n";
        $message .= "Candidat:\n";
        $message .= "- Nom: {$data['last_name']}\n";
        $message .= "- Prénom: {$data['first_name']}\n";
        $message .= "- Email: {$data['email']}\n";
        $message .= "- Téléphone: {$data['phone']}\n";

        if (!empty($data['session_id'])) {
            $message .= "\nSession préférée ID: {$data['session_id']}\n";
        }

        if (!empty($data['message'])) {
            $message .= "\nMessage du candidat:\n{$data['message']}\n";
        }

        return $message;
    }
}
