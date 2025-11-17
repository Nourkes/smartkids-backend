<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presence extends Model
{
    use HasFactory;

    protected $table = 'presence';

    protected $fillable = [
        'enfant_id',
        'educateur_id',
        'date_presence',
        'statut',
    ];

    protected $casts = [
        'date_presence' => 'date',
    ];

    /**
     * Relation : cette présence concerne un enfant.
     */
    public function enfant()
    {
        return $this->belongsTo(Enfant::class);
    }

    /**
     * Relation : cette présence est enregistrée par un éducateur.
     */
    public function educateur()
    {
        return $this->belongsTo(Educateur::class);
    }
}
