<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasNotifications;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, HasNotifications;
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
