<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Classe, EmploiTemplate, EmploiTemplateSlot};
use App\Services\TemplateGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class EmploiTemplateController extends Controller
{
    public function __construct(private TemplateGeneratorService $gen) {}

    public function show(EmploiTemplate $tpl)
    {
        return response()->json(['success' => true, 'data' => $this->serializeTemplate($tpl)]);
    }

    public function activeForClasse(Classe $classe)
    {
        $tpl = EmploiTemplate::query()
            ->where('classe_id', $classe->id)
            ->orderByRaw("CASE WHEN status='published' THEN 0 ELSE 1 END")
            ->orderByDesc('effective_from')
            ->first();

        if (!$tpl) {
            return response()->json(['message' => 'Aucun emploi trouvé pour cette classe.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->serializeTemplate($tpl)]);
    }

    public function generate(Request $r)
    {
        $data = $r->validate([
            'classe_id'      => 'required|exists:classe,id',   // <-- table 'classe'
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after:period_start',
            'effective_from' => 'required|date|after_or_equal:period_start',
        ]);

        $tpl = $this->gen->generate(
            $data['classe_id'], $data['period_start'], $data['period_end'], $data['effective_from']
        );

        return response()->json(['success' => true, 'data' => $this->serializeTemplate($tpl)]);
    }

    public function publish(EmploiTemplate $tpl)
    {
        $tpl->update(['status' => 'published']);
        return response()->json(['success' => true]);
    }

    public function lock(EmploiTemplate $tpl, EmploiTemplateSlot $slot)
    {
        abort_unless($slot->emploi_template_id === $tpl->id, 404);
        $slot->update(['status' => 'locked']);
        return response()->json(['success' => true]);
    }

    public function updateSlot(Request $r, EmploiTemplate $tpl, EmploiTemplateSlot $slot)
    {
        abort_unless($slot->emploi_template_id === $tpl->id, 404);

        $data = $r->validate([
            'jour_semaine' => 'sometimes|integer|min:1|max:6',
            'debut'        => 'sometimes|date_format:H:i',
            'fin'          => 'sometimes|date_format:H:i|after:debut',
            'matiere_id'   => 'sometimes|exists:matiere,id',
            'educateur_id' => 'sometimes|exists:educateurs,id',
            'salle_id'     => 'nullable|exists:salles,id',
            'status'       => 'sometimes|in:planned,locked,cancelled',
        ]);

        $slot->update($data);
        $fresh = EmploiTemplateSlot::find($slot->id);

        return response()->json(['success' => true, 'data' => $fresh]);
    }

    /** Sérialisation avec enrichissements (salle/matière/niveau) */
private function serializeTemplate(EmploiTemplate $tpl): array
{
    $rows = DB::table('emploi_template_slots as s')
        ->join('emploi_templates as t', 't.id', '=', 's.emploi_template_id')
        ->leftJoin('classe as c',    'c.id',  '=', 't.classe_id')
        ->leftJoin('salles as sa',   'sa.id', '=', 's.salle_id')
        ->leftJoin('matiere as m',   'm.id',  '=', 's.matiere_id')
        ->leftJoin('educateurs as e','e.id',  '=', 's.educateur_id')
        ->leftJoin('users as u',     'u.id',  '=', 'e.user_id')
        ->where('s.emploi_template_id', $tpl->id)
        ->orderBy('s.jour_semaine')->orderBy('s.debut')
        ->get([
            's.id','s.emploi_template_id','s.jour_semaine','s.debut','s.fin',
            's.matiere_id','s.educateur_id','s.salle_id','s.status',
            's.created_at','s.updated_at',
            'c.niveau as classe_niveau','c.nom as classe_nom',
            'sa.code as salle_code','sa.nom as salle_nom',
            'm.nom  as matiere_nom',
            'm.photo as matiere_photo',
            'u.name as educateur_nom',
        ]);

    $slots = $rows->map(function ($r) {
        // URL publique de la photo (absolue si APP_URL défini)
        $photoUrl = !empty($r->matiere_photo)
            ? URL::to(Storage::url($r->matiere_photo))
            : null;

        // fallback propre si pas de user.name
        $educNom = (isset($r->educateur_nom) && trim($r->educateur_nom) !== '')
            ? $r->educateur_nom
            : ('Éducateur #'.$r->educateur_id);

        return [
            'id'                 => (int) $r->id,
            'emploi_template_id' => (int) $r->emploi_template_id,
            'jour_semaine'       => (int) $r->jour_semaine,
            'debut'              => $r->debut,
            'fin'                => $r->fin,
            'matiere_id'         => (int) $r->matiere_id,
            'educateur_id'       => (int) $r->educateur_id,
            'salle_id'           => $r->salle_id ? (int) $r->salle_id : null,
            'status'             => $r->status,
            'created_at'         => $r->created_at,
            'updated_at'         => $r->updated_at,

            // champs friendly pour l’app
            'matiere_nom'   => $r->matiere_nom,
            'matiere_photo' => $photoUrl,
            'salle_code'    => $r->salle_code,
            'salle_nom'     => $r->salle_nom,
            'classe_niveau' => $r->classe_niveau,
            'classe_nom'    => $r->classe_nom,
            'educateur_nom' => $educNom,
        ];
    })->values()->all();

    return [
        'id'             => (int) $tpl->id,
        'classe_id'      => (int) $tpl->classe_id,
        'period_start'   => $tpl->period_start,
        'period_end'     => $tpl->period_end,
        'effective_from' => $tpl->effective_from,
        'status'         => $tpl->status,
        'version'        => (int) $tpl->version,
        'generated_by'   => $tpl->generated_by,
        'created_at'     => $tpl->created_at,
        'updated_at'     => $tpl->updated_at,
        'slots'          => $slots,
    ];
}

}
