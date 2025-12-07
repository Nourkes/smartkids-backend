<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enfant extends Model
{
    use HasFactory;

    protected $table = 'enfant';

    protected $fillable = [
        'nom',
        'prenom',
        'sexe',
        'date_naissance',
        'classe_id',
        'allergies',
        'remarques_medicales',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    /** ---------------------
     * Relations
     * --------------------- */

    // Relation : un enfant appartient à une classe
    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    // Relation many-to-many : un enfant peut avoir plusieurs parents
    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'enfant_parent', 'enfant_id', 'parent_id')
            ->withTimestamps();
    }

    // Relation one-to-many : un enfant a plusieurs présences
    public function presences()
    {
        return $this->hasMany(Presence::class);
    }

    // Relation many-to-many : un enfant participe à plusieurs activités
    public function activites()
    {
        return $this->belongsToMany(Activite::class, 'participation_activite')
            ->withPivot('statut_participation', 'remarques', 'note_evaluation')
            ->withTimestamps();
    }

    // Relation one-to-many avec table pivot personnalisée
    // Activités auxquelles l'enfant participe
    public function participationActivites()
    {
        return $this->hasMany(ParticipationActivite::class);
    }

    // Inscriptions de l'enfant (ex: inscription annuelle)
    public function inscriptions()
    {
        return $this->hasMany(Inscription::class);
    }

    // Relation many-to-many avec les matières + infos pivot
    public function matieres()
    {
        return $this->belongsToMany(Matiere::class, 'suivie_note')
            ->withPivot('note', 'type_evaluation', 'date_evaluation', 'trimestre', 'annee_scolaire', 'commentaire', 'educateur_id')
            ->withTimestamps();
    }

    // Relation directe avec la table suivie_note
    public function suivieNotes()
    {
        return $this->hasMany(\App\Models\SuivieNote::class, 'enfant_id');
    }



    /** ---------------------
     * Méthodes utilitaires pour les activités
     * --------------------- */

    // Activités où l’enfant est inscrit
    public function activitesInscrites()
    {
        return $this->activites()->wherePivot('statut_participation', 'inscrit');
    }

    // Activités où l’enfant était présent
    public function activitesPresentes()
    {
        return $this->activites()->wherePivot('statut_participation', 'present');
    }

    // Activités avec note d’évaluation
    public function participationsAvecNotes()
    {
        return $this->activites()->wherePivotNotNull('note_evaluation');
    }

    /** ---------------------
     * Méthodes utilitaires pour les notes
     * --------------------- */

    // Moyenne d’un trimestre donné
    public function getMoyenneParTrimestre($trimestre, $anneeScolaire)
    {
        return $this->suivieNotes()
            ->where('trimestre', $trimestre)
            ->where('annee_scolaire', $anneeScolaire)
            ->avg('note');
    }

    // Moyenne générale pour une année scolaire
    public function getMoyenneGenerale($anneeScolaire)
    {
        return $this->suivieNotes()
            ->where('annee_scolaire', $anneeScolaire)
            ->avg('note');
    }

    // Bulletin détaillé d’un trimestre
    public function getBulletinTrimestre($trimestre, $anneeScolaire)
    {
        return $this->suivieNotes()
            ->with(['matiere', 'educateur'])
            ->where('trimestre', $trimestre)
            ->where('annee_scolaire', $anneeScolaire)
            ->orderBy('matiere_id')
            ->get();
    }
}
