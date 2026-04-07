<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PortfolioProjectController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewPasswordController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\FormateurController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\TeamMemberController;
use App\Http\Controllers\Api\ApprenantController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CertificateRequestController;
use App\Http\Controllers\Api\FormateurCertificateController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\SecretaireController;
use App\Http\Controllers\Api\ChefChantierController;

// ==========================================
// Routes publiques du site
// ==========================================
Route::prefix('public')->group(function () {
    // Données globales
    Route::get('/settings', [PublicController::class, 'settings']);
    Route::get('/contact-info', [PublicController::class, 'contact']);
    Route::get('/social-links', [PublicController::class, 'socialLinks']);
    Route::get('/health', [PublicController::class, 'health']);
    Route::get('/maintenance-status', [PublicController::class, 'maintenanceStatus']);
    Route::get('/homepage', [PublicController::class, 'homepage']);

    // Équipe
    Route::get('/team', [TeamMemberController::class, 'publicTeam']);

    // Services
    Route::get('/services', [PublicController::class, 'services']);
    Route::get('/services/{slug}', [PublicController::class, 'service']);

    // Formations
    Route::get('/formations', [PublicController::class, 'formations']);
    Route::get('/formations/{slug}', [PublicController::class, 'formation']);

    // Certificats
    Route::get('/certificats/verify/{reference}', [CertificateController::class, 'verify']);

    // Inscription formation
    Route::post('/formation-inscription', [PublicController::class, 'formationInscription']);

    // Témoignages
    Route::get('/testimonials', [PublicController::class, 'testimonials']);

    // Portfolio
    Route::get('/portfolio', [PublicController::class, 'portfolio']);
    Route::get('/portfolio/{slug}', [PublicController::class, 'portfolioProject']);

    // Pages légales
    Route::get('/legal', [PublicController::class, 'legalPages']);
    Route::get('/legal/{slug}', [PublicController::class, 'legalPage'])->where('slug', 'cgu|cgv|privacy-policy|mentions-legales');
});

// Contact form (public avec rate limiting)
Route::post('/contact', [ContactController::class, 'submit']);

// ==========================================
// Routes de paiement (publiques pour webhook)
// ==========================================
Route::prefix('payments')->group(function () {
    // Webhook Moneroo (public - appelé par Moneroo)
    Route::post('/webhook/moneroo', [PaymentController::class, 'webhook']);

    // Méthodes de paiement disponibles (public)
    Route::get('/methods', [PaymentController::class, 'methods']);

    // Vérification du statut d'un paiement (public)
    Route::get('/verify/{reference}', [PaymentController::class, 'verify']);

    // Détails d'un paiement (public pour lien de paiement)
    Route::get('/{reference}', [PaymentController::class, 'show']);

    // Relancer un paiement via un lien public sécurisé par référence
    Route::post('/pay-pending/{reference}', [PaymentController::class, 'payPending'])->middleware('idempotency');

    // Vérifier un code promo (public)
    Route::post('/check-promo', [PaymentController::class, 'checkPromo']);
});

// ==========================================
// Routes d'authentification (publiques)
// ==========================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor']);
    Route::post('/two-factor/resend', [AuthController::class, 'resendTwoFactorCode']);

    // Routes pour l'invitation des employés (publiques)
    Route::post('/verify-invitation', [AuthController::class, 'verifyInvitation']);
    Route::post('/complete-profile', [AuthController::class, 'completeProfile']);

    // Password Reset
    Route::post('/forgot-password', [NewPasswordController::class, 'forgotPassword']);
    Route::post('/reset-password', [NewPasswordController::class, 'resetPassword']);
});

