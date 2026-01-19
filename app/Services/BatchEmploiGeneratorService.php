<?php
namespace App\Services;

use App\Models\{Classe, EmploiTemplate, EmploiTemplateSlot};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;

class BatchEmploiGeneratorService
{
    public function generateAll(?array $classeIds, string $ps, string $pe, string $ef): array
    {
        $grid = $this->buildGrid();

        $classes = Classe::query()
            ->when($classeIds, fn($q) => $q->whereIn('id', $classeIds))
            ->with('salle')
            ->get();

        if ($classes->isEmpty()) return ['templates' => []];

        $tasksByClasse = $this->loadTasksFor($classes->pluck('id')->all());
        $educByClasse  = $this->loadEducatorsFor($classes->pluck('id')->all());

        // disponibilités et occupations déjà publiées
        $allEducIds = array_values(array_unique(array_merge(...array_map(fn($ids) => $ids, $educByClasse ?: [[]]))));
        $availability = $this->loadAvailability($allEducIds);
        [$busyEdu, $busySalle] = $this->loadBusyMapsPublished();

        // quotas
        $blockMin   = (int) Config::get('emploi.block_min', 60);
        $capMinutes = (int) Config::get('emploi.educateur_max_h_week', 20) * 60;
        $capMin     = array_fill_keys($allEducIds, $capMinutes);
        $loadMin    = $this->loadPublishedWeeklyMinutes($allEducIds);

        // état par classe
        $classBusy       = [];         // [classe_id][jour]["HH:MM-HH:MM"]
        $classLoadPerDay = [];         // [classe_id][jour] => nb slots
        $classSalleUse   = [];         // [classe_id][salle_id] => compteur (pour minimiser les changements)

        // priorité : classes les plus "chargées" par éducateur
        $ordered = $classes->sortByDesc(function ($c) use ($tasksByClasse, $educByClasse) {
            $blocks = array_sum(array_column($tasksByClasse[$c->id] ?? [], 'blocks'));
            $neduc  = max(1, count($educByClasse[$c->id] ?? []));
            return $blocks / $neduc;
        });

        $out = ['templates' => []];

        foreach ($ordered as $classe) {
            $tasks   = $tasksByClasse[$classe->id] ?? [];
            $educIds = $educByClasse[$classe->id] ?? [];

            $classBusy[$classe->id]       = [];
            $classLoadPerDay[$classe->id] = array_fill(1, 6, 0);
            $classSalleUse[$classe->id]   = [];

            // 🔹 salles candidates & home room
            $candidateSalles = $this->candidateSallesForClasse($classe);
            $homeSalleId     = $classe->salle_id ?: ($candidateSalles[0] ?? null);

            $version = (int) (EmploiTemplate::where('classe_id', $classe->id)->max('version') ?? 0) + 1;

            $tpl = DB::transaction(function () use (
                $classe, $ps, $pe, $ef, $version, $grid, $tasks, $educIds, $availability,
                &$busyEdu, &$busySalle, &$classBusy, &$classLoadPerDay, &$classSalleUse,
                &$loadMin, $capMin, $blockMin, $candidateSalles, $homeSalleId
            ) {
                $tpl = EmploiTemplate::create([
                    'classe_id'      => $classe->id,
                    'period_start'   => $ps,
                    'period_end'     => $pe,
                    'effective_from' => $ef,
                    'status'         => 'draft',
                    'version'        => $version,
                    'generated_by'   => auth()->id(),
                ]);

                foreach ($tasks as $task) {
                    $matiereId = (int) $task['matiere_id'];
                    $blocks    = (int) $task['blocks'];

                    // éducateur principal si possible
                    $primary = $this->choosePrimaryEducateurForMatiere(
                        $matiereId, $blocks, $educIds, $availability, $busyEdu, $grid, $loadMin, $capMin, $blockMin
                    );
                    $educOrder = $primary ? array_values(array_unique([$primary, ...$educIds])) : $educIds;

                    // grappes
                    $clusters = $this->makeClusters($blocks);

                    foreach ($clusters as $packSize) {
                        // 1) tenter un pack consécutif (avec choix de salle)
                        if ($this->tryPlaceConsecutivePackForClass(
                            $packSize, $grid, $educOrder, $availability, $busyEdu, $busySalle,
                            $loadMin, $capMin, $blockMin,
                            $tpl->id, $matiereId,
                            $classBusy[$classe->id], $classLoadPerDay[$classe->id],
                            $candidateSalles, $homeSalleId, $classSalleUse[$classe->id]
                        )) {
                            continue;
                        }

                        // 2) fallback: blocs unitaires
                        for ($i = 0; $i < $packSize; $i++) {
                            $slot = $this->findSingleSlotForClass(
                                $grid, $educOrder, $availability, $busyEdu, $busySalle,
                                $loadMin, $capMin, $blockMin,
                                $classBusy[$classe->id], $classLoadPerDay[$classe->id],
                                $candidateSalles, $homeSalleId, $classSalleUse[$classe->id]
                            );
                            if (!$slot) break;

                            [$jour, $debut, $fin, $educateurId, $salleId] = $slot;

                            EmploiTemplateSlot::create([
                                'emploi_template_id' => $tpl->id,
                                'jour_semaine'       => $jour,
                                'debut'              => $debut,
                                'fin'                => $fin,
                                'matiere_id'         => $matiereId,
                                'educateur_id'       => $educateurId,
                                'salle_id'           => $salleId,
                                'status'             => 'planned',
                            ]);

                            $key = "$debut-$fin";
                            $busyEdu[$educateurId][$jour][$key] = true;
                            if ($salleId) {
                                $busySalle[$salleId][$jour][$key] = true;
                                $classSalleUse[$classe->id][$salleId] = ($classSalleUse[$classe->id][$salleId] ?? 0) + 1;
                            }
                            $classBusy[$classe->id][$jour][$key] = true;

                            $classLoadPerDay[$classe->id][$jour] = ($classLoadPerDay[$classe->id][$jour] ?? 0) + 1;
                            $loadMin[$educateurId] = ($loadMin[$educateurId] ?? 0) + $blockMin;
                        }
                    }
                }

                return $tpl->loadCount('slots');
            });

            $need = array_sum(array_column($tasks, 'blocks'));
            $out['templates'][] = [
                'classe_id'   => $classe->id,
                'template_id' => $tpl->id,
                'slots'       => $tpl->slots_count,
                'unplaced'    => max(0, $need - $tpl->slots_count),
            ];
        }

        return $out;
    }

