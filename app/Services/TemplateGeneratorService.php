<?php
namespace App\Services;

use App\Models\{EmploiTemplate, EmploiTemplateSlot, Classe, Salle, Matiere};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TemplateGeneratorService
{
    public function generate(int $classeId, string $periodStart, string $periodEnd, string $effectiveFrom): EmploiTemplate
    {
        $grid         = $this->buildGrid();
        $tasks        = $this->loadTasksFromClasseMatiere($classeId);
        $educators    = $this->loadEducatorsForClasse($classeId);
        $availability = $this->loadAvailability($educators);
        [$busyEdu, $busySalle] = $this->loadBusyMapsPublished();

        $classe = Classe::with('salle')->findOrFail($classeId);

        // --- salles compatibles & "home room" ---
        $candidateSalles = $this->candidateSallesForClasse($classe);
        $homeSalleId     = $classe->salle_id ?: ($candidateSalles[ array_rand($candidateSalles) ] ?? null);

        // compteur d’usage des salles pour CETTE classe
        $classSalleUse = []; // [salle_id] => count

        // Quotas/charges éducateurs (en minutes)
        $blockMin   = (int) Config::get('emploi.block_min', 60);
        $capMinutes = (int) Config::get('emploi.educateur_max_h_week', 20) * 60;
        $capMin     = array_fill_keys($educators, $capMinutes);
        $loadMin    = $this->loadPublishedWeeklyMinutes($educators);

        // État “classe”
        $classBusy = [];                 // $classBusy[jour]["HH:MM-HH:MM"] = true
        $dayLoad   = array_fill(1, 6, 0);

        return DB::transaction(function () use (
            $classeId,$periodStart,$periodEnd,$effectiveFrom,$grid,$tasks,$educators,
            $availability,&$busyEdu,&$busySalle,$blockMin,$capMin,&$loadMin,
            &$classBusy,&$dayLoad,$candidateSalles,$homeSalleId,&$classSalleUse
        ) {
            $tpl = EmploiTemplate::create([
                'classe_id'      => $classeId,
                'period_start'   => $periodStart,
                'period_end'     => $periodEnd,
                'effective_from' => $effectiveFrom,
                'status'         => 'draft',
                'version'        => (int)((EmploiTemplate::where('classe_id',$classeId)->max('version') ?? 0) + 1),
                'generated_by'   => auth()->id(),
            ]);

            foreach ($tasks as $task) {
                $matiereId = (int) $task['matiere_id'];
                $blocks    = (int) $task['blocks'];

                // éducateur principal tant que le quota le permet
                $primary   = $this->choosePrimaryEducateurForMatiere(
                    $matiereId, $blocks, $educators, $availability, $busyEdu, $grid, $loadMin, $capMin, $blockMin
                );
                $educOrder = $primary ? array_values(array_unique([$primary, ...$educators])) : $educators;

                // grappes consécutives préférées
                $clusters = $this->makeClusters($blocks);

                foreach ($clusters as $packSize) {
                    // 1) tenter un pack de K blocs consécutifs
                    if ($this->tryPlaceConsecutivePack(
                        $packSize, $grid, $educOrder, $availability, $busyEdu, $busySalle,
                        $loadMin, $capMin, $blockMin,
                        $tpl->id, $matiereId, $classBusy, $dayLoad,
                        $candidateSalles, $homeSalleId, $classSalleUse
                    )) {
                        continue; // pack placé
                    }

                    // 2) fallback: blocs unitaires
                    for ($i=0; $i<$packSize; $i++) {
                        $slot = $this->findSingleSlot(
                            $grid, $educOrder, $availability, $busyEdu, $busySalle,
                            $loadMin, $capMin, $blockMin, $classBusy, $dayLoad,
                            $candidateSalles, $homeSalleId, $classSalleUse
                        );
                        if (!$slot) break;

                        [$jour,$debut,$fin,$educateurId,$salleId] = $slot;

                        EmploiTemplateSlot::create([
                            'emploi_template_id'=>$tpl->id,
                            'jour_semaine'=>$jour,'debut'=>$debut,'fin'=>$fin,
                            'matiere_id'=>$matiereId,'educateur_id'=>$educateurId,
                            'salle_id'=>$salleId,'status'=>'planned',
                        ]);

                        $key = "$debut-$fin";
                        $busyEdu[$educateurId][$jour][$key] = true;
                        if ($salleId) {
                            $busySalle[$salleId][$jour][$key] = true;
                            $classSalleUse[$salleId] = ($classSalleUse[$salleId] ?? 0) + 1;
                        }
                        $classBusy[$jour][$key] = true;

                        $dayLoad[$jour] = ($dayLoad[$jour] ?? 0) + 1;
                        $loadMin[$educateurId] = ($loadMin[$educateurId] ?? 0) + $blockMin;
                    }
                }
            }

            return $tpl->load('slots');
        });
    }

    /* ======================== GRID & CHARGES ======================== */

    private function buildGrid(): array
    {
        $days   = Config::get('emploi.days', [1,2,3,4,5,6]);
        $start  = Carbon::createFromTimeString(Config::get('emploi.day_start','08:30'));
        $end    = Carbon::createFromTimeString(Config::get('emploi.day_end','16:30'));
        $block  = (int) Config::get('emploi.block_min', 60);
        $breaks = Config::get('emploi.breaks', [['start'=>'12:00','end'=>'13:00']]);

        $grid = [];
        foreach ($days as $d) {
            $slots = []; $t = $start->copy();
            while ($t->lt($end)) {
                $slotStart = $t->copy();
                $slotEnd   = $t->copy()->addMinutes($block);
                if ($slotEnd->gt($end)) break;

                $inBreak = false;
                foreach ($breaks as $b) {
                    $bs = Carbon::createFromTimeString($b['start']);
                    $be = Carbon::createFromTimeString($b['end']);
                    if ($slotStart->lt($be) && $slotEnd->gt($bs)) { $inBreak = true; break; }
                }
                if (!$inBreak) $slots[] = ['debut'=>$slotStart->format('H:i'),'fin'=>$slotEnd->format('H:i')];
                $t->addMinutes($block);
            }
            $grid[$d] = $slots;
        }
        return $grid;
    }

    private function loadTasksFromClasseMatiere(int $classeId): array
    {
        $block = (int) Config::get('emploi.block_min', 60);
        $rows = DB::table('classe_matiere')->where('classe_id',$classeId)
            ->select('matiere_id','heures_par_semaine')->get();

        $tasks = [];
        foreach ($rows as $r) {
            $blocks = (int) ceil(($r->heures_par_semaine * 60) / $block);
            if ($blocks > 0) $tasks[] = ['matiere_id'=>(int)$r->matiere_id,'blocks'=>$blocks];
        }
        usort($tasks, fn($a,$b)=>$b['blocks']<=>$a['blocks']);
        return $tasks;
    }

    private function loadEducatorsForClasse(int $classeId): array
    {
        return DB::table('educateur_classe')->where('classe_id',$classeId)
            ->pluck('educateur_id')->map(fn($v)=>(int)$v)->all();
    }

    private function loadAvailability(array $educatorIds): array
    {
        if (!$educatorIds) return [];
        $rows = DB::table('educator_availabilities')
            ->whereIn('educateur_id',$educatorIds)
            ->orderBy('jour_semaine')->orderBy('debut')->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->educateur_id][$r->jour_semaine][] = ['debut'=>$r->debut,'fin'=>$r->fin];
        }
        return $map;
    }

    private function loadBusyMapsPublished(): array
    {
        $rows = DB::table('emploi_template_slots as s')
            ->join('emploi_templates as t','t.id','=','s.emploi_template_id')
            ->where('t.status','published')
            ->get(['s.educateur_id','s.salle_id','s.jour_semaine','s.debut','s.fin']);

        $edu=[]; $salle=[];
        foreach ($rows as $r) {
            $edu[$r->educateur_id][$r->jour_semaine]["$r->debut-$r->fin"]=true;
            if ($r->salle_id) $salle[$r->salle_id][$r->jour_semaine]["$r->debut-$r->fin"]=true;
        }
        return [$edu,$salle];
    }

    private function loadPublishedWeeklyMinutes(array $educatorIds): array
    {
        if (!$educatorIds) return [];
        $rows = DB::table('emploi_template_slots as s')
            ->join('emploi_templates as t','t.id','=','s.emploi_template_id')
            ->where('t.status','published')
            ->whereIn('s.educateur_id',$educatorIds)
            ->get(['s.educateur_id','s.debut','s.fin']);

        $min = [];
        foreach ($rows as $r) {
            $delta = (strtotime($r->fin) - strtotime($r->debut)) / 60;
            $min[(int)$r->educateur_id] = ($min[(int)$r->educateur_id] ?? 0) + (int)$delta;
        }
        return $min;
    }

    /** Prof principal = le moins chargé qui peut absorber tous les blocs sans dépasser le quota */
    private function choosePrimaryEducateurForMatiere(
        int $matiereId, int $blocks,
        array $educators, array $availability, array $busyEdu, array $grid,
        array $loadMin, array $capMin, int $blockMin
    ): ?int {
        $ordered = $educators;
        usort($ordered, fn($a,$b)=>($loadMin[$a] ?? 0) <=> ($loadMin[$b] ?? 0));
        $need = $blocks * $blockMin;
        foreach ($ordered as $e) {
            if ( ($loadMin[$e] ?? 0) + $need <= ($capMin[$e] ?? PHP_INT_MAX) ) {
                return $e;
            }
        }
        return $ordered[0] ?? null;
    }

    /* ===================== Placement (pack + unitaire) ===================== */

    private function makeClusters(int $blocks): array {
        $pref = (int) Config::get('emploi.prefer_consecutive_blocks', 2);
        $max  = (int) Config::get('emploi.max_consecutive_blocks', 3);
        $pref = max(1, min($pref, $max));
        $out = [];
        while ($blocks > 0) {
            $take = min($pref, $blocks);
            $out[] = $take;
            $blocks -= $take;
        }
        return $out;
    }

    private function tryPlaceConsecutivePack(
        int $k,
        array $grid,
        array $educOrder,
        array $availability,
        array &$busyEdu,
        array &$busySalle,
        array &$loadMin,
        array $capMin,
        int $blockMin,
        int $tplId,
        int $matiereId,
        array &$classBusy,
        array &$dayLoad,
        array $candidateSalles,
        ?int $homeSalleId,
        array &$classSalleUse
    ): bool {
        $jours = array_keys($grid);
        usort($jours, fn($a,$b)=>($dayLoad[$a] ?? 0) <=> ($dayLoad[$b] ?? 0));

        foreach ($jours as $jour) {
            $slots = $grid[$jour];
            $n = count($slots);
            if ($n < $k) continue;

            for ($i=0; $i <= $n - $k; $i++) {
                // classe libre sur toute la fenêtre
                $keys = [];
                $okClass = true;
                for ($t=0; $t<$k; $t++) {
                    $d = $slots[$i+$t]['debut']; $f = $slots[$i+$t]['fin'];
                    $key = "$d-$f";
                    $keys[] = $key;
                    if (!empty($classBusy[$jour][$key])) { $okClass = false; break; }
                }
                if (!$okClass) continue;

                // choisir une salle pour tout le pack
$salleId = $this->chooseSalleForPack($jour, $keys, $homeSalleId, $candidateSalles, $busySalle, $classSalleUse);
if ($salleId === null) continue; 

                foreach ($educOrder as $e) {
                    if ( (($loadMin[$e] ?? 0) + $k*$blockMin) > ($capMin[$e] ?? PHP_INT_MAX) ) continue;

                    // vérifier disponibilités/collisions
                    $okAll = true;
                    for ($t=0; $t<$k; $t++) {
                        $d = $slots[$i+$t]['debut']; $f = $slots[$i+$t]['fin']; $key = $keys[$t];
                        if (!$this->isWithinAvailability($availability,$e,$jour,$d,$f)) { $okAll = false; break; }
                        if (!empty($busyEdu[$e][$jour][$key])) { $okAll = false; break; }
                        if (!empty($busySalle[$salleId][$jour][$key])) { $okAll = false; break; }
                    }
                    if (!$okAll) continue;

                    // placer le pack
                    for ($t=0; $t<$k; $t++) {
                        $d = $slots[$i+$t]['debut']; $f = $slots[$i+$t]['fin']; $key = $keys[$t];
                        EmploiTemplateSlot::create([
                            'emploi_template_id'=>$tplId,
                            'jour_semaine'=>$jour,'debut'=>$d,'fin'=>$f,
                            'matiere_id'=>$matiereId,'educateur_id'=>$e,
                            'salle_id'=>$salleId,'status'=>'planned',
                        ]);
                        $busyEdu[$e][$jour][$key] = true;
                        $busySalle[$salleId][$jour][$key] = true;
                        $classBusy[$jour][$key]  = true;
                    }
                    $classSalleUse[$salleId] = ($classSalleUse[$salleId] ?? 0) + $k;
                    $dayLoad[$jour] = ($dayLoad[$jour] ?? 0) + $k;
                    $loadMin[$e]    = ($loadMin[$e] ?? 0) + $k*$blockMin;
                    return true;
                }
            }
        }
        return false;
    }

    private function findSingleSlot(
        array $grid,
        array $educOrder,
        array $availability,
        array &$busyEdu,
        array &$busySalle,
        array &$loadMin,
        array $capMin,
        int $blockMin,
        array &$classBusy,
        array &$dayLoad,
        array $candidateSalles,
        ?int $homeSalleId,
        array &$classSalleUse
    ): ?array {
        $jours = array_keys($grid);
        usort($jours, fn($a,$b)=>($dayLoad[$a] ?? 0) <=> ($dayLoad[$b] ?? 0));

        foreach ($jours as $jour) {
            foreach ($grid[$jour] as $s) {
                $d = $s['debut']; $f = $s['fin']; $key = "$d-$f";
                if (!empty($classBusy[$jour][$key])) continue;

                // salle pour ce créneau
$salleId = $this->chooseSalleForSlot(0, $jour, $d, $f, $homeSalleId, $candidateSalles, $busySalle, $classSalleUse);
if ($salleId === null) continue;

                foreach ($educOrder as $e) {
                    if ( (($loadMin[$e] ?? 0) + $blockMin) > ($capMin[$e] ?? PHP_INT_MAX) ) continue;
                    if (!$this->isWithinAvailability($availability,$e,$jour,$d,$f)) continue;
                    if (!empty($busyEdu[$e][$jour][$key])) continue;
                    if (!empty($busySalle[$salleId][$jour][$key])) continue;

                    return [$jour,$d,$f,$e,$salleId];
                }
            }
        }
        return null;
    }

    /* ============================ Utils ============================ */

    private function isWithinAvailability(array $availability, int $e, int $j, string $d, string $f): bool
    {
        if (!isset($availability[$e][$j])) return true;
        foreach ($availability[$e][$j] as $w) {
            if ($w['debut'] <= $d && $w['fin'] >= $f) return true;
        }
        return false;
    }

    private function resolveSalleForMatiere(int $matiereId, ?int $fallbackSalleId): ?int
    {
        $map = Config::get('emploi.matiere_default_salle', []);
        if (!$map) return $fallbackSalleId;

        $matiere = Matiere::find($matiereId);
        if (!$matiere) return $fallbackSalleId;

        foreach ($map as $slugOrName => $code) {
            if (str_contains(strtolower($matiere->nom), strtolower($slugOrName))) {
                $salle = Salle::where('code',$code)->first();
                return $salle?->id ?? $fallbackSalleId;
            }
        }
        return $fallbackSalleId;
    }

    /** Salles compatibles pour une classe (capacité >= effectif/capacité classe) */
   /** Salles compatibles pour une classe (capacité >= effectif/capacité classe) */
