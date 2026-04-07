<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * 
     * Vérifie si l'utilisateur a l'un des rôles spécifiés.
     * Usage: middleware('role:admin,secretaire')
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Les rôles autorisés (séparés par virgule)
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        // Vérifier le rôle actif ou le rôle principal
        $userRole = $user->getActiveRoleSlug() ?? $user->role;

        // L'admin a toujours accès
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Vérifier si l'utilisateur a l'un des rôles autorisés
        if (in_array($userRole, $roles)) {
            return $next($request);
        }

        // Vérifier dans les rôles multiples de l'utilisateur
        $userRoles = $user->roles()->pluck('slug')->toArray();
        $userRoles[] = $user->role; // Ajouter le rôle principal

        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Rôle requis: ' . implode(' ou ', $roles),
        ], 403);
    }
}
