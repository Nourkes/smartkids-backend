<?php
namespace App\Services;

use App\Models\Presence;
use App\Models\Educateur;
use App\Models\Classe;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PresenceService
{
    public function calculerStatistiquesClasse(Classe $classe, Educateur $educateur, Carbon $dateDebut, Carbon $dateFin): array
    {
        $presences = Presence::where('educateur_id', $educateur->id)
            ->whereHas('enfant', function($q) use ($classe) {
                $q->where('classe_id', $classe->id);
            })
            ->whereBetween('date_presence', [$dateDebut, $dateFin])
            ->get();

        $total = $presences->count();
        $presents = $presences->where('statut', 'present')->count();
        $absents = $presents - $total;
        $tauxPresence = $total > 0 ? round(($presents / $total) * 100, 1) : 0;

        return [
            'total_presences' => $total,
            'presents' => $presents,
            'absents' => $absents,
            'taux_presence' => $tauxPresence,
            'periode' => [
                'debut' => $dateDebut->format('Y-m-d'),
                'fin' => $dateFin->format('Y-m-d')
            ]
        ];
    }

    public function obtenirEnfantsAbsentsFrequents(Classe $classe, Educateur $educateur, int $seuil = 3): Collection
    {
        // Récupérer les enfants avec un taux d'absence élevé sur les 30 derniers jours
        $dateDebut = now()->subDays(30);
        
        return $classe->enfants()
            ->withCount(['presences as total_presences' => function($q) use ($educateur, $dateDebut) {
                $q->where('educateur_id', $educateur->id)
                  ->where('date_presence', '>=', $dateDebut);
            }])
            ->withCount(['presences as absences_count' => function($q) use ($educateur, $dateDebut) {
                $q->where('educateur_id', $educateur->id)
                  ->where('date_presence', '>=', $dateDebut)
                  ->where('statut', 'absent');
            }])
            ->having('absences_count', '>=', $seuil)
            ->get()
            ->map(function($enfant) {
                $tauxAbsence = $enfant->total_presences > 0 
                    ? round(($enfant->absences_count / $enfant->total_presences) * 100, 1) 
                    : 0;
                    
                return [
                    'enfant' => $enfant,
                    'total_presences' => $enfant->total_presences,
                    'absences' => $enfant->absences_count,
                    'taux_absence' => $tauxAbsence
                ];
            });
    }

    public function verifierPresencesDuJour(Educateur $educateur): array
    {
        $today = now()->format('Y-m-d');
        
        $classesAvecPresences = $educateur->classes()
            ->with(['enfants'])
            ->get()
            ->map(function($classe) use ($educateur, $today) {
                $totalEnfants = $classe->enfants->count();
                $presencesPrises = Presence::where('educateur_id', $educateur->id)
                    ->where('date_presence', $today)
                    ->whereHas('enfant', function($q) use ($classe) {
                        $q->where('classe_id', $classe->id);
                    })
                    ->count();
                    
                return [
                    'classe_id' => $classe->id,
                    'classe_nom' => $classe->nom,
                    'total_enfants' => $totalEnfants,
                    'presences_prises' => $presencesPrises,
                    'est_complete' => $presencesPrises >= $totalEnfants
                ];
            });
            
        return [
            'date' => $today,
            'classes' => $classesAvecPresences,
            'total_classes' => $classesAvecPresences->count(),
            'classes_completes' => $classesAvecPresences->where('est_complete', true)->count()
        ];
    }
}
