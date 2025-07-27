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
        'date_naissance',
        'classe_id',
        'allergies',
        'remarques_medicales',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    /**
     * Relation : un enfant appartient à une classe.
     */
    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Relation : un enfant peut avoir plusieurs parents (many-to-many).
     */
    public function parents()
    {
        return $this->belongsToMany(ParentModel::class, 'enfant_parent', 'enfant_id', 'parent_id')
                    ->withTimestamps();
    }

    /**
     * Relation : un enfant a plusieurs présences.
     */
    public function presences()
    {
        return $this->hasMany(Presence::class);
    }
}
