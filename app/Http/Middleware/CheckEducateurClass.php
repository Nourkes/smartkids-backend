<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEducateurClass
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'educateur') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé - Réservé aux éducateurs'
            ], 403);
        }

        $educateur = $user->educateur;
        
        if (!$educateur) {
            return response()->json([
                'success' => false,
                'message' => 'Profil éducateur non trouvé'
            ], 404);
        }

        // Vérification pour les routes avec classeId
        $classeId = $request->route('classeId') ?? $request->route('classe');
        
        if ($classeId) {
            if (!$educateur->classes()->where('classe.id', $classeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à accéder aux données de cette classe'
                ], 403);
            }
        }

        // Ajouter l'éducateur à la requête pour éviter les requêtes répétées
        $request->merge(['educateur' => $educateur]);

        return $next($request);
    }
}
