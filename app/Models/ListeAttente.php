<?php
// app/Models/ListeAttente.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListeAttente extends Model
{
    use HasFactory;

    protected $table = 'liste_attente';

    protected $fillable = [
        'nom_enfant',
        'prenom_enfant',
        'date_naissance_enfant',
        'nom_parent',
        'telephone_parent',
        'email_parent',
        'date_demande',
        'position',
        'statut',
        'remarques',
    ];

    protected $casts = [
        'date_naissance_enfant' => 'date',
        'date_demande' => 'date',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class);
    }
}