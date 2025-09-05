<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enfant_id' => $this->enfant_id,
            'educateur_id' => $this->educateur_id,
            'date_presence' => $this->date_presence->format('Y-m-d'),
            'statut' => $this->statut,
            'enfant' => new EnfantBasicResource($this->whenLoaded('enfant')),
            'educateur' => new EducateurBasicResource($this->whenLoaded('educateur')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

// EnfantBasicResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnfantBasicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'sexe' => $this->sexe,
            'classe' => new ClasseBasicResource($this->whenLoaded('classe')),
        ];
    }
}

// EducateurBasicResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EducateurBasicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->user->nom ?? null,
            'prenom' => $this->user->prenom ?? null,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'nom' => $this->user->nom,
                    'prenom' => $this->user->prenom,
                ];
            }),
        ];
    }
}

// ClasseBasicResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseBasicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'niveau' => $this->niveau,
            'capacite_max' => $this->when($this->capacite_max, $this->capacite_max),
        ];
    }
}

// EnfantPresenceResource.php - Pour l'écran éducateur
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnfantPresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presenceAujourdhui = $this->presences->first();
        
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'sexe' => $this->sexe,
            'statut_presence' => $presenceAujourdhui?->statut,
            'deja_marque' => $presenceAujourdhui ? true : false,
            'allergies' => $this->when($this->allergies, $this->allergies),
            'remarques_medicales' => $this->when($this->remarques_medicales, $this->remarques_medicales),
        ];
    }
}

// CalendrierResource.php - Pour le calendrier parent
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class CalendrierResource extends JsonResource
{
    private $presences;
    private $mois;
    private $annee;

    public function __construct($enfant, $presences, $mois, $annee)
    {
        parent::__construct($enfant);
        $this->presences = $presences;
        $this->mois = $mois;
        $this->annee = $annee;
    }

    public function toArray(Request $request): array
    {
        // Organiser les présences par jour
        $calendrier = [];
        foreach ($this->presences as $presence) {
            $jour = Carbon::parse($presence->date_presence)->day;
            $calendrier[$jour] = [
                'statut' => $presence->statut,
                'educateur' => $presence->educateur->user->nom . ' ' . $presence->educateur->user->prenom,
                'date' => $presence->date_presence->format('Y-m-d'),
                'heure_creation' => $presence->created_at->format('H:i'),
            ];
        }

        // Calculer les statistiques
        $totalJours = $this->presences->count();
        $presents = $this->presences->where('statut', 'present')->count();
        $absents = $this->presences->where('statut', 'absent')->count();
        $retards = $this->presences->where('statut', 'retard')->count();
        $excuses = $this->presences->where('statut', 'excuse')->count();

        return [
            'enfant' => [
                'id' => $this->id,
                'nom' => $this->nom,
                'prenom' => $this->prenom,
                'classe' => $this->classe ? $this->classe->nom : null,
            ],
            'mois' => $this->mois,
            'annee' => $this->annee,
            'calendrier' => $calendrier,
            'statistiques' => [
                'total_jours' => $totalJours,
                'presents' => $presents,
                'absents' => $absents,
                'retards' => $retards,
                'excuses' => $excuses,
                'taux_presence' => $totalJours > 0 ? round(($presents / $totalJours) * 100, 2) : 0,
                'taux_absence' => $totalJours > 0 ? round(($absents / $totalJours) * 100, 2) : 0,
                'taux_retard' => $totalJours > 0 ? round(($retards / $totalJours) * 100, 2) : 0,
            ],
        ];
    }
}