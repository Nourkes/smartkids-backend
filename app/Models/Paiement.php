<?php
// app/Models/Paiement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    use HasFactory;

    protected $table = 'paiements';

    protected $fillable = [
        'parent_id',
        'inscription_id',
        'participation_activite_id',
        'montant',
        'type',
        'methode_paiement',
        'date_paiement',
        'date_echeance',
        'statut',
        'reference_transaction',
        'remarques',
    ];

protected $casts = [
    'montant'            => 'decimal:2',
    'date_paiement'      => 'date',
    'date_echeance'      => 'date',
    'periodes_couvertes' => 'array',
];


    public function parent()
    {
        return $this->belongsTo(ParentModel::class);
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }

    public function participationActivite()
    {
        return $this->belongsTo(ParticipationActivite::class);
    }
    public function scopeEnAttente($q) { return $q->where('statut','en_attente'); }

}