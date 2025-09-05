<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasNotifications;
class Educateur extends Model
{
    use HasFactory;
    use HasNotifications;
    protected $table = 'educateurs';

    protected $fillable = [
        'user_id',
        'diplome',
        'date_embauche',
        'salaire',
    ];

    protected $casts = [
        'date_embauche' => 'date',
        'salaire' => 'decimal:2',
    ];

    /**
     * Relation : l'éducateur hérite des données d’un utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation many-to-many avec les activités
     */
    public function activites()
    {
        return $this->belongsToMany(Activite::class, 'educateur_activite')
                    ->withTimestamps();
    }

    /**
     * Relation many-to-many avec les classes
     */
    public function classes()
    {
        return $this->belongsToMany(Classe::class, 'educateur_classe')
                    ->withTimestamps();
    }

    /**
     * Relation one-to-many : un éducateur fait plusieurs présences
     */
    public function presences()
    {
        return $this->hasMany(Presence::class);
    }
    // Dans app/Models/Educateur.php - ajouter cette méthode

public function notesAttribuees()
{
    return $this->hasMany(SuivieNote::class);
}
}
