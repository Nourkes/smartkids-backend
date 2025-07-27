<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activite extends Model
{
    use HasFactory;

    protected $table = 'activite'; // Nom explicite, non pluriel

    protected $fillable = [
        'nom',
        'description',
        'type',
        'date_activite',
        'heure_debut',
        'heure_fin',
        'prix', 
    ];

    protected $casts = [
        'date_activite' => 'date',
        'heure_debut' => 'datetime:H:i',
        'heure_fin' => 'datetime:H:i',
    ];

    // === Relations ===

    /**
     * Les éducateurs responsables de cette activité (Many-to-Many)
     */
    public function educateurs()
    {
        return $this->belongsToMany(Educateur::class, 'activite_educateur')
                    ->withTimestamps();
    }

    /**
     * Les enfants qui participent à cette activité (Many-to-Many)
     */
    public function enfants()
    {
        return $this->belongsToMany(Enfant::class, 'activite_enfant')
                    ->withTimestamps();
    }
}
