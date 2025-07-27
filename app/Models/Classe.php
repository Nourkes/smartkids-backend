<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    use HasFactory;

    protected $table = 'classe'; // Laravel ne pourra pas deviner le nom car il n'est pas au pluriel anglais

    protected $fillable = [
        'nom',
        'niveau',
        'capacite_max',
        'description',
    ];

    // Relation many-to-many avec Educateur
    public function educateurs()
    {
        return $this->belongsToMany(Educateur::class, 'educateur_classe', 'classe_id', 'educateur_id')->withTimestamps();
    }
}