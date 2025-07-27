<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentModel extends Model
{
    use HasFactory;

    protected $table = 'parent'; // Nom personnalisé car pas au pluriel

    protected $fillable = [
        'user_id',
        'telephone',
        'adresse',
        'profession',
        'contact_urgence_nom',
        'contact_urgence_telephone',
    ];

    // Relation avec le modèle User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation Many-to-Many avec Enfant
    public function enfants()
    {
        return $this->belongsToMany(Enfant::class, 'enfant_parent', 'parent_id', 'enfant_id')->withTimestamps();
    }
}