private function candidateSallesForClasse(\App\Models\Classe $classe): array
{
    // On récupère la bonne colonne
    $need = $classe->capacite ?? $classe->effectif ?? $classe->capacite_max ?? null;

    $q = DB::table('salles');
    if ($need) {
        $q->where('capacite', '>=', $need);
    }

    $ids = $q->orderBy('capacite')->pluck('id')->map(fn($v)=>(int)$v)->all();

    if (empty($ids)) {
        $ids = DB::table('salles')->orderBy('capacite')->pluck('id')->map(fn($v)=>(int)$v)->all();
    }

    return $ids;
}

/** Choisit la salle pour un créneau unique */
private function chooseSalleForSlot(
    int $classeId, int $jour, string $debut, string $fin,
    ?int $homeSalleId, array $candidates,
    array &$busySalle, array &$useCount
): ?int {
    $key = "$debut-$fin";

    // 1) Home room si libre
    if ($homeSalleId && in_array($homeSalleId, $candidates, true)) {
        if (empty($busySalle[$homeSalleId][$jour][$key])) {
            return $homeSalleId;
        }
    }

    // 2) candidates libres, triées par usage croissant (minimise les changements)
    $pool = array_values(array_filter($candidates, fn($sid) => empty($busySalle[$sid][$jour][$key])));
    if (!empty($pool)) {
        usort($pool, function ($a, $b) use ($useCount) {
            $ua = $useCount[$a] ?? 0; $ub = $useCount[$b] ?? 0;
            if ($ua === $ub) return rand(-1, 1);
            return $ua <=> $ub;
        });
        return $pool[0];
    }

    // 3) Si TOUT est occupé à ce créneau → pas de salle possible (on évite les collisions)
    return null;
}

/** Choisit une salle pour un pack consécutif */
private function chooseSalleForPack(
    int $jour, array $keys, ?int $homeSalleId, array $candidates,
    array &$busySalle, array &$useCount
): ?int {
    // 1) Home si libre sur TOUTE la fenêtre
    if ($homeSalleId && in_array($homeSalleId, $candidates, true)) {
        $ok = true;
        foreach ($keys as $k) if (!empty($busySalle[$homeSalleId][$jour][$k])) { $ok = false; break; }
        if ($ok) return $homeSalleId;
    }

    // 2) Candidate libre sur toute la fenêtre, la plus "habituelle"
    $pool = [];
    foreach ($candidates as $sid) {
        $ok = true;
        foreach ($keys as $k) if (!empty($busySalle[$sid][$jour][$k])) { $ok = false; break; }
        if ($ok) $pool[] = $sid;
    }
    if (empty($pool)) return null;

    usort($pool, function ($a, $b) use ($useCount) {
        $ua = $useCount[$a] ?? 0; $ub = $useCount[$b] ?? 0;
        if ($ua === $ub) return rand(-1, 1);
        return $ua <=> $ub;
    });

    return $pool[0];
}
}
