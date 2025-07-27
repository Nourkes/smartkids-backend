<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;

    // Nom de la table au pluriel comme défini dans la migration
    protected $table = 'admins';

    // Champs autorisés pour l'insertion ou la mise à jour
    protected $fillable = [
        'user_id',
        'poste',
    ];

    /**
     * Relation avec le modèle User (chaque admin est lié à un utilisateur)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
