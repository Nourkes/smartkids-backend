<?php
// app/Models/Matiere.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matiere extends Model
{
    use HasFactory;

    protected $table = 'matiere';

    protected $fillable = [
        'nom',
        'description',
        'code',
        'niveau',
        'coefficient',
        'couleur',
        'actif',
        'photo',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    // Relation Many-to-Many avec Classe
    public function classes()
    {
        return $this->belongsToMany(Classe::class, 'classe_matiere')
                    ->withPivot('heures_par_semaine', 'objectifs_specifiques')
                    ->withTimestamps();
    }

    // Relation Many-to-Many avec Enfant via suivie_note
    public function enfants()
    {
        return $this->belongsToMany(Enfant::class, 'suivie_note')
                    ->withPivot('note', 'type_evaluation', 'date_evaluation', 'trimestre', 'annee_scolaire', 'commentaire', 'educateur_id')
                    ->withTimestamps();
    }

    // Relation directe avec les notes
    public function suivieNotes()
    {
        return $this->hasMany(SuivieNote::class);
    }

    // Méthodes utilitaires
    public function getMoyenneParTrimestre($trimestre, $anneeScolaire)
    {
        return $this->suivieNotes()
                    ->where('trimestre', $trimestre)
                    ->where('annee_scolaire', $anneeScolaire)
                    ->avg('note');
    }

    public function getMoyenneGenerale($anneeScolaire)
    {
        return $this->suivieNotes()
                    ->where('annee_scolaire', $anneeScolaire)
                    ->avg('note');
    }
}