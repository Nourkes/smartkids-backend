<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\Enfant;
use App\Models\Classe;
use App\Models\Educateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class PresenceController extends Controller
{
    /**
     * Marquer la présence pour une classe (Éducateur)
     */
    public function marquerPresenceClasse(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|exists:classe,id',
            'date_presence' => 'required|date',
            'presences' => 'required|array',
            'presences.*.enfant_id' => 'required|exists:enfant,id',
            'presences.*.statut' => 'required|in:present,absent,retard,excuse'
        ]);

        $educateur = Auth::user()->educateur;
        if (!$educateur) {
            return response()->json(['error' => 'Utilisateur non autorisé'], 403);
        }

        // Vérifier que l'éducateur a accès à cette classe
        if (!$educateur->classes()->where('classe.id', $request->classe_id)->exists()) {
            return response()->json(['error' => 'Accès non autorisé à cette classe'], 403);
        }

        $presencesEnregistrees = [];
        $date = Carbon::parse($request->date_presence)->format('Y-m-d');

        foreach ($request->presences as $presenceData) {
            // Vérifier ou créer la présence
            $presence = Presence::updateOrCreate(
                [
                    'enfant_id' => $presenceData['enfant_id'],
                    'date_presence' => $date,
                ],
                [
                    'educateur_id' => $educateur->id,
                    'statut' => $presenceData['statut'],
                ]
            );

            $presencesEnregistrees[] = $presence->load('enfant');
        }

        return response()->json([
            'message' => 'Présences enregistrées avec succès',
            'presences' => $presencesEnregistrees
        ], 200);
    }

    /**
     * Obtenir les enfants d'une classe avec leur statut de présence pour une date
     */
    public function getEnfantsClasse(Request $request, $classeId)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $educateur = Auth::user()->educateur;
        if (!$educateur) {
            return response()->json(['error' => 'Utilisateur non autorisé'], 403);
        }

        // Vérifier l'accès à la classe
        if (!$educateur->classes()->where('classe.id', $classeId)->exists()) {
            return response()->json(['error' => 'Accès non autorisé à cette classe'], 403);
        }

        $classe = Classe::with(['enfants' => function ($query) use ($request) {
            $query->with(['presences' => function ($subQuery) use ($request) {
                $subQuery->where('date_presence', Carbon::parse($request->date)->format('Y-m-d'));
            }]);
        }])->find($classeId);

        if (!$classe) {
            return response()->json(['error' => 'Classe non trouvée'], 404);
        }

        $enfantsAvecPresence = $classe->enfants->map(function ($enfant) {
            $presenceAujourdhui = $enfant->presences->first();
            return [
                'id' => $enfant->id,
                'nom' => $enfant->nom,
                'prenom' => $enfant->prenom,
                'sexe' => $enfant->sexe,
                'statut_presence' => $presenceAujourdhui ? $presenceAujourdhui->statut : null,
                'deja_marque' => $presenceAujourdhui ? true : false
            ];
        });

        return response()->json([
            'classe' => [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'niveau' => $classe->niveau
            ],
            'enfants' => $enfantsAvecPresence
        ], 200);
    }

    /**
     * Obtenir les classes de l'éducateur connecté
     */
    public function getClassesEducateur()
    {
        $educateur = Auth::user()->educateur;
        if (!$educateur) {
            return response()->json(['error' => 'Utilisateur non autorisé'], 403);
        }

        $classes = $educateur->classes()->select('classe.id', 'classe.nom', 'classe.niveau')->get();

        return response()->json([
            'classes' => $classes
        ], 200);
    }

    /**
     * Obtenir le calendrier de présence d'un enfant (Parent)
     */