// ==========================================
// Routes protégées (nécessitent authentification)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::delete('/account', [AuthController::class, 'deleteAccount']);

        // Role switching
        Route::post('/switch-role', [AuthController::class, 'switchRole']);

        // Self-service: add role to own account (apprenant or client only)
        Route::post('/add-role', [AuthController::class, 'addRoleToSelf']);

        // Routes admin pour gestion des employés
        Route::post('/create-employee', [AuthController::class, 'createEmployee']);
        Route::post('/resend-invitation/{userId}', [AuthController::class, 'resendInvitation']);

        // Routes admin pour gestion des rôles multiples
        Route::post('/users/{targetUser}/roles', [AuthController::class, 'addRoleToUser']);
        Route::delete('/users/{targetUser}/roles', [AuthController::class, 'removeRoleFromUser']);
    });

    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ==========================================
    // Routes de paiement (authentifiées)
    // ==========================================
    Route::prefix('payments')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiate'])->middleware('idempotency');
        Route::post('/enrollment', [PaymentController::class, 'initiateEnrollmentPayment'])->middleware('idempotency');
        Route::post('/verify', [PaymentController::class, 'verify']);
        Route::get('/history', [PaymentController::class, 'history']);
        Route::get('/purposes', [PaymentController::class, 'getPurposes']);
        
        // Gestion des reçus
        Route::get('/{reference}/receipt', [PaymentController::class, 'generateReceipt']);
        Route::get('/{reference}/receipt/download', [PaymentController::class, 'downloadReceipt']);
        Route::post('/{reference}/receipt/send', [PaymentController::class, 'sendReceiptByEmail']);
    });

    // ==========================================
    // Routes Admin (nécessitent rôle admin)
    // ==========================================
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Dashboard
        Route::get('/stats', [AdminController::class, 'dashboardStats']);
        Route::get('/activities', [AdminController::class, 'getActivities']);

        // Users management
        Route::get('/users', [AdminController::class, 'listUsers']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::get('/users/{user}', [AdminController::class, 'getUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::patch('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
        Route::get('/users/export', [AdminController::class, 'exportUsers']);
        Route::get('/users/{user}/login-history', [AdminController::class, 'getUserLoginHistory']);
        Route::post('/users/{user}/send-reset-password', [AdminController::class, 'sendPasswordResetEmail']);

        // Roles management
        Route::get('/roles', [AdminController::class, 'listRoles']);
        Route::post('/roles', [AdminController::class, 'createRole']);
        Route::put('/roles/{role}', [AdminController::class, 'updateRole']);
        Route::delete('/roles/{role}', [AdminController::class, 'deleteRole']);

        // Contact requests management
        Route::get('/contacts', [ContactController::class, 'index']);
        Route::get('/contacts/{contact}', [ContactController::class, 'show']);
        Route::put('/contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
        Route::post('/contacts/{contact}/respond', [ContactController::class, 'respondToQuote']);

        // Team members management
        Route::get('/team', [TeamMemberController::class, 'index']);
        Route::post('/team', [TeamMemberController::class, 'store']);
        Route::get('/team/{member}', [TeamMemberController::class, 'show']);
        Route::post('/team/{member}', [TeamMemberController::class, 'update']); // POST pour multipart/form-data
        Route::delete('/team/{member}', [TeamMemberController::class, 'destroy']);
        Route::post('/team/reorder', [TeamMemberController::class, 'reorder']);
        Route::patch('/team/{member}/toggle-visibility', [TeamMemberController::class, 'toggleVisibility']);

        // Portfolio management (admin)
        Route::get('/portfolio-projects', [PortfolioProjectController::class, 'adminIndex']);
        Route::post('/portfolio-projects', [PortfolioProjectController::class, 'store']);
        Route::post('/portfolio-projects/upload-image', [PortfolioProjectController::class, 'uploadImage']);
        Route::get('/portfolio-projects/{project}', [PortfolioProjectController::class, 'adminShow']);
        Route::put('/portfolio-projects/{project}', [PortfolioProjectController::class, 'update']);
        Route::delete('/portfolio-projects/{project}', [PortfolioProjectController::class, 'destroy']);

        // Site Settings management
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::get('/settings/group/{group}', [SettingsController::class, 'group']);
        Route::post('/settings', [SettingsController::class, 'store']);
        Route::put('/settings/batch', [SettingsController::class, 'updateBatch']);
        Route::post('/settings/email/test', [SettingsController::class, 'testEmail']);
        Route::put('/settings/{key}', [SettingsController::class, 'update']);
        Route::post('/settings/{key}/upload', [SettingsController::class, 'uploadImage']);
        Route::delete('/settings/{key}', [SettingsController::class, 'destroy']);
        Route::post('/settings/maintenance', [SettingsController::class, 'toggleMaintenance']);

        // Contact requests management
        Route::get('/contacts', [ContactController::class, 'index']);
        Route::get('/contacts/{contact}', [ContactController::class, 'show']);
        Route::put('/contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);

        // Formations management (admin)
        Route::get('/formations-admin', [FormationController::class, 'index']);
        Route::post('/formations-admin', [FormationController::class, 'store']);
        Route::get('/formations-admin/stats', [FormationController::class, 'stats']);
        Route::get('/formations-admin/categories', [FormationController::class, 'categories']);
        Route::get('/formations-admin/{formation}', [FormationController::class, 'show']);
        Route::put('/formations-admin/{formation}', [FormationController::class, 'update']);
        Route::delete('/formations-admin/{formation}', [FormationController::class, 'destroy']);
        Route::patch('/formations-admin/{formation}/toggle-status', [FormationController::class, 'toggleStatus']);
        Route::get('/formations-admin/{formation}/sessions', [FormationController::class, 'sessions']);
        Route::post('/formations-admin/{formation}/sessions', [FormationController::class, 'createSession']);

        // Enrollments management (admin)
        Route::get('/enrollments', [AdminController::class, 'listEnrollments']);
        Route::get('/enrollments/{enrollment}', [AdminController::class, 'getEnrollment']);
        Route::put('/enrollments/{enrollment}', [AdminController::class, 'updateEnrollment']);

        // Financial Reports
        Route::get('/reports/financial', [AdminController::class, 'listFinancialReports']);
        Route::post('/reports/financial/generate', [AdminController::class, 'generateFinancialReport']);

        // New Reports
        Route::get('/reports/payment-history', [AdminController::class, 'generatePaymentHistory']);
        Route::get('/reports/clients/export', [AdminController::class, 'exportClients']);
        Route::get('/reports/projects/progress', [AdminController::class, 'generateProjectProgress']);
        Route::get('/reports/projects/budget', [AdminController::class, 'generateProjectBudget']);
        Route::get('/reports/users/activity', [AdminController::class, 'generateUserActivity']);
        Route::get('/reports/operations/training', [AdminController::class, 'generateTrainingSynthesis']);
        Route::get('/reports/operations/personnel', [AdminController::class, 'generatePersonnelHours']);
    });

    // ==========================================
    // Routes Secrétaire (nécessitent rôle secretaire ou admin)
    // ==========================================
    Route::prefix('secretaire')->middleware('role:secretaire,admin')->group(function () {
        Route::get('/dashboard', [SecretaireController::class, 'dashboard']);

        // Gestion des apprenants
        Route::get('/apprenants', [SecretaireController::class, 'listApprenants']);
        Route::get('/apprenants/{apprenant}', [SecretaireController::class, 'getApprenant']);
        Route::put('/apprenants/{apprenant}', [SecretaireController::class, 'updateApprenant']);

        // Gestion des inscriptions
        Route::get('/enrollments', [SecretaireController::class, 'listEnrollments']);
        Route::get('/enrollments/{enrollment}', [SecretaireController::class, 'getEnrollment']);
        Route::put('/enrollments/{enrollment}/status', [SecretaireController::class, 'updateEnrollmentStatus']);

        // Demandes de certificat
        Route::get('/certificate-requests', [CertificateRequestController::class, 'index']);
        Route::post('/certificate-requests/{certificateRequest}/approve', [CertificateRequestController::class, 'approve']);
        Route::post('/certificate-requests/{certificateRequest}/reject', [CertificateRequestController::class, 'reject']);
        Route::post('/certificate-requests/{certificateRequest}/invalidate', [CertificateRequestController::class, 'invalidate']);

        // Gestion des paiements
        Route::get('/paiements', [SecretaireController::class, 'listPayments']);
        Route::get('/paiements/{payment}', [SecretaireController::class, 'getPayment']);
        Route::post('/paiements/{payment}/validate', [SecretaireController::class, 'validatePayment']);
        Route::post('/paiements/{payment}/reject', [SecretaireController::class, 'rejectPayment']);

        // Reçus
        Route::get('/recus', [SecretaireController::class, 'listReceipts']);
        Route::get('/recus/{payment}/download', [SecretaireController::class, 'downloadReceipt']);
        Route::post('/recus/{payment}/ignore', [SecretaireController::class, 'ignoreReceiptWarning']);

        // Registre
        Route::get('/registre', [SecretaireController::class, 'getRegistre']);
        Route::get('/registre/export', [SecretaireController::class, 'exportRegistre']);

        // Projets (vue limitée)
        Route::get('/projets', [SecretaireController::class, 'listProjets']);
        Route::get('/projets/{projet}', [SecretaireController::class, 'getProjet']);
        Route::post('/projets/{projet}/assign-client', [SecretaireController::class, 'assignProjetClient']);
        Route::post('/projets/{projet}/validate-creation', [SecretaireController::class, 'validateProjetCreation']);
        Route::get('/projets/{projet}/phase-transition', [SecretaireController::class, 'getProjetPhaseState']);
        Route::post('/projets/{projet}/phase-transition', [SecretaireController::class, 'updateProjetPhase']);

        // Clients
        Route::get('/clients', [SecretaireController::class, 'listClients']);

        // Codes promo
        Route::get('/promo-codes', [SecretaireController::class, 'listPromoCodes']);
        Route::post('/promo-codes', [SecretaireController::class, 'createPromoCode']);
        Route::put('/promo-codes/{id}', [SecretaireController::class, 'updatePromoCode']);
        Route::delete('/promo-codes/{id}', [SecretaireController::class, 'deletePromoCode']);

        // Demandes de devis (Quote requests)
        Route::get('/contacts', [ContactController::class, 'index']);
        Route::get('/contacts/{contact}', [ContactController::class, 'show']);
        Route::put('/contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
        Route::post('/contacts/{contact}/respond', [ContactController::class, 'respondToQuote']);

        // Reçus manuels
        Route::post('/recus/manual', [SecretaireController::class, 'createManualReceipt'])->middleware('idempotency');
        Route::get('/recus/{payment}/pdf', [SecretaireController::class, 'generateReceiptPDF']);

        // Création Utilisateurs
        Route::post('/clients', [SecretaireController::class, 'createClient']);
        Route::post('/apprenants', [SecretaireController::class, 'createApprenant']);

        // Liens de paiement
        Route::post('/paiements/generate-link', [SecretaireController::class, 'generatePaymentLink'])->middleware('idempotency');

        // Financial Reports
        Route::get('/reports/financial', [SecretaireController::class, 'listFinancialReports']);
        Route::post('/reports/financial', [SecretaireController::class, 'storeFinancialReport']);
        Route::post('/reports/financial/generate', [SecretaireController::class, 'generateFinancialReport']);
    });

    // ==========================================
    // Routes Chef Chantier (nécessitent rôle chef_chantier ou admin)
    // ==========================================
    Route::prefix('chef-chantier')->middleware('role:chef_chantier,admin')->group(function () {
        Route::get('/dashboard', [ChefChantierController::class, 'dashboard']);

        // Gestion des chantiers
        Route::get('/chantiers', [ChefChantierController::class, 'listChantiers']);
        Route::post('/chantiers', [ChefChantierController::class, 'createChantier']);
        Route::get('/chantiers/{chantier}', [ChefChantierController::class, 'getChantier']);
        Route::get('/chantiers/{chantier}/stats', [ChefChantierController::class, 'getChantierStats']);
        Route::get('/chantiers/{chantier}/weekly-summary', [ChefChantierController::class, 'getWeeklySummary']);
        Route::put('/chantiers/{chantier}', [ChefChantierController::class, 'updateChantier']);
        Route::put('/chantiers/{chantier}/progress', [ChefChantierController::class, 'updateProgress']);
        Route::post('/chantiers/{chantier}/photos', [ChefChantierController::class, 'uploadPhotos']);
        Route::get('/chantiers/{chantier}/phase-transition', [ChefChantierController::class, 'getPhaseTransitionState']);
        Route::post('/chantiers/{chantier}/phase-transition', [ChefChantierController::class, 'requestPhaseTransition']);

        // Avancements
        Route::get('/avancements', [ChefChantierController::class, 'listAvancements']);
        Route::post('/avancements', [ChefChantierController::class, 'createAvancement']);
        Route::get('/avancements/{avancement}', [ChefChantierController::class, 'getAvancement']);
        Route::put('/avancements/{avancement}', [ChefChantierController::class, 'updateAvancement']);

        // Logs journaliers
        Route::get('/daily-logs', [ChefChantierController::class, 'listDailyLogs']);
        Route::post('/daily-logs', [ChefChantierController::class, 'createDailyLog']);

        // Incidents de sécurité
        Route::get('/incidents', [ChefChantierController::class, 'listIncidents']);
        Route::get('/incidents/types', [ChefChantierController::class, 'getIncidentTypes']);
        Route::post('/incidents', [ChefChantierController::class, 'reportIncident']);
        Route::put('/incidents/{incident}/resolve', [ChefChantierController::class, 'resolveIncident']);

        // Équipes
        Route::get('/equipes', [ChefChantierController::class, 'listEquipes']);
        Route::post('/equipes', [ChefChantierController::class, 'createEquipe']);
        Route::put('/equipes/{equipe}', [ChefChantierController::class, 'updateEquipe']);

        // Rapports
        Route::get('/rapports', [ChefChantierController::class, 'listRapports']);
        Route::post('/rapports', [ChefChantierController::class, 'createRapport']);

        // Messages
        Route::get('/messages', [ChefChantierController::class, 'listMessages']);
        Route::post('/messages', [ChefChantierController::class, 'sendMessage']);
    });


    // ==========================================
    // Routes Apprenant (nécessitent rôle apprenant)
    // ==========================================
    Route::prefix('apprenant')->middleware('role:apprenant')->group(function () {
        Route::get('/dashboard', [ApprenantController::class, 'dashboard']);

        // Formations inscrites
        Route::get('/formations', [ApprenantController::class, 'myFormations']);
        Route::get('/formations/{enrollment}', [ApprenantController::class, 'getFormation']);

        // Certificats
        Route::get('/certificats', [ApprenantController::class, 'myCertificats']);
        Route::get('/certificats/{certificat}/download', [ApprenantController::class, 'downloadCertificat']);

        // Paiements
        Route::get('/paiements', [ApprenantController::class, 'myPaiements']);
        Route::get('/paiements/{payment}', [ApprenantController::class, 'getPaiement']);
        Route::post('/paiements/formation/initiate', [ApprenantController::class, 'initiateFormationPayment'])->middleware('idempotency');

        // Reçus
        Route::get('/recus', [ApprenantController::class, 'myRecus']);
        Route::get('/recus/{payment}/pdf', [ApprenantController::class, 'previewRecu']);
        Route::get('/recus/{payment}/download', [ApprenantController::class, 'downloadRecu']);

        // Profil
        Route::get('/profil', [ApprenantController::class, 'getProfil']);
        Route::put('/profil', [ApprenantController::class, 'updateProfil']);
    });

    // ==========================================
    // Routes Client (nécessitent rôle client)
    // ==========================================
    Route::prefix('client')->middleware('role:client')->group(function () {
        Route::get('/dashboard', [ClientController::class, 'dashboard']);

        // Mes projets
        Route::get('/projets', [ClientController::class, 'myProjets']);
        Route::get('/projets/{projet}', [ClientController::class, 'getProjet']);

        // Suivi chantier
        Route::get('/suivi-chantier', [ClientController::class, 'suiviChantier']);
        Route::get('/suivi-chantier/{projet}', [ClientController::class, 'getSuiviChantier']);
        Route::get('/suivi-chantier/{projet}/photos', [ClientController::class, 'getPhotosChantier']);

        // Devis et factures
        Route::get('/devis', [ClientController::class, 'myDevis']);
        Route::get('/factures', [ClientController::class, 'myFactures']);
        Route::post('/factures/{payment}/pay', [ClientController::class, 'payInvoice']);

        // Messages
        Route::get('/messages', [ClientController::class, 'myMessages']);
        Route::post('/messages', [ClientController::class, 'sendMessage']);

        // Profil
        Route::get('/profil', [ClientController::class, 'getProfil']);
        Route::put('/profil', [ClientController::class, 'updateProfil']);
    });

    // ==========================================
    // Routes Formateur (nécessitent rôle formateur)
    // ==========================================
    Route::prefix('formateur')->middleware('role:formateur,admin')->group(function () {
        Route::get('/dashboard', [FormateurController::class, 'dashboard']);

        // Apprenants
        Route::get('/apprenants', [FormateurController::class, 'listApprenants']);

        // Certificats
        Route::get('/certificats', [FormateurCertificateController::class, 'index']);
        Route::post('/certificats/enrollments/{enrollment}/request', [FormateurCertificateController::class, 'requestCertificate']);

        // Evaluations
        Route::get('/evaluations', [FormateurController::class, 'listEvaluations']);
        Route::post('/evaluations', [FormateurController::class, 'createEvaluation']);
        Route::get('/evaluations/{evaluation}/notes', [FormateurController::class, 'getEvaluationNotes']);
        Route::post('/evaluations/{evaluation}/notes', [FormateurController::class, 'saveEvaluationNotes']);

        // Présences
        Route::get('/presences', [FormateurController::class, 'getPresences']);
        Route::post('/presences', [FormateurController::class, 'savePresences']);

        // Formations management (formateur)
        Route::get('/formations', [FormationController::class, 'index']);
        Route::post('/formations', [FormationController::class, 'store']);
        Route::get('/formations/stats', [FormationController::class, 'stats']);
        Route::get('/formations/{formation}', [FormationController::class, 'show']);
        Route::put('/formations/{formation}', [FormationController::class, 'update']);
        Route::delete('/formations/{formation}', [FormationController::class, 'destroy']);
        Route::patch('/formations/{formation}/toggle-status', [FormationController::class, 'toggleStatus']);
        Route::get('/formations/{formation}/sessions', [FormationController::class, 'sessions']);
        Route::post('/formations/{formation}/sessions', [FormationController::class, 'createSession']);
    });
});

// ==========================================
// Portfolio Routes (publiques)
// ==========================================
Route::get('/portfolio-projects', [PortfolioProjectController::class, 'index']);
Route::get('/portfolio-projects/{slug}', [PortfolioProjectController::class, 'show']);
