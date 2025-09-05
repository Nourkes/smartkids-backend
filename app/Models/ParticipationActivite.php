<?php
// app/Models/ParticipationActivite.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipationActivite extends Model
{
    use HasFactory;

    protected $table = 'participation_activite';

    protected $fillable = [
        'enfant_id',
        'activite_id',
    ];

    public function enfant()
    {
        return $this->belongsTo(Enfant::class);
    }

    public function activite()
    {
        return $this->belongsTo(Activite::class);
    }

    public function paiement()
    {
        return $this->hasOne(Paiement::class);
    }
}