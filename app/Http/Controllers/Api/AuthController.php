<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoginTwoFactorCode;
use App\Models\TwoFactorLoginChallenge;
use App\Models\TwoFactorLoginChallengeSend;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\StaffInvitation;
use App\Models\ActivityLog;
use App\Services\EnrollmentOwnershipService;
use App\Services\LegalAcceptanceService;
use App\Services\RecaptchaService;
use App\Jobs\ProcessLoginHistory;

class AuthController extends Controller
{
    public function __construct(
        private EnrollmentOwnershipService $enrollmentOwnershipService,
        private LegalAcceptanceService $legalAcceptanceService
    ) {
    }

    /**
     * Register a new user (only client or apprenant)
     * Supports registering with multiple roles at once
     */
    public function register(Request $request, RecaptchaService $recaptcha)
    {
        // Support single role (legacy) or multiple roles
        $roles = $request->roles ?? [$request->role ?? 'apprenant'];
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        // Filter to only self-registrable roles
        $validRoles = array_filter($roles, fn($r) => in_array($r, User::SELF_REGISTER_ROLES));

        if (empty($validRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce type de compte ne peut pas être créé par inscription directe. Contactez l\'administration.'
            ], 403);
        }

        // Validation de base
        $baseRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(6)],
            'phone' => 'nullable|string|max:20',
        ];

        // Règles spécifiques selon les rôles demandés
        $roleRules = [];
        if (in_array('client', $validRoles)) {
            $roleRules = array_merge($roleRules, [
                'company_name' => 'nullable|string|max:255',
                'company_address' => 'nullable|string|max:500',
                'project_type' => 'nullable|string|max:100',
                'project_description' => 'nullable|string|max:2000',
            ]);
        }
        if (in_array('apprenant', $validRoles)) {
            $roleRules = array_merge($roleRules, [
                'formation' => 'required|string|max:255',
            ]);
        }

        $validator = Validator::make($request->all(), array_merge($baseRules, $roleRules));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $recaptchaResult = $recaptcha->verifyToken($request->input('recaptcha_token'), 'register');
        if (!$recaptchaResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $recaptchaResult['message'] ?? 'Échec de la vérification reCAPTCHA.',
            ], 422);
        }

        // Primary role is the first one provided
        $primaryRole = $validRoles[0];

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $primaryRole,
            'is_active' => true,
            'profile_completed' => true,
        ];

        // Ajouter les données spécifiques aux rôles
        if (in_array('client', $validRoles)) {
            $userData['company_name'] = $request->company_name;
            $userData['company_address'] = $request->company_address;
            $userData['project_type'] = $request->project_type;
            $userData['project_description'] = $request->project_description;
        }
        if (in_array('apprenant', $validRoles)) {
            $userData['formation'] = $request->formation;
            $userData['enrollment_date'] = now();
        }

        $user = User::create($userData);

        // Add all roles to user
        foreach ($validRoles as $index => $roleSlug) {
            $user->addRole($roleSlug, $index === 0); // First role is primary
        }

        $this->enrollmentOwnershipService->attachByEmail($user);
        $this->legalAcceptanceService->recordCurrentAcceptances($user, $request, 'register');

        $user->refresh();
        $user->load('roles', 'activeRole');

        $token = $user->createToken('auth_token')->plainTextToken;

        // Send welcome email
        try {
            if ($user->role === 'apprenant') {
                Mail::to($user->email)->send(new \App\Mail\RegistrationThanks($user));
                
                ActivityLog::log(
                    $user,
                    'Nouveau membre',
                    $user->name . ' a rejoint la plateforme en tant qu\'apprenant',
                    $user
                );
            } elseif ($user->role === 'client') {
                Mail::to($user->email)->send(new \App\Mail\RegistrationThanks($user));
                
                ActivityLog::log(
                    $user,
                    'Nouveau client',
                    $user->name . ' a rejoint la plateforme en tant que client',
                    $user
                );
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send registration email: ' . $e->getMessage());
            // Continue anyway - registration succeeded
        }

        dispatch(new ProcessLoginHistory($user, $request->ip(), $request->userAgent()));

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Add a role to the current authenticated user (self-service)
     * Only allows adding self-registrable roles (apprenant, client)
     */
    public function addRoleToSelf(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:apprenant,client',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rôle invalide. Vous pouvez uniquement ajouter les rôles apprenant ou client.',
                'errors' => $validator->errors()
            ], 422);
        }

        $roleSlug = $request->role;

        // Check if user already has this role
        if ($user->hasRole($roleSlug)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà ce rôle.'
            ], 400);
        }

        // Validate role-specific data
        if ($roleSlug === 'apprenant') {
            $roleValidator = Validator::make($request->all(), [
                'formation' => 'required|string|max:255',
            ]);

            if ($roleValidator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez spécifier une formation.',
                    'errors' => $roleValidator->errors()
                ], 422);
            }

            // Update user with apprenant data
            $user->update([
                'formation' => $request->formation,
                'enrollment_date' => now(),
            ]);
        }

        if ($roleSlug === 'client') {
            // Update user with client data if provided
            $user->update([
                'company_name' => $request->company_name ?? $user->company_name,
                'company_address' => $request->company_address ?? $user->company_address,
                'project_type' => $request->project_type ?? $user->project_type,
                'project_description' => $request->project_description ?? $user->project_description,
            ]);
        }

        // Add the role
        $user->addRole($roleSlug);

        $user->refresh();
        $user->load('roles', 'activeRole');

        return response()->json([
            'success' => true,
            'message' => 'Rôle ajouté avec succès. Vous pouvez maintenant accéder à l\'espace ' . ($roleSlug === 'client' ? 'client' : 'apprenant') . '.',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Login user
     */
    public function login(Request $request, RecaptchaService $recaptcha)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $recaptchaResult = $recaptcha->verifyToken($request->input('recaptcha_token'), 'login');
        if (!$recaptchaResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $recaptchaResult['message'] ?? 'Échec de la vérification reCAPTCHA.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été désactivé. Contactez l\'administration.'
            ], 403);
        }

        $this->legalAcceptanceService->recordCurrentAcceptances($user, $request, 'login');

        $activeChallenge = $this->getActiveTwoFactorChallenge($user);
        if ($activeChallenge) {
            return response()->json([
                'success' => true,
                'message' => 'Un code de vérification est déjà en attente dans votre boîte mail.',
                'requires_two_factor' => true,
                'two_factor' => $this->buildTwoFactorPayload($user, $activeChallenge),
            ]);
        }

        $sendState = $this->getTwoFactorSendState($user);
        if (!$sendState['can_send']) {
            return response()->json([
                'success' => false,
                'message' => $sendState['message'],
                'errors' => [
                    'two_factor' => [$sendState['message']],
                ],
                'retry_after_seconds' => $sendState['retry_after_seconds'],
            ], 429);
        }

        $challenge = $this->createTwoFactorChallenge($user);
        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de générer le code de vérification. Réessayez.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Un code de vérification a été envoyé par email.',
            'requires_two_factor' => true,
            'two_factor' => $this->buildTwoFactorPayload($user, $challenge),
        ]);
    }

    /**
     * Vérifier le code 2FA et finaliser la connexion
     */
    public function verifyTwoFactor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'remember_me' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $challenge = TwoFactorLoginChallenge::query()
            ->with('user.roles', 'user.activeRole')
            ->where('challenge_token', $request->challenge_token)
            ->first();

        if (!$challenge || $challenge->verified_at || $challenge->consumed_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cette session de vérification n\'est plus valide. Veuillez relancer la connexion.',
            ], 422);
        }

        if (!$challenge->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre code a expiré. Veuillez cliquer sur "Renvoyer le code" pour en obtenir un nouveau.',
            ], 422);
        }

        if (!Hash::check($request->code, $challenge->code_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Le code saisi est incorrect.',
            ], 422);
        }

        $user = $challenge->user;
        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte n\'est plus disponible.',
            ], 403);
        }

        DB::transaction(function () use ($challenge, $user): void {
            TwoFactorLoginChallenge::query()
                ->where('user_id', $user->id)
                ->whereNull('verified_at')
                ->update(['consumed_at' => now()]);

            $challenge->update([
                'verified_at' => now(),
                'consumed_at' => now(),
            ]);
        });

        $user->update(['last_login_at' => now()]);
        $this->enrollmentOwnershipService->attachByEmail($user);
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        dispatch(new ProcessLoginHistory($user, $request->ip(), $request->userAgent()));

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ]);
    }

    /**
     * Renvoyer le code 2FA
     */
    public function resendTwoFactorCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $challenge = TwoFactorLoginChallenge::query()
            ->with('user.roles', 'user.activeRole', 'sends')
            ->where('challenge_token', $request->challenge_token)
            ->first();

        if (!$challenge || $challenge->verified_at || $challenge->consumed_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ce challenge de vérification n\'est plus valide. Veuillez relancer la connexion.',
            ], 422);
        }

        // Si le challenge est expiré, on le "ranime" au lieu de le déclarer invalide
        // tant qu'il n'a jamais été consommé.
        $wasExpired = !$challenge->isActive();

        $sendState = $this->getTwoFactorSendState($challenge->user);
        if (!$sendState['can_send']) {
            return response()->json([
                'success' => false,
                'message' => $sendState['message'],
                'errors' => [
                    'two_factor' => [$sendState['message']],
                ],
                'retry_after_seconds' => $sendState['retry_after_seconds'],
            ], 429);
        }

        $previousState = [
            'code_hash' => $challenge->code_hash,
            'expires_at' => $challenge->expires_at,
        ];

        $code = $this->generateTwoFactorCode();
        $challenge->update([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $sendRecord = $challenge->sends()->create();

        try {
            Log::info('Attempting to send 2FA code', [
                'user_id' => $challenge->user->id,
                'email' => $challenge->user->email,
                'challenge_id' => $challenge->id,
                'was_expired' => $wasExpired ?? false,
            ]);

            Mail::to($challenge->user->email)->send(new LoginTwoFactorCode(
                $challenge->user,
                $code,
                $challenge->expires_at->toIso8601String(),
                $request->ip(),
                $request->userAgent()
            ));

            Log::info('2FA code email sent successfully to relay', [
                'user_id' => $challenge->user->id,
            ]);
        } catch (\Throwable $exception) {
            Log::error('CRITICAL: Failed to send 2FA code email', [
                'user_id' => $challenge->user_id,
                'challenge_id' => $challenge->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $sendRecord->delete();
            $challenge->update($previousState);

            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'envoyer le code de vérification. Réessayez dans quelques instants.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Un nouveau code de vérification a été envoyé.',
            'requires_two_factor' => true,
            'two_factor' => $this->buildTwoFactorPayload($challenge->user, $challenge),
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'current_password' => 'required_with:password|string',
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier le mot de passe actuel si on change le mot de passe
        if ($request->password) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 422);
            }
            $user->password = Hash::make($request->password);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Delete the current authenticated account.
     * Only self-registered account types can delete themselves.
     */
    public function deleteAccount(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'confirmation' => 'required|string|in:SUPPRIMER',
        ], [
            'confirmation.in' => 'La confirmation doit être exactement "SUPPRIMER".',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect',
            ], 422);
        }

        $roleSlugs = $user->getRoleSlugs();
        $nonSelfServiceRoles = array_diff($roleSlugs, User::SELF_REGISTER_ROLES);

        if (empty($roleSlugs) || !empty($nonSelfServiceRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce type de compte ne peut pas être supprimé par son titulaire. Contactez un administrateur.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($user): void {
                $user->tokens()->delete();
                $user->roles()->detach();
                $user->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Votre compte a été supprimé définitivement.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Self-service account deletion failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'La suppression du compte a échoué. Veuillez réessayer plus tard.',
            ], 500);
        }
    }

    /**
     * Create employee account (admin only)
     */
    public function createEmployee(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur peut créer des comptes employés'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:admin,secretaire,chef_chantier',
            'employee_id' => 'required|string|max:50|unique:users',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6', // Optional predefined password
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Determine password: use provided or generate random 10 chars
        $plainPassword = $request->password ?: Str::random(10);



        // Créer l'utilisateur avec un mot de passe
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
            'role' => $request->role,
            'employee_id' => $request->employee_id,
            'address' => $request->address,
            'phone' => $request->phone,
            'is_active' => true, // Active immediately since password is known/sent
            'profile_completed' => false, // Still need to review/complete profile?
        ]);

        // Générer le token d'invitation
        $invitationToken = $user->generateInvitationToken();
        $invitationUrl = config('app.frontend_url', 'http://localhost:3000') . '/completer-profil?token=' . $invitationToken;

        // Send Invitation Email
        try {
            Mail::to($user->email)->send(new StaffInvitation($user, $invitationUrl, $plainPassword));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send staff invitation: ' . $e->getMessage());
        }

        // Log activity
        ActivityLog::log(
            $request->user(),
            'Nouvel employé',
            'A créé le compte employé pour ' . $user->name,
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Compte employé créé avec succès',
            'user' => $this->formatUserResponse($user),
            'invitation_token' => $invitationToken,
            'invitation_url' => $invitationUrl,
            'expires_at' => $user->invitation_expires_at,
        ], 201);
    }

    /**
     * Verify invitation token
     */
    public function verifyInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Token requis'
            ], 422);
        }

        $user = User::where('invitation_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Lien d\'invitation invalide'
            ], 404);
        }

        if (!$user->hasValidInvitation()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce lien d\'invitation a expiré. Contactez l\'administration pour obtenir un nouveau lien.'
            ], 410);
        }

        if ($user->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte a déjà été activé. Connectez-vous avec vos identifiants.'
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation valide',
            'user' => [
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => User::ROLES[$user->role] ?? $user->role,
                'address' => $user->address,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Complete employee profile
     */
    public function completeProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(6)],
            'phone' => 'required|string|max:20',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('invitation_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Lien d\'invitation invalide'
            ], 404);
        }

        if (!$user->hasValidInvitation()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce lien d\'invitation a expiré'
            ], 410);
        }

        if ($user->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte a déjà été activé'
            ], 409);
        }

        // Mettre à jour le profil
        $user->update([
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'emergency_contact' => $request->emergency_contact,
            'emergency_phone' => $request->emergency_phone,
            'is_active' => true,
            'profile_completed' => true,
        ]);

        // Supprimer le token d'invitation
        $user->clearInvitationToken();

        // Créer un token d'authentification
        $token = $user->createToken('auth_token')->plainTextToken;

        dispatch(new ProcessLoginHistory($user, $request->ip(), $request->userAgent()));

        return response()->json([
            'success' => true,
            'message' => 'Profil complété avec succès. Bienvenue chez MBC !',
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ]);
    }

    /**
     * Resend invitation (admin only)
     */
    public function resendInvitation(Request $request, $userId)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur peut renvoyer les invitations'
            ], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        if ($user->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte a déjà été activé'
            ], 409);
        }

        if (!$user->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les comptes employés peuvent recevoir des invitations'
            ], 400);
        }

        // Générer un nouveau token
        $invitationToken = $user->generateInvitationToken();
        $invitationUrl = config('app.frontend_url', 'http://localhost:3000') . '/completer-profil?token=' . $invitationToken;

        // Generate new temp password for the resend
        $plainPassword = Str::random(10);
        $user->update(['password' => Hash::make($plainPassword)]);

        // Send Invitation Email
        try {
            Mail::to($user->email)->send(new StaffInvitation($user, $invitationUrl, $plainPassword));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send staff invitation: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation renvoyée avec succès',
            'invitation_token' => $invitationToken,
            'invitation_url' => $invitationUrl,
            'expires_at' => $user->invitation_expires_at,
        ]);
    }

    /**
     * Switch active role
     */
    public function switchRole(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $roleSlug = $request->role;

        // Check if user has this role
        if (!$user->hasRole($roleSlug)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas ce rôle'
            ], 403);
        }

        // Switch role
        if (!$user->switchRole($roleSlug)) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de changer de rôle'
            ], 500);
        }

        // Reload user with fresh data
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Rôle changé avec succès',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Add role to user (admin only)
     */
    public function addRoleToUser(Request $request, User $targetUser)
    {
        // Verify admin
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur peut ajouter des rôles'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|string',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $success = $targetUser->addRole(
            $request->role,
            $request->is_primary ?? false
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Rôle invalide ou déjà attribué'
            ], 400);
        }

        $targetUser->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Rôle ajouté avec succès',
            'user' => $this->formatUserResponse($targetUser),
        ]);
    }

    /**
     * Remove role from user (admin only)
     */
    public function removeRoleFromUser(Request $request, User $targetUser)
    {
        // Verify admin
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur peut retirer des rôles'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Don't allow removing the last role
        if (count($targetUser->getRoleSlugs()) <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de retirer le dernier rôle de l\'utilisateur'
            ], 400);
        }

        $success = $targetUser->removeRole($request->role);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de retirer ce rôle'
            ], 400);
        }

        $targetUser->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Rôle retiré avec succès',
            'user' => $this->formatUserResponse($targetUser),
        ]);
    }

    /**
     * Obtenir l'URL de redirection selon le rôle actif
     */
    private function getRedirectUrl(User $user): string
    {
        $activeRole = $user->getActiveRoleSlug();

        return match ($activeRole) {
            'admin' => '/admin/dashboard',
            'secretaire' => '/secretaire/dashboard',
            'formateur' => '/formateur/dashboard',
            'chef_chantier' => '/chef-chantier/dashboard',
            'apprenant' => '/apprenant/dashboard',
            'client' => '/client',
            default => '/dashboard',
        };
    }

    /**
     * Obtenir les permissions de l'utilisateur selon son rôle
     */
    private function getUserPermissions(User $user): array
    {
        $activeRole = $user->getActiveRoleSlug();

        $basePermissions = ['view_profile', 'edit_profile'];

        $rolePermissions = match ($activeRole) {
            'admin' => [
                'manage_users', 'manage_roles', 'manage_formations', 'manage_payments',
                'manage_settings', 'view_reports', 'manage_projects', 'manage_promo_codes',
                'validate_payments', 'generate_receipts', 'send_emails'
            ],
            'secretaire' => [
                'manage_apprenants', 'manage_payments', 'view_reports', 'generate_receipts',
                'manage_promo_codes', 'validate_payments', 'send_emails', 'manage_enrollments'
            ],
            'formateur' => [
                'view_formations', 'manage_attendances', 'manage_evaluations',
                'view_apprenants', 'generate_certificates'
            ],
            'chef_chantier' => [
                'manage_chantiers', 'create_reports', 'manage_teams', 'upload_photos',
                'track_progress', 'send_updates'
            ],
            'apprenant' => [
                'view_formations', 'view_payments', 'download_certificates',
                'view_evaluations', 'view_attendances'
            ],
            'client' => [
                'view_projects', 'view_invoices', 'request_quotes', 'track_progress',
                'send_messages', 'view_photos'
            ],
            default => [],
        };

        return array_merge($basePermissions, $rolePermissions);
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user): array
    {
        // Load roles relationship if not loaded
        if (!$user->relationLoaded('roles')) {
            $user->load('roles', 'activeRole');
        }

        $response = [
            'id' => $user->getPublicId(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getActiveRoleSlug(),
            'role_label' => User::ROLES[$user->getActiveRoleSlug()] ?? $user->getActiveRoleSlug(),
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'profile_completed' => $user->profile_completed,
            // Redirect URL based on active role
            'redirect_url' => $this->getRedirectUrl($user),
            // User permissions
            'permissions' => $this->getUserPermissions($user),
            // Multi-role fields
            'roles' => $user->roles->map(fn($role) => [
                'slug' => $role->slug,
                'name' => $role->name,
                'is_primary' => (bool) $role->pivot->is_primary,
                'is_staff' => $role->is_staff,
                'redirect_url' => $this->getRedirectUrl((clone $user)->forceFill(['active_role_id' => null, 'role' => $role->slug])),
            ])->toArray(),
            'active_role' => $user->activeRole ? [
                'slug' => $user->activeRole->slug,
                'name' => $user->activeRole->name,
            ] : [
                'slug' => $user->role,
                'name' => User::ROLES[$user->role] ?? $user->role,
            ],
        ];

        // Ajouter les champs spécifiques selon le rôle
        if ($user->isEmployee()) {
            $response['employee_id'] = $user->employee_id;
            $response['address'] = $user->address;
            $response['emergency_contact'] = $user->emergency_contact;
            $response['emergency_phone'] = $user->emergency_phone;
        }

        if ($user->isClient() || $user->hasRole('client')) {
            $response['company_name'] = $user->company_name;
            $response['company_address'] = $user->company_address;
            $response['project_type'] = $user->project_type;
            $response['project_description'] = $user->project_description;
        }

        if ($user->isApprenant() || $user->hasRole('apprenant')) {
            $response['formation'] = $user->formation;
            $response['formation_id'] = $user->formation_id;
            $response['enrollment_date'] = $user->enrollment_date?->format('Y-m-d');
        }

        if ($user->isFormateur() || $user->hasRole('formateur')) {
            $response['speciality'] = $user->speciality;
            $response['bio'] = $user->bio;
        }

        return $response;
    }

    private function getActiveTwoFactorChallenge(User $user): ?TwoFactorLoginChallenge
    {
        return TwoFactorLoginChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();
    }

    private function getTwoFactorSendState(User $user): array
    {
        $sendQuery = TwoFactorLoginChallengeSend::query()
            ->whereHas('challenge', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            });

        $recentSendQuery = (clone $sendQuery)->where('created_at', '>=', now()->subHour());
        $recentSendCount = (clone $recentSendQuery)->count();
        $latestSend = (clone $sendQuery)->latest('created_at')->first();
        $oldestRecentSend = (clone $recentSendQuery)->oldest('created_at')->first();

        $cooldownUntil = $latestSend?->created_at?->copy()->addSeconds(120);
        $hourWindowUntil = $recentSendCount >= 5 && $oldestRecentSend?->created_at
            ? $oldestRecentSend->created_at->copy()->addHour()
            : null;

        $availableAt = $cooldownUntil;
        if ($hourWindowUntil && (!$availableAt || $hourWindowUntil->greaterThan($availableAt))) {
            $availableAt = $hourWindowUntil;
        }

        $retryAfterSeconds = $availableAt ? max(0, now()->diffInSeconds($availableAt)) : 0;
        $remainingResends = max(0, 5 - $recentSendCount);

        return [
            'can_send' => $availableAt === null || $availableAt->lessThanOrEqualTo(now()),
            'retry_after_seconds' => $retryAfterSeconds,
            'message' => $availableAt && $availableAt->isFuture()
                ? ($recentSendCount >= 5
                    ? 'Vous avez atteint la limite de 5 envois par heure. Réessayez plus tard.'
                    : 'Veuillez patienter 120 secondes entre deux envois.')
                : 'Code disponible.',
            'remaining_resends' => $remainingResends,
            'send_count_last_hour' => $recentSendCount,
            'available_at' => $availableAt,
        ];
    }

    private function buildTwoFactorPayload(User $user, TwoFactorLoginChallenge $challenge): array
    {
        $sendState = $this->getTwoFactorSendState($user);
        $availableAt = $sendState['available_at'];

        return [
            'challenge_token' => $challenge->challenge_token,
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'resend_available_at' => $availableAt?->toIso8601String(),
            'retry_after_seconds' => $sendState['retry_after_seconds'],
            'remaining_resends' => $sendState['remaining_resends'],
            'send_count_last_hour' => $sendState['send_count_last_hour'],
            'cooldown_seconds' => $sendState['retry_after_seconds'],
        ];
    }

    private function createTwoFactorChallenge(User $user): ?TwoFactorLoginChallenge
    {
        $sendState = $this->getTwoFactorSendState($user);
        if (!$sendState['can_send']) {
            return null;
        }

        $code = $this->generateTwoFactorCode();
        $challenge = null;
        $sendRecord = null;

        try {
            DB::transaction(function () use ($user, $code, &$challenge, &$sendRecord): void {
                $challenge = TwoFactorLoginChallenge::create([
                    'user_id' => $user->id,
                    'challenge_token' => Str::random(64),
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(10),
                ]);

                $sendRecord = $challenge->sends()->create();
            });

            Log::info('Attempting to send initial 2FA code', [
                'user_id' => $user->id,
                'email' => $user->email,
                'challenge_id' => $challenge->id,
            ]);

            Mail::to($user->email)->send(new LoginTwoFactorCode(
                $user,
                $code,
                $challenge->expires_at->toIso8601String(),
                request()->ip(),
                request()->userAgent()
            ));

            Log::info('Initial 2FA code email sent successfully to relay', [
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $exception) {
            Log::error('CRITICAL: Failed to send initial 2FA code', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if ($sendRecord) {
                $sendRecord->delete();
            }

            if ($challenge) {
                $challenge->delete();
            }

            return null;
        }

        return $challenge?->load('sends');
    }

    private function generateTwoFactorCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