public function getCalendrierEnfant(Request $request, $enfantId)
{
    // 0) Mois/année par défaut = courants (si non fournis)
    $mois  = (int) $request->query('mois', now()->month);
    $annee = (int) $request->query('annee', now()->year);

    // validation soft
    $v = Validator::make(['mois'=>$mois,'annee'=>$annee], [
        'mois'  => 'integer|min:1|max:12',
        'annee' => 'integer|min:2020|max:2030',
    ]);
    if ($v->fails()) {
        return response()->json(['message'=>'Validation error','errors'=>$v->errors()], 422);
    }

    // 1) Sécu
    $parent = Auth::user()->parent;
    if (!$parent) return response()->json(['error'=>'Utilisateur non autorisé'], 403);

    // ⚠️ adapte le nom de table si pivot = enfant_parent avec "enfants"
    if (!$parent->enfants()->where('enfant.id', $enfantId)->exists()) {
        return response()->json(['error'=>'Accès non autorisé à cet enfant'], 403);
    }

    $enfant = Enfant::find($enfantId);
    if (!$enfant) return response()->json(['error'=>'Enfant non trouvé'], 404);

    // 2) Fenêtre du mois
    $start = Carbon::createFromDate($annee, $mois, 1)->startOfMonth();
    $end   = $start->copy()->endOfMonth();

    // 3) Récup des présences existantes dans le mois
    $presences = Presence::where('enfant_id', $enfantId)
        ->whereBetween('date_presence', [$start, $end])
        ->with('educateur.user:id,nom,prenom,name') // selon ton schéma
        ->orderBy('date_presence')
        ->get();

    // keyBy date pour lookup rapide
    $byDate = $presences->keyBy(fn($p) => Carbon::parse($p->date_presence)->format('Y-m-d'));

    // 4) Construire la grille complète + stats
    $daysInMonth = $start->daysInMonth;
    $days = [];
    $stats = ['present'=>0, 'absent'=>0, 'retard'=>0, 'excuse'=>0, 'weekend'=>0];

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = $start->copy()->day($d);
        $isoDow = $date->dayOfWeekIso; // 1=lundi ... 7=dimanche
        $isWeekend = in_array($isoDow, [6, 7]); // samedi/dimanche

        $key = $date->format('Y-m-d');
        $p = $byDate->get($key); // Presence | null

        $statut = $p->statut ?? null;

        // stats
        if ($isWeekend) $stats['weekend']++;
        if ($statut && isset($stats[$statut])) $stats[$statut]++;

        $days[] = [
            'day'        => $d,
            'date'       => $key,
            'weekday'    => $isoDow,     // 1..7
            'is_weekend' => $isWeekend,
            'statut'     => $statut,     // present|absent|retard|excuse|null
            'educateur'  => $p?->educateur?->user?->name
                ?? trim(($p?->educateur?->user?->prenom).' '.($p?->educateur?->user?->nom)),
        ];
    }

    // 5) Réponse
    return response()->json([
        'enfant' => [
            'id' => $enfant->id,
            'nom' => $enfant->nom,
            'prenom' => $enfant->prenom,
            'classe' => optional($enfant->classe)->nom,
        ],
        'mois'  => $mois,
        'annee' => $annee,
        'days'  => $days,     // ← grille complète
        'stats' => [
            'present' => $stats['present'],
            'absent'  => $stats['absent'],
            'retard'  => $stats['retard'],
            'excuse'  => $stats['excuse'],
            'weekend' => $stats['weekend'], // pour le badge jaune "Weekend and holiday"
        ],
    ]);
}


    /**
     * Obtenir la liste des enfants du parent pour la sélection
     */
    public function getEnfantsParent()
    {
        $parent = Auth::user()->parent;
        if (!$parent) {
            return response()->json(['error' => 'Utilisateur non autorisé'], 403);
        }

        $enfants = $parent->enfants()->with('classe')->get();

        $enfantsFormates = $enfants->map(function ($enfant) {
            return [
                'id' => $enfant->id,
                'nom' => $enfant->nom,
                'prenom' => $enfant->prenom,
                'sexe' => $enfant->sexe,
                'classe' => $enfant->classe ? [
                    'id' => $enfant->classe->id,
                    'nom' => $enfant->classe->nom,
                    'niveau' => $enfant->classe->niveau
                ] : null
            ];
        });

        return response()->json([
            'enfants' => $enfantsFormates
        ], 200);
    }

    /**
     * Obtenir les statistiques de présence pour un enfant sur une période
     */
    public function getStatistiquesPresence(Request $request, $enfantId)
    {
        $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut'
        ]);

        $parent = Auth::user()->parent;
        if (!$parent) {
            return response()->json(['error' => 'Utilisateur non autorisé'], 403);
        }

        // Vérifier l'accès à l'enfant
        if (!$parent->enfants()->where('enfant.id', $enfantId)->exists()) {
            return response()->json(['error' => 'Accès non autorisé à cet enfant'], 403);
        }

        $presences = Presence::where('enfant_id', $enfantId)
            ->whereBetween('date_presence', [$request->date_debut, $request->date_fin])
            ->get();

        $totalJours = $presences->count();
        $presents = $presences->where('statut', 'present')->count();
        $absents = $presences->where('statut', 'absent')->count();
        $retards = $presences->where('statut', 'retard')->count();
        $excuses = $presences->where('statut', 'excuse')->count();

        return response()->json([
            'periode' => [
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin
            ],
            'statistiques' => [
                'total_jours' => $totalJours,
                'presents' => $presents,
                'absents' => $absents,
                'retards' => $retards,
                'excuses' => $excuses,
                'taux_presence' => $totalJours > 0 ? round(($presents / $totalJours) * 100, 2) : 0,
                'taux_absence' => $totalJours > 0 ? round(($absents / $totalJours) * 100, 2) : 0,
                'taux_retard' => $totalJours > 0 ? round(($retards / $totalJours) * 100, 2) : 0
            ]
        ], 200);
    }
}