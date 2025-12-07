<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveEnrollment
{
    /**
     * Vérifier que le parent a au moins un enfant avec une inscription active
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Ne s'applique qu'aux parents
        if ($user->role !== 'parent') {
            return $next($request);
        }

        $parent = $user->parent;

        if (!$parent) {
            return response()->json([
                'error' => 'Parent non trouvé'
            ], 403);
        }

        // Vérifier qu'au moins un enfant a une inscription active
        $hasActiveChild = $parent->enfants()
            ->active() // Utilise le scope défini dans Enfant.php
            ->exists();

        if (!$hasActiveChild) {
            return response()->json([
                'error' => 'Aucune inscription active pour l\'année en cours',
                'message' => 'Veuillez renouveler votre inscription',
                'code' => 'NO_ACTIVE_ENROLLMENT',
                'current_year' => config('smartkids.current_academic_year'),
            ], 403);
        }

        return $next($request);
    }
}
