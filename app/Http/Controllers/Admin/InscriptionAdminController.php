<?php
// app/Http/Controllers/Admin/InscriptionAdminController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Services\InscriptionFlowService;
use Illuminate\Http\Request;

class InscriptionAdminController extends Controller
{
    public function __construct(private InscriptionFlowService $flow) {}

    /**
     * Liste des inscriptions
     * GET /api/admin/inscriptions
     */
    public function index(Request $request)
    {
        $query = Inscription::with(['classe', 'form']);

        // Filtres
        if ($s = $request->input('statut')) {
            $query->where('statut', $s);
        }
        if ($n = $request->input('niveau')) {
            $query->where('niveau_souhaite', $n);
        }
        if ($a = $request->input('annee')) {
            $query->where('annee_scolaire', $a);
        }

        $inscriptions = $query->orderByDesc('date_inscription')
                              ->paginate($request->input('per_page', 15));

        return response()->json($inscriptions);
    }

    /**
     * Détail d'une inscription
     * GET /api/admin/inscriptions/{inscription}
     */
    public function show(Inscription $inscription)
    {
        $inscription->load(['classe', 'form', 'parent', 'paiements']);
        return response()->json($inscription);
    }

    /**
     * Accepter une inscription + créer paiement 1er mois avec token
     * POST /api/admin/inscriptions/{inscription}/accept
     * 
     * 🔥 Le montant sera calculé au moment du paiement (pas besoin de le fournir)
     * 
     * Body: {
     *   "classe_id": 1,
     *   "remarques": "..."
     * }
     */
    public function accept(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'classe_id' => 'required|exists:classe,id',
            'remarques' => 'nullable|string',
        ]);

        $result = $this->flow->accept(
            $inscription,
            $validated['classe_id'],
            auth()->id(),
            $validated['remarques'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Mettre en liste d'attente
     * POST /api/admin/inscriptions/{inscription}/wait
     */
    public function wait(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'remarques' => 'nullable|string',
        ]);

        $result = $this->flow->wait(
            $inscription,
            auth()->id(),
            $validated['remarques'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Refuser une inscription
     * POST /api/admin/inscriptions/{inscription}/reject
     */
    public function reject(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'remarques' => 'nullable|string',
        ]);

        $result = $this->flow->reject(
            $inscription,
            auth()->id(),
            $validated['remarques'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Assigner une classe (si déjà acceptée)
     * POST /api/admin/inscriptions/{inscription}/assign-class
     */
    public function assignClass(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'classe_id' => 'required|exists:classe,id',
        ]);

        $inscription->update(['classe_id' => $validated['classe_id']]);

        return response()->json(['inscription' => $inscription->fresh()]);
    }
}