<?php

namespace App\Http\Controllers\Educateur;

use App\Http\Controllers\Controller;
use App\Models\Educateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class EducateurEmploiController extends Controller
{
     public function day(Request $r)
    {
        $user = $r->user();
        $educ = Educateur::where('user_id', $user->id)->firstOrFail();

        $date = $r->query('date')
            ? Carbon::parse($r->query('date'))->toDateString()
            : Carbon::today()->toDateString();

        $dow = Carbon::parse($date)->dayOfWeekIso; // 1..7 (lundi..dimanche)

        // pas de créneau le dimanche
        if ($dow === 7) {
            return response()->json(['success' => true, 'data' => ['date' => $date, 'slots' => []]]);
        }

        $rows = DB::table('emploi_template_slots as s')
            ->join('emploi_templates as t', 't.id', '=', 's.emploi_template_id')
            ->leftJoin('salles as sa', 'sa.id', '=', 's.salle_id')
            ->leftJoin('matiere as m', 'm.id', '=', 's.matiere_id')
            ->where('t.status', 'published')
            ->where('s.educateur_id', $educ->id)
            ->where('s.jour_semaine', $dow)
            ->whereDate('t.period_start', '<=', $date)
            ->whereDate('t.period_end',   '>=', $date)
            ->whereDate('t.effective_from','<=',$date)
            ->orderBy('s.debut')
            ->get([
                's.id','s.emploi_template_id','s.jour_semaine','s.debut','s.fin',
                's.matiere_id','s.educateur_id','s.salle_id','s.status',
                'sa.code as salle_code','sa.nom as salle_nom',
                'm.nom as matiere_nom',
                'm.photo as matiere_photo', // <- chemin brut en DB
            ]);

        $slots = $rows->map(function ($r) {
            return [
                'id'                 => (int)$r->id,
                'emploi_template_id' => (int)$r->emploi_template_id,
                'jour_semaine'       => (int)$r->jour_semaine,
                'debut'              => $r->debut,
                'fin'                => $r->fin,
                'matiere_id'         => (int)$r->matiere_id,
                'educateur_id'       => (int)$r->educateur_id,
                'salle_id'           => $r->salle_id ? (int)$r->salle_id : null,
                'status'             => $r->status,
                'matiere_nom'        => $r->matiere_nom,
                'salle_code'         => $r->salle_code,
                'salle_nom'          => $r->salle_nom,

                'matiere_photo'      => $r->matiere_photo
                                        ? Storage::url($r->matiere_photo)
                                        : null,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data'    => [
                'date'  => $date,
                'slots' => $slots,
            ],
        ]);
    }
    public function myActiveTemplate(Request $request)
    {
        $user = $request->user();
        $educateur = Educateur::where('user_id', $user->id)->firstOrFail();

        $rows = DB::table('emploi_template_slots as s')
            ->join('emploi_templates as t', 't.id', '=', 's.emploi_template_id')
            ->leftJoin('classe as c', 'c.id', '=', 't.classe_id')      
            ->leftJoin('salles as sa', 'sa.id', '=', 's.salle_id')
            ->leftJoin('matiere as m', 'm.id', '=', 's.matiere_id')
            ->where('t.status', 'published')
            ->where('s.educateur_id', $educateur->id)
            ->orderBy('s.jour_semaine')->orderBy('s.debut')
            ->get([
                's.id','s.emploi_template_id','s.jour_semaine','s.debut','s.fin',
                's.matiere_id','s.educateur_id','s.salle_id','s.status',
                's.created_at','s.updated_at',
                't.classe_id','t.period_start','t.period_end',
                'c.niveau as classe_niveau','c.nom as classe_nom',
                'sa.code as salle_code','sa.nom as salle_nom',
                'm.nom as matiere_nom',
            ]);

        $today = now()->toDateString();
        $periodStart = $rows->min('period_start') ?? $today;
        $periodEnd   = $rows->max('period_end') ?? $today;

        $payload = [
            'id'             => 0,
            'classe_id'      => 0,                 
            'period_start'   => $periodStart,
            'period_end'     => $periodEnd,
            'effective_from' => $periodStart,
            'status'         => 'published',
            'version'        => 1,
            'generated_by'   => $user->id,
            'slots'          => $rows->map(function ($r) {
                return [
                    'id'                 => (int)$r->id,
                    'emploi_template_id' => (int)$r->emploi_template_id,
                    'jour_semaine'       => (int)$r->jour_semaine,
                    'debut'              => $r->debut,
                    'fin'                => $r->fin,
                    'matiere_id'         => (int)$r->matiere_id,
                    'educateur_id'       => (int)$r->educateur_id,
                    'salle_id'           => $r->salle_id ? (int)$r->salle_id : null,
                    'status'             => $r->status,
                    'created_at'         => $r->created_at,
                    'updated_at'         => $r->updated_at,

                    // ---- enrichissements pour Flutter ----
                    'classe_id'     => (int)$r->classe_id,
                    'classe_niveau' => $r->classe_niveau,
                    'classe_nom'    => $r->classe_nom,
                    'salle' => $r->salle_id
                        ? ['id' => (int)$r->salle_id, 'code' => $r->salle_code, 'nom' => $r->salle_nom]
                        : null,
                    'matiere' => $r->matiere_nom
                        ? ['id' => (int)$r->matiere_id, 'nom' => $r->matiere_nom]
                        : null,
                ];
            })->values(),
        ];

        return response()->json(['success' => true, 'data' => $payload]);
    }

    /** Sélectionne les IDs des templates publiés actifs (1 par classe) */
    private function activePublishedTemplateIds(?Carbon $asOf = null)
    {
        $ref = $asOf?->toDateString();

        $q = DB::table('emploi_templates as t')
            ->select('t.id')
            ->where('t.status', 'published');

        if ($ref) {
            $q->whereDate('t.effective_from', '<=', $ref);
        }

        return $q->whereRaw('t.effective_from = (
                select max(t2.effective_from)
                from emploi_templates t2
                where t2.classe_id = t.classe_id
                  and t2.status = "published"
                  ' . ($ref ? 'and t2.effective_from <= ?' : '') . '
            )', $ref ? [$ref] : [])
            ->pluck('id');
    }

    /** Emploi année pour un éducateur donné */
    public function year(int $educateurId)
    {
        $tplIds = $this->activePublishedTemplateIds();

        $rows = DB::table('emploi_template_slots as s')
            ->join('emploi_templates as t', 't.id', '=', 's.emploi_template_id')
            ->leftJoin('classe as c', 'c.id', '=', 't.classe_id')      // <-- 'classe'
            ->leftJoin('salles as sa', 'sa.id', '=', 's.salle_id')
            ->leftJoin('matiere as m', 'm.id', '=', 's.matiere_id')
            ->whereIn('s.emploi_template_id', $tplIds)
            ->where('s.educateur_id', $educateurId)
            ->orderBy('s.jour_semaine')->orderBy('s.debut')
            ->get([
                's.id','t.id as template_id','t.classe_id',
                't.period_start','t.period_end','t.effective_from',
                's.jour_semaine','s.debut','s.fin','s.matiere_id','s.salle_id','s.status',
                'c.niveau as classe_niveau','c.nom as classe_nom',
                'sa.code as salle_code','sa.nom as salle_nom',
                'm.nom as matiere_nom',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'educateur_id' => $educateurId,
                'slots'        => $rows,
            ],
        ]);
    }

    /** Emploi semaine pour un éducateur */
    public function week(Request $request, int $educateurId)
    {
        $weekStart = Carbon::parse($request->query('week_start', 'monday this week'))
            ->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $tplIds = $this->activePublishedTemplateIds($weekStart);

        $rows = DB::table('emploi_template_slots as s')
            ->join('emploi_templates as t', 't.id', '=', 's.emploi_template_id')
            ->whereIn('s.emploi_template_id', $tplIds)
            ->where('s.educateur_id', $educateurId)
            ->whereDate('t.period_start', '<=', $weekEnd)
            ->whereDate('t.period_end',   '>=', $weekStart)
            ->orderBy('s.jour_semaine')->orderBy('s.debut')
            ->get([
                't.classe_id',
                's.jour_semaine','s.debut','s.fin',
                's.matiere_id','s.salle_id','s.status',
            ]);

        $events = [];
        foreach ($rows as $r) {
            $date = $weekStart->copy()->addDays($r->jour_semaine - 1)->toDateString();
            $events[] = [
                'classe_id'  => $r->classe_id,
                'jour'       => $r->jour_semaine,
                'start_at'   => "{$date} {$r->debut}",
                'end_at'     => "{$date} {$r->fin}",
                'matiere_id' => $r->matiere_id,
                'salle_id'   => $r->salle_id,
                'status'     => $r->status,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'educateur_id' => $educateurId,
                'week_start'   => $weekStart->toDateString(),
                'week_end'     => $weekEnd->toDateString(),
                'events'       => $events,
            ],
        ]);
    }

    public function yearSelf(Request $r)  { return $this->year($this->educateurIdFromAuthOrFail()); }
    public function weekSelf(Request $r)  { return $this->week($r, $this->educateurIdFromAuthOrFail()); }

    private function educateurIdFromAuthOrFail(): int
    {
        $row = DB::table('educateurs')->where('user_id', auth()->id())->select('id')->first();
        abort_unless($row?->id, 403, 'Educateur non trouvé pour cet utilisateur.');
        return (int)$row->id;
    }
}
