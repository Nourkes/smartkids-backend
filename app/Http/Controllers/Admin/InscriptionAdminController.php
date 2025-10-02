<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideInscriptionRequest;
use App\Http\Resources\InscriptionResource;
use App\Http\Resources\PaiementResource;
use App\Models\Inscription;
use App\Services\InscriptionFlowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InscriptionAdminController extends Controller
{
    public function __construct(private InscriptionFlowService $flow) {}

    // GET /api/admin/inscriptions
    public function index(Request $request)
    {
        $q = Inscription::query();

        if ($s = $request->get('statut')) {
            $q->where('statut', $s);
        }
        if ($n = $request->get('niveau')) {
            $q->where('niveau_souhaite', $n);
        }
        if ($classeId = $request->get('classe_id')) {
            $q->where('classe_id', $classeId);
        }

        $inscriptions = $q->orderByDesc('created_at')->paginate(20);

        return InscriptionResource::collection($inscriptions);
    }

    // GET /api/admin/inscriptions/{inscription}
    public function show(Inscription $inscription)
    {
        return new InscriptionResource($inscription);
    }

    // POST /api/admin/inscriptions/{inscription}/decide
    public function decide(DecideInscriptionRequest $request, Inscription $inscription)
    {
        // Normalise l'action (accept / wait / reject)
        $action = $this->normalizeAction($request->input('action'));

        $data = $this->flow->decide(
            $inscription,
            match ($action) { // le service attend FR
                'accept' => 'accepter',
                'wait'   => 'mettre_en_attente',
                'reject' => 'refuser',
            },
            $request->input('classe_id'),
            $request->user()->id,
            $request->input('frais_inscription'),
            $request->input('frais_mensuel'),
            $request->input('remarques'),
            $request->input('methode_paiement', 'cash') // <— passe le moyen de paiement
        );

        $message = match ($action) {
            'accept' => 'Demande acceptée • paiement en attente (si applicable).',
            'reject' => 'Demande refusée.',
            default  => 'Demande mise en liste d’attente.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'inscription' => new InscriptionResource($data['inscription']),
                'paiement'    => $data['paiement'] ? new PaiementResource($data['paiement']) : null,
            ],
        ]);
    }

    // POST /api/admin/inscriptions/{inscription}/accept
    public function accept(Request $request, Inscription $inscription)
    {
        // petite validation directe (on peut aussi faire une FormRequest dédiée)
        $request->validate([
            'classe_id'         => ['nullable', 'integer', 'exists:classe,id'],
            'frais_inscription' => ['nullable', 'numeric', 'min:0'],
            'frais_mensuel'     => ['nullable', 'numeric', 'min:0'],
            'methode_paiement'  => ['nullable', Rule::in(['cash','carte','en_ligne'])],
            'remarques'         => ['nullable', 'string'],
        ]);

       $data = $this->flow->accept(
    $inscription,
    $request->input('classe_id'),
    $request->user()->id,
    $request->input('frais_inscription'),
    $request->input('frais_mensuel'),
    $request->input('remarques'),
    $request->input('methode_paiement', 'cash')  // ✅ passe la méthode
);

        return response()->json([
            'success' => true,
            'message' => 'Demande acceptée • paiement en attente (si applicable).',
            'data' => [
                'inscription' => new InscriptionResource($data['inscription']),
                'paiement'    => $data['paiement'] ? new PaiementResource($data['paiement']) : null,
            ],
        ]);
    }

    // POST /api/admin/inscriptions/{inscription}/wait
    public function wait(Request $request, Inscription $inscription)
    {
        $request->validate([
            'remarques' => ['nullable','string'],
        ]);

        $data = $this->flow->wait($inscription, $request->user()->id, $request->input('remarques'));

        return response()->json([
            'success' => true,
            'message' => 'Demande mise en liste d’attente.',
            'data'    => ['inscription' => new InscriptionResource($data['inscription'])],
        ]);
    }

    // POST /api/admin/inscriptions/{inscription}/reject
    public function reject(Request $request, Inscription $inscription)
    {
        $request->validate([
            'remarques' => ['nullable','string'],
        ]);

        $data = $this->flow->reject($inscription, $request->user()->id, $request->input('remarques'));

        return response()->json([
            'success' => true,
            'message' => 'Demande refusée.',
            'data'    => ['inscription' => new InscriptionResource($data['inscription'])],
        ]);
    }

    // POST /api/admin/inscriptions/{inscription}/assign-class
    public function assignClass(Request $request, Inscription $inscription)
    {
        $data = $request->validate([
            'classe_id' => ['required','integer','exists:classe,id'], // table = "classe"
        ]);

        $inscription->update(['classe_id' => $data['classe_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Classe affectée.',
            'data'    => new InscriptionResource($inscription->fresh()),
        ]);
    }

    private function normalizeAction(?string $action): string
    {
        return match (strtolower((string) $action)) {
            'accepter', 'accept', 'accepted'                                 => 'accept',
            'wait', 'waiting', 'attente', 'mettre_en_attente', 'liste_attente' => 'wait',
            'reject', 'refuser', 'rejected'                                  => 'reject',
            default                                                          => 'wait',
        };
    }
}
