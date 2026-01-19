<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            // Informations utilisateur
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name, // ✅ Utiliser le vrai champ name
                'nom_complet' => $this->user->nom_complet, // ✅ Utiliser l'accesseur
                'email' => $this->user->email,
                'role' => $this->user->role, // ✅ Ajouter le champ role
                'created_at' => $this->user->created_at?->format('Y-m-d H:i:s'),
            ],

            // Informations parent
            'profession' => $this->profession,
            'telephone' => $this->telephone,
            'adresse' => $this->adresse,
            'contact_urgence_nom' => $this->contact_urgence_nom,
'contact_urgence_telephone' => $this->contact_urgence_telephone,


            // Enfants
            'enfants' => $this->whenLoaded('enfants', function () {
                return $this->enfants->map(function ($enfant) {
                    return [
                        'id' => $enfant->id,
                        'nom' => $enfant->nom,
                        'prenom' => $enfant->prenom,
                        'nom_complet' => $enfant->nom . ' ' . $enfant->prenom,
                        'date_naissance' => $enfant->date_naissance?->format('Y-m-d'),
                        'age' => $enfant->date_naissance 
                            ? $enfant->date_naissance->diffInYears(now()) 
                            : null,

                        // Classe (relation chargée ou non)
                        'classe' => $enfant->relationLoaded('classe') ? [
                            'id' => $enfant->classe?->id,
                            'nom' => $enfant->classe?->nom,
                        ] : null,

                        // Notes récentes
                        'notes_recentes' => $enfant->relationLoaded('suivieNotes') 
                            ? $enfant->suivieNotes->take(3)->map(function ($note) {
                                return [
                                    'id' => $note->id,
                                    'note' => $note->note,
                                    'mention' => $note->mention,
                                    'matiere' => $note->matiere?->nom,
                                    'date_evaluation' => $note->date_evaluation?->format('Y-m-d'),
                                ];
                            }) 
                            : null,
                    ];
                });
            }),

            // Statistiques
            'nombre_enfants' => $this->whenLoaded('enfants', function () {
                return $this->enfants->count();
            }),

            // Métadonnées
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}