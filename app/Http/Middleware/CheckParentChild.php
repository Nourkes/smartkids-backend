<?php
// app/Http/Middleware/CheckParentChild.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckParentChild
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Vérifier que l'utilisateur existe et est un parent
        if (!$user || !$user->isParent()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé'
            ], 403);
        }

        // Récupérer le parent associé à l'utilisateur
        $parent = $user->parent;
        
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent non trouvé'
            ], 404);
        }

        // Si la route contient un ID d'enfant, vérifier l'autorisation
        if ($request->route('enfant')) {
            $enfantId = $request->route('enfant');
            
            // CORRECTION : Spécifier explicitement la table pour éviter l'ambiguïté
            if (!$parent->enfants()->where('enfant.id', $enfantId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à accéder aux données de cet enfant'
                ], 403);
            }
        }

        return $next($request);
    }
}