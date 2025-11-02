<?php
namespace App\Services;

use App\Models\Inscription;
use App\Models\Paiement;
use App\Support\AcademicCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str; // en haut si tu utilises l’option par niveau

class PaymentService
{
    private function N(): int { return (int) config('smartkids.mois_par_annee', 9); }

private function monthly(Inscription $i): float
{
    if ($i->classe && $i->classe->frais_mensuel) {
        return (float) $i->classe->frais_mensuel;
    }
    // Optionnel : par niveau (si tu l’as configuré)
    $map = config('smartkids.frais_mensuel_by_niveau', []);
    if (!empty($map) && array_key_exists($i->niveau_souhaite, $map)) {
        return (float) $map[$i->niveau_souhaite];
    }
    // Fallback global
    return (float) config('smartkids.frais_mensuel', 300);
}

    private function resolveYearAndIndex(Inscription $i, Carbon $payDate): array
    {
        $start = AcademicCalendar::yearStart($i->annee_scolaire);
        $idx   = AcademicCalendar::periodIndexForDate($start, $this->N(), $payDate);
        return [$start, $idx];
    }

    private function coveredIndices(Inscription $i): array
    {
        return $i->paiements()
            ->where('type','scolarite')
            ->whereIn('statut',['paye','confirmé','valide'])
            ->pluck('periodes_couvertes')->flatten()->unique()->values()->all();
    }

    private function deadlineDays(): int
    {
        return (int) config('school.payment_deadline_days', 3);
    }

    /** 1er mois (prorata par semaines ISO du mois de pay_date) */
    public function quoteFirstMonthProrata(Inscription $i, Carbon $firstPayDate): array
    {
        [$yearStart, $idx] = $this->resolveYearAndIndex($i, $firstPayDate);
        abort_if($idx === null, 422, "Date de paiement hors de l’année scolaire.");

        [$pStart, $pEnd] = AcademicCalendar::periodBounds($yearStart, $idx);
        $weeksInMonth    = AcademicCalendar::weeksInMonthIso($firstPayDate);    // 4..6
        $weekNumber      = AcademicCalendar::weekIndexInMonthIso($firstPayDate); // 1..N
        $weeksRemaining  = max(1, min($weeksInMonth, $weeksInMonth - ($weekNumber - 1)));

        $amount = round(($this->monthly($i) / $weeksInMonth) * $weeksRemaining, 2);

        if (in_array($idx, $this->coveredIndices($i), true)) {
            abort(422, "La période académique est déjà payée.");
        }

        // Échéance = min(fin de période, today + deadline)
        $dueByPeriodEnd = $pEnd->copy();
        $dueByDeadline  = now()->copy()->addDays($this->deadlineDays());
        $due            = $dueByPeriodEnd->lt($dueByDeadline) ? $dueByPeriodEnd : $dueByDeadline;

        return [
            'periode_index'   => $idx,
            'periode_start'   => $pStart->toDateString(),
            'periode_end'     => $pEnd->toDateString(), // exclusive
            'montant_du'      => $amount,
            'montant_mensuel' => round($this->monthly($i), 2),
            'echeance'        => $due->toDateString(),
        ];
    }

    /** Création du paiement 1er mois */
    public function createFirstMonthPayment(Inscription $i, array $quote, string $methode='cash'): Paiement
    {
        return DB::transaction(function () use ($i, $quote, $methode) {
            return Paiement::create([
                'parent_id'          => $i->parent_id, // peut être null avant finalisation
                'inscription_id'     => $i->id,
                'type'               => 'scolarite',
                'plan'               => 'mensuel',
                'periodes_couvertes' => [$quote['periode_index']],
                'montant'            => $quote['montant_du'],
                'methode_paiement'   => $methode,
                'date_echeance'      => $quote['echeance'],
                'statut'             => 'en_attente',
            ]);
        });
    }

    /** Mois suivants (montant plein) */
    public function quoteMonthly(Inscription $i, Carbon $payDate): array
    {
        [$yearStart, $idx] = $this->resolveYearAndIndex($i, $payDate);
        abort_if($idx === null, 422, "Date de paiement hors de l’année scolaire.");
        if (in_array($idx, $this->coveredIndices($i), true)) {
            abort(422, "La période académique est déjà payée.");
        }
        [$pStart, $pEnd] = AcademicCalendar::periodBounds($yearStart, $idx);

        // Échéance = min(fin de période, début de période + deadline)
        $dueByPeriodEnd = $pEnd->copy();
        $dueByDeadline  = $pStart->copy()->addDays($this->deadlineDays());
        $due            = $dueByPeriodEnd->lt($dueByDeadline) ? $dueByPeriodEnd : $dueByDeadline;

        return [
            'periode_index'   => $idx,
            'periode_start'   => $pStart->toDateString(),
            'periode_end'     => $pEnd->toDateString(),
            'montant_du'      => round($this->monthly($i), 2),
            'echeance'        => $due->toDateString(),
        ];
    }
}
