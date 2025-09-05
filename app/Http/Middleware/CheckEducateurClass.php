<?php
// app/Http/Middleware/CheckEducateurClass.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEducateurClass
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Vérifier que l'utilisateur est un éducateur
        if (!$user || $user->role !== 'educateur') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé - Réservé aux éducateurs'
            ], 403);
        }

        // Récupérer l'éducateur associé
        $educateur = $user->educateur;
        
        if (!$educateur) {
            return response()->json([
                'success' => false,
                'message' => 'Éducateur non trouvé'
            ], 404);
        }

        // Si la route contient un ID de classe, vérifier l'autorisation
        if ($request->route('classe')) {
            $classeId = $request->route('classe');
            
            // Vérifier que l'éducateur a accès à cette classe
            if (!$educateur->classes()->where('id', $classeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à accéder aux données de cette classe'
                ], 403);
            }
        }

        return $next($request);
    }
}