    /* ====================== GRID & DATA ====================== */

    private function buildGrid(): array
    {
        $days   = Config::get('emploi.days', [1,2,3,4,5,6]);
        $start  = Config::get('emploi.day_start', '08:30');
        $end    = Config::get('emploi.day_end',   '16:30');
        $block  = (int) Config::get('emploi.block_min', 60);
        $breaks = Config::get('emploi.breaks', [['start'=>'12:00','end'=>'13:00']]);

        $grid = [];
        foreach ($days as $d) {
            $slots = [];
            $t  = Carbon::createFromTimeString($start);
            $te = Carbon::createFromTimeString($end);
            while ($t->lt($te)) {
                $slotStart = $t->copy();
                $slotEnd   = $t->copy()->addMinutes($block);
                if ($slotEnd->gt($te)) break;

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

    private function loadTasksFor(array $classeIds): array
    {
        $block = (int) Config::get('emploi.block_min', 60);
        $rows = DB::table('classe_matiere')->whereIn('classe_id',$classeIds)
            ->select('classe_id','matiere_id','heures_par_semaine')->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->classe_id][] = [
                'matiere_id' => (int)$r->matiere_id,
                'blocks'     => (int)ceil(($r->heures_par_semaine * 60) / $block),
            ];
        }
        foreach ($map as &$arr) usort($arr, fn($a,$b)=>$b['blocks'] <=> $a['blocks']);
        return $map;
    }

    private function loadEducatorsFor(array $classeIds): array
    {
        $rows = DB::table('educateur_classe')->whereIn('classe_id',$classeIds)->get();
        $map = [];
        foreach ($rows as $r) $map[$r->classe_id][] = (int)$r->educateur_id;
        return $map;
    }

    private function loadAvailability(array $educIds): array
    {
        if (!$educIds) return [];
        $rows = DB::table('educator_availabilities')
            ->whereIn('educateur_id',$educIds)
            ->orderBy('jour_semaine')->orderBy('debut')->get();

        $map = [];
        foreach ($rows as $r) $map[$r->educateur_id][$r->jour_semaine][] = ['debut'=>$r->debut,'fin'=>$r->fin];
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
            $key = "$r->debut-$r->fin";
            $edu[$r->educateur_id][$r->jour_semaine][$key] = true;
            if ($r->salle_id) $salle[$r->salle_id][$r->jour_semaine][$key] = true;
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

    private function choosePrimaryEducateurForMatiere(
        int $matiereId, int $blocks,
        array $educators, array $availability, array $busyEdu, array $grid,
        array $loadMin, array $capMin, int $blockMin
    ): ?int {
        $ordered = $educators;
        usort($ordered, fn($a,$b)=>($loadMin[$a] ?? 0) <=> ($loadMin[$b] ?? 0));
        $need = $blocks * $blockMin;
        foreach ($ordered as $e) {
            if ( ($loadMin[$e] ?? 0) + $need <= ($capMin[$e] ?? PHP_INT_MAX) ) return $e;
        }
        return $ordered[0] ?? null;
    }

    /* ================== Placement avec choix de salle ================== */

    private function makeClusters(int $blocks): array {
        $pref = (int) Config::get('emploi.prefer_consecutive_blocks', 2);
        $max  = (int) Config::get('emploi.max_consecutive_blocks', 3);
        $pref = max(1, min($pref, $max));
        $out = [];
        while ($blocks > 0) { $take = min($pref, $blocks); $out[] = $take; $blocks -= $take; }
        return $out;
    }

    private function tryPlaceConsecutivePackForClass(
        int $k, array $grid, array $educOrder, array $availability,
        array &$busyEdu, array &$busySalle, array &$loadMin, array $capMin, int $blockMin,
        int $tplId, int $matiereId,
        array &$classBusy, array &$dayLoad,
        array $candidateSalles, ?int $homeSalleId, array &$useCount
    ): bool {
        $jours = array_keys($grid);
        usort($jours, fn($a,$b)=>($dayLoad[$a] ?? 0) <=> ($dayLoad[$b] ?? 0));

        foreach ($jours as $jour) {
            $slots = $grid[$jour]; $n = count($slots);
            if ($n < $k) continue;

            for ($i=0; $i <= $n - $k; $i++) {
                $keys=[]; $okClass=true;
                for ($t=0; $t<$k; $t++) {
                    $d=$slots[$i+$t]['debut']; $f=$slots[$i+$t]['fin']; $key="$d-$f";
                    $keys[] = $key;
                    if (!empty($classBusy[$jour][$key])) { $okClass=false; break; }
                }
                if (!$okClass) continue;

                // ⬇️ choisir 1 salle pour toute la grappe
                $salleId = $this->chooseSalleForPack($jour, $keys, $homeSalleId, $candidateSalles, $busySalle, $useCount);
                if ($salleId === null) continue;

                foreach ($educOrder as $e) {
                    if ( (($loadMin[$e] ?? 0) + $k*$blockMin) > ($capMin[$e] ?? PHP_INT_MAX) ) continue;

                    $okAll = true;
                    for ($t=0; $t<$k; $t++) {
                        $d=$slots[$i+$t]['debut']; $f=$slots[$i+$t]['fin']; $key=$keys[$t];
                        if (!$this->isWithinAvailability($availability,$e,$jour,$d,$f)) { $okAll=false; break; }
                        if (!empty($busyEdu[$e][$jour][$key])) { $okAll=false; break; }
                        if (!empty($busySalle[$salleId][$jour][$key])) { $okAll=false; break; }
                    }
                    if (!$okAll) continue;

                    // place le pack
                    for ($t=0; $t<$k; $t++) {
                        $d=$slots[$i+$t]['debut']; $f=$slots[$i+$t]['fin']; $key=$keys[$t];
                        EmploiTemplateSlot::create([
                            'emploi_template_id'=>$tplId,
                            'jour_semaine'=>$jour,'debut'=>$d,'fin'=>$f,
                            'matiere_id'=>$matiereId,'educateur_id'=>$e,
                            'salle_id'=>$salleId,'status'=>'planned',
                        ]);
                        $busyEdu[$e][$jour][$key]    = true;
                        $busySalle[$salleId][$jour][$key] = true;
                        $classBusy[$jour][$key]      = true;
                    }
                    $useCount[$salleId] = ($useCount[$salleId] ?? 0) + $k;
                    $dayLoad[$jour]     = ($dayLoad[$jour] ?? 0) + $k;
                    $loadMin[$e]        = ($loadMin[$e] ?? 0) + $k*$blockMin;
                    return true;
                }
            }
        }
        return false;
    }

    private function findSingleSlotForClass(
        array $grid, array $educOrder, array $availability,
        array &$busyEdu, array &$busySalle, array &$loadMin, array $capMin, int $blockMin,
        array &$classBusy, array &$dayLoad,
        array $candidateSalles, ?int $homeSalleId, array &$useCount
    ): ?array {
        $jours = array_keys($grid);
        usort($jours, fn($a,$b)=>($dayLoad[$a] ?? 0) <=> ($dayLoad[$b] ?? 0));

        foreach ($jours as $jour) {
            foreach ($grid[$jour] as $s) {
                $d=$s['debut']; $f=$s['fin']; $key="$d-$f";
                if (!empty($classBusy[$jour][$key])) continue;

                // ⬇️ choisir une salle pour ce créneau
                $salleId = $this->chooseSalleForSlot($jour, $d, $f, $homeSalleId, $candidateSalles, $busySalle, $useCount);
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

    /* ============================ Choix des salles ============================ */

    /** Salles compatibles pour la classe (ex: par capacité). */
    private function candidateSallesForClasse(Classe $classe): array
    {
        // capacité “cible”
        $need = $classe->capacite ?? $classe->effectif ?? $classe->capacite_max ?? null;

        $q = DB::table('salles');
        if ($need) $q->where('capacite', '>=', $need);

        $ids = $q->orderBy('capacite')->pluck('id')->map(fn($v)=>(int)$v)->all();

        if (empty($ids)) {
            $ids = DB::table('salles')->orderBy('capacite')->pluck('id')->map(fn($v)=>(int)$v)->all();
        }
        return $ids;
    }

    private function chooseSalleForSlot(
        int $jour, string $debut, string $fin, ?int $homeSalleId, array $candidates,
        array &$busySalle, array &$useCount
    ): ?int {
        $key = "$debut-$fin";

        // home room prioritaire si libre
        if ($homeSalleId && in_array($homeSalleId, $candidates, true)) {
            if (empty($busySalle[$homeSalleId][$jour][$key])) return $homeSalleId;
        }

        // autre salle candidate libre, la moins “utilisée”
        $pool = array_values(array_filter(
            $candidates,
            fn($sid) => empty($busySalle[$sid][$jour][$key])
        ));
        if (!$pool) return null;

        usort($pool, function ($a, $b) use ($useCount) {
            $ua = $useCount[$a] ?? 0; $ub = $useCount[$b] ?? 0;
            if ($ua === $ub) return $a <=> $b;
            return $ua <=> $ub;
        });

        return $pool[0];
    }

    private function chooseSalleForPack(
        int $jour, array $keys, ?int $homeSalleId, array $candidates,
        array &$busySalle, array &$useCount
    ): ?int {
        // home room si libre sur toute la fenêtre
        if ($homeSalleId && in_array($homeSalleId, $candidates, true)) {
            $ok = true;
            foreach ($keys as $k) if (!empty($busySalle[$homeSalleId][$jour][$k])) { $ok = false; break; }
            if ($ok) return $homeSalleId;
        }

        // autre candidate libre sur toute la fenêtre
        $pool = [];
        foreach ($candidates as $sid) {
            $ok = true;
            foreach ($keys as $k) if (!empty($busySalle[$sid][$jour][$k])) { $ok = false; break; }
            if ($ok) $pool[] = $sid;
        }
        if (!$pool) return null;

        usort($pool, function ($a, $b) use ($useCount) {
            $ua = $useCount[$a] ?? 0; $ub = $useCount[$b] ?? 0;
            if ($ua === $ub) return $a <=> $b;
            return $ua <=> $ub;
        });

        return $pool[0];
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
}
