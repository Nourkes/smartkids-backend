<?php
// app/Http/Controllers/Parent/ActiviteParticipationController.php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Services\ActiviteParticipationService;
use Illuminate\Http\Request;

class ActiviteParticipationController extends Controller
{
    public function __construct(private ActiviteParticipationService $svc) {}

    // POST /api/parent/activites/{activite}/participer
    public function participer(Request $request, Activite $activite)
    {
        $data = $request->validate([
            'enfant_id'        => 'required|exists:enfants,id',
            'remarques'        => 'nullable|string',
            'methode_paiement' => 'nullable|in:cash,carte,en_ligne',
        ]);

        // parent_id à partir de l’utilisateur courant (si dispo)
        $parentId = optional($request->user()->parent)->id;

        $out = $this->svc->inscrire(
            activite:       $activite,
            enfantId:       (int) $data['enfant_id'],
            parentId:       $parentId,
            remarques:      $data['remarques'] ?? null,
            methodePaiement:$data['methode_paiement'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Inscription enregistrée' . (
                ($activite->prix ?? 0) > 0 ? ' (paiement en attente)' : ''
            ),
            'data' => [
                'participation' => $out['participation'],
                'paiement'      => $out['paiement'],
            ],
        ], 201);
    }

    public function annuler(Request $request, Activite $activite, int $enfant)
    {
        $this->svc->desinscrire($activite, $enfant);

        return response()->json([
            'success' => true,
            'message' => 'Inscription annulée',
        ]);
    }
}
