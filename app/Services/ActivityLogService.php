<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Log a user activity
     *
     * @param string $action
     * @param string|null $description
     * @param mixed|null $subject
     * @param int|null $userId
     * @return ActivityLog
     */
    public static function log(
        string $action,
        ?string $description = null,
        $subject = null,
        ?int $userId = null,
        bool $fallbackToAuthenticatedActor = true
    ): ActivityLog {
        $actorId = $userId;

        if ($actorId === null && $fallbackToAuthenticatedActor) {
            $actorId = Auth::id();
        }

        if ($actorId === null && $subject instanceof User) {
            // Fallback for automated contexts (seed/tests) where no authenticated actor exists.
            $actorId = $subject->id;
        }

        return ActivityLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log user creation
     */
    public static function logUserCreated($user, $createdBy = null): ActivityLog
    {
        return self::log(
            'Nouvel utilisateur créé',
            "L'utilisateur {$user->name} ({$user->email}) a été ajouté au système",
            $user,
            $createdBy ? $createdBy->id : null
        );
    }

    /**
     * Log user update
     */
    public static function logUserUpdated($user, $updatedBy = null): ActivityLog
    {
        return self::log(
            'Utilisateur mis à jour',
            "Les informations de l'utilisateur {$user->name} ont été modifiées",
            $user,
            $updatedBy ? $updatedBy->id : null
        );
    }

    /**
     * Log user deletion
     */
    public static function logUserDeleted($userName, $userEmail, $deletedBy = null): ActivityLog
    {
        return self::log(
            'Utilisateur supprimé',
            "L'utilisateur {$userName} ({$userEmail}) a été supprimé du système",
            null,
            $deletedBy ? $deletedBy->id : null
        );
    }

    /**
     * Log payment completed
     */
    public static function logPaymentCompleted($payment): ActivityLog
    {
        return self::log(
            'Paiement complété',
            "Paiement de {$payment->amount} FCFA complété - Référence: {$payment->reference}",
            $payment,
            $payment->user_id
        );
    }

    /**
     * Log payment pending
     */
    public static function logPaymentPending($payment): ActivityLog
    {
        return self::log(
            'Paiement en attente',
            "Paiement de {$payment->amount} FCFA en attente de validation - Référence: {$payment->reference}",
            $payment,
            $payment->user_id
        );
    }

    /**
     * Log payment failed
     */
    public static function logPaymentFailed($payment, $reason = null): ActivityLog
    {
        $description = "Échec du paiement de {$payment->amount} FCFA - Référence: {$payment->reference}";
        if ($reason) {
            $description .= " - Raison: {$reason}";
        }

        return self::log(
            'Paiement échoué',
            $description,
            $payment,
            $payment->user_id
        );
    }

    /**
     * Log payment deletion
     */
    public static function logPaymentDeleted($payment, $deletedBy = null): ActivityLog
    {
        return self::log(
            'Paiement supprimé',
            "Le paiement {$payment->reference} d'un montant de {$payment->amount} {$payment->currency} a été supprimé.",
            null,
            $deletedBy ? $deletedBy->id : null
        );
    }

    /**
     * Log formation created
     */
    public static function logFormationCreated($formation, $createdBy = null): ActivityLog
    {
        return self::log(
            'Formation créée',
            "Nouvelle formation '{$formation->title}' ajoutée au catalogue",
            $formation,
            $createdBy ? $createdBy->id : null
        );
    }

    /**
     * Log formation updated
     */
    public static function logFormationUpdated($formation, $updatedBy = null): ActivityLog
    {
        return self::log(
            'Formation mise à jour',
            "La formation '{$formation->title}' a été modifiée",
            $formation,
            $updatedBy ? $updatedBy->id : null
        );
    }

    /**
     * Log enrollment created
     */
    public static function logEnrollmentCreated($enrollment): ActivityLog
    {
        $formationTitle = $enrollment->formation ? $enrollment->formation->title : 'Formation inconnue';
        return self::log(
            'Nouvelle inscription',
            "Inscription de {$enrollment->full_name} à la formation '{$formationTitle}'",
            $enrollment,
            $enrollment->user_id
        );
    }

    /**
     * Log project created
     */
    public static function logProjectCreated($project, $createdBy = null): ActivityLog
    {
        return self::log(
            'Projet créé',
            "Nouveau projet '{$project->title}' ajouté au portfolio",
            $project,
            $createdBy ? $createdBy->id : null
        );
    }

    /**
     * Log project updated
     */
    public static function logProjectUpdated($project, $updatedBy = null): ActivityLog
    {
        return self::log(
            'Projet mis à jour',
            "Le projet '{$project->title}' a été modifié",
            $project,
            $updatedBy ? $updatedBy->id : null
        );
    }

    /**
     * Log project status change
     */
    public static function logProjectStatusChange($project, $oldStatus, $newStatus, $updatedBy = null): ActivityLog
    {
        return self::log(
            'Statut du projet modifié',
            "Le statut du projet '{$project->title}' est passé de '{$oldStatus}' à '{$newStatus}'",
            $project,
            $updatedBy ? $updatedBy->id : null
        );
    }

    /**
     * Log system backup
     */
    public static function logSystemBackup(bool $success = true): ActivityLog
    {
        return self::log(
            $success ? 'Sauvegarde automatique' : 'Échec de sauvegarde',
            $success 
                ? 'Sauvegarde automatique de la base de données effectuée avec succès'
                : 'Échec de la sauvegarde automatique de la base de données',
            null,
            null
        );
    }

    /**
     * Log settings update
     */
    public static function logSettingsUpdate(string $settingName, $updatedBy = null): ActivityLog
    {
        return self::log(
            'Configuration mise à jour',
            "Paramètre '{$settingName}' modifié",
            null,
            $updatedBy ? $updatedBy->id : null
        );
    }

    /**
     * Log login attempt
     */
    public static function logLoginAttempt(string $email, bool $success = true): ActivityLog
    {
        return self::log(
            $success ? 'Connexion réussie' : 'Tentative de connexion échouée',
            $success 
                ? "Connexion réussie pour l'utilisateur {$email}"
                : "Tentative de connexion échouée pour l'utilisateur {$email}",
            null,
            Auth::id()
        );
    }

    /**
     * Log logout
     */
    public static function logLogout($user): ActivityLog
    {
        return self::log(
            'Déconnexion',
            "L'utilisateur {$user->name} s'est déconnecté",
            null,
            $user->id
        );
    }
}
