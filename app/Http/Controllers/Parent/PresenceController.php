<?php


namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\Enfant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PresenceController extends Controller
{
    /**
     * GET /api/parent/enfants
     * Récupérer les enfants du parent connecté
     */
   public function getEnfantsParent(): JsonResponse
{
    try {
        $user = Auth::user();
        if (!$user || !$user->parent) {
            return response()->json([
                'success' => false,
                'message' => 'Profil parent non trouvé'
            ], 404);
        }

        $parent = $user->parent;

        $enfants = $parent->enfants()
            ->with(['classe:id,nom,niveau'])
            ->select([
                'enfant.id',
                'enfant.nom',
                'enfant.prenom',
                'enfant.classe_id',
                'enfant.date_naissance',
            ])
            ->orderBy('enfant.nom')
            ->get()
            ->map(function ($enfant) {
                $age = null;
                if (!empty($enfant->date_naissance)) {
                    try {
                        $age = \Carbon\Carbon::now()
                            ->diffInYears(\Carbon\Carbon::parse($enfant->date_naissance));
                    } catch (\Throwable $e) {}
                }
                return [
                    'id'          => $enfant->id,
                    'nom'         => $enfant->nom,
                    'prenom'      => $enfant->prenom,
                    'nom_complet' => trim(($enfant->prenom ?? '').' '.($enfant->nom ?? '')),
                    'age'         => $age,
                    'classe'      => $enfant->classe ? [
                        'id'     => $enfant->classe->id,
                        'nom'    => $enfant->classe->nom,
                        'niveau' => $enfant->classe->niveau,
                    ] : null,
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $enfants]);

    } catch (\Throwable $e) {
        \Log::error('[parent/enfants] erreur', [
            'msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des enfants',
            // 'debug' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * GET /api/parent/enfants/{enfantId}/presences
     * Consulter les présences d'un enfant
     */
    public function getPresencesEnfant(Request $request, $enfantId): JsonResponse
    {
        try {
            $user = Auth::user();
            $parent = $user->parent;
            
            // Vérifier que l'enfant appartient au parent
            $enfant = $parent->enfants()->with('classe')->findOrFail($enfantId);
            
            $dateDebut = $request->get('date_debut', now()->subDays(30)->format('Y-m-d'));
            $dateFin = $request->get('date_fin', now()->format('Y-m-d'));
            $perPage = $request->get('per_page', 15);

            $presences = Presence::where('enfant_id', $enfantId)
                ->whereBetween('date_presence', [$dateDebut, $dateFin])
                ->with(['educateur.user:id,name'])
                ->orderBy('date_presence', 'desc')
                ->paginate($perPage);

            // Calculer les statistiques
            $totalJours = $presences->total();
            $joursPresents = Presence::where('enfant_id', $enfantId)
                ->whereBetween('date_presence', [$dateDebut, $dateFin])
                ->where('statut', 'present')
                ->count();
            $joursAbsents = $totalJours - $joursPresents;
            $tauxPresence = $totalJours > 0 ? round(($joursPresents / $totalJours) * 100, 1) : 0;

            $presencesFormatted = $presences->getCollection()->map(function($presence) {
                return [
                    'id' => $presence->id,
                    'date' => $presence->date_presence->format('Y-m-d'),
                    'date_libelle' => $presence->date_presence->locale('fr')->isoFormat('dddd DD MMMM YYYY'),
                    'statut' => $presence->statut,
                    'educateur_nom' => $presence->educateur->user->name,
                    'updated_at' => $presence->updated_at->format('H:i')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'enfant' => [
                        'id' => $enfant->id,
                        'nom_complet' => "{$enfant->prenom} {$enfant->nom}",
                        'classe' => $enfant->classe ? [
                            'nom' => $enfant->classe->nom,
                            'niveau' => $enfant->classe->niveau
                        ] : null
                    ],
                    'presences' => $presencesFormatted,
                    'statistiques' => [
                        'total_jours' => $totalJours,
                        'jours_presents' => $joursPresents,
                        'jours_absents' => $joursAbsents,
                        'taux_presence' => $tauxPresence
                    ],
                    'periode' => [
                        'debut' => $dateDebut,
                        'fin' => $dateFin
                    ]
                ],
                'pagination' => [
                    'current_page' => $presences->currentPage(),
                    'last_page' => $presences->lastPage(),
                    'per_page' => $presences->perPage(),
                    'total' => $presences->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des présences'
            ], 500);
        }
    }

    /**
     * GET /api/parent/enfants/{enfantId}/presences/calendrier
     * Calendrier des présences de l'enfant
     */
    public function getCalendrierEnfant(Request $request, $enfantId): JsonResponse
    {
        try {
            $user = Auth::user();
            $parent = $user->parent;
            
            // Vérifier que l'enfant appartient au parent
            $enfant = $parent->enfants()->findOrFail($enfantId);
            
            $mois = $request->get('mois', now()->format('Y-m'));
            $dateDebut = Carbon::parse($mois . '-01')->startOfMonth();
            $dateFin = $dateDebut->copy()->endOfMonth();

            $presences = Presence::where('enfant_id', $enfantId)
                ->whereBetween('date_presence', [$dateDebut, $dateFin])
                ->get()
                ->keyBy(function($presence) {
                    return $presence->date_presence->format('Y-m-d');
                });

            // Générer le calendrier du mois
            $calendrier = [];
            $date = $dateDebut->copy();
            
            while ($date->lte($dateFin)) {
                $dateStr = $date->format('Y-m-d');
                $presence = $presences->get($dateStr);
                
                $calendrier[] = [
                    'date' => $dateStr,
                    'jour' => $date->format('d'),
                    'jour_semaine' => $date->locale('fr')->isoFormat('dddd'),
                    'est_weekend' => $date->isWeekend(),
                    'statut' => $presence ? $presence->statut : null,
                    'a_presence' => (bool) $presence
                ];
                
                $date->addDay();
            }

            // Statistiques du mois
            $presencesMois = $presences->whereNotNull('statut');
            $totalJours = $presencesMois->count();
            $joursPresents = $presencesMois->where('statut', 'present')->count();
            $joursAbsents = $totalJours - $joursPresents;

            return response()->json([
                'success' => true,
                'data' => [
                    'mois' => $mois,
                    'mois_libelle' => $dateDebut->locale('fr')->isoFormat('MMMM YYYY'),
                    'calendrier' => $calendrier,
                    'statistiques_mois' => [
                        'total_jours_ecole' => $totalJours,
                        'jours_presents' => $joursPresents,
                        'jours_absents' => $joursAbsents,
                        'taux_presence' => $totalJours > 0 ? round(($joursPresents / $totalJours) * 100, 1) : 0
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du calendrier'
            ], 500);
        }
    }
}
