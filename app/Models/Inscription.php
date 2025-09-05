<?php
// app/Models/Inscription.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    use HasFactory;

    protected $table = 'inscriptions';

    protected $fillable = [
        'enfant_id',
        'liste_attente_id',
        'annee_scolaire',
        'date_inscription',
        'statut',
        'frais_inscription',
        'frais_mensuel',
        'documents_fournis',
        'remarques',
    ];

    protected $casts = [
        'date_inscription' => 'date',
        'frais_inscription' => 'decimal:2',
        'frais_mensuel' => 'decimal:2',
        'documents_fournis' => 'array',
    ];

    public function enfant()
    {
        return $this->belongsTo(Enfant::class);
    }

    public function listeAttente()
    {
        return $this->belongsTo(ListeAttente::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
}