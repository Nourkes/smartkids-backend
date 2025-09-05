<?php
// app/Models/SuivieNote.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuivieNote extends Model
{
    use HasFactory;

    protected $table = 'suivie_note';

    protected $fillable = [
        'enfant_id',
        'matiere_id',
        'note',
        'type_evaluation',
        'date_evaluation',
        'trimestre',
        'annee_scolaire',
        'commentaire',
        'educateur_id',
    ];

    protected $casts = [
        'note' => 'decimal:2',
        'date_evaluation' => 'date',
    ];

    // Relations
    public function enfant()
    {
        return $this->belongsTo(Enfant::class);
    }

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function educateur()
    {
        return $this->belongsTo(Educateur::class);
    }

    // Méthodes utilitaires
    public function getNoteSur20Attribute()
    {
        return $this->note;
    }

    public function getNoteSur10Attribute()
    {
        return round($this->note / 2, 2);
    }

    public function getMentionAttribute()
    {
        if ($this->note >= 16) return 'Très Bien';
        if ($this->note >= 14) return 'Bien';
        if ($this->note >= 12) return 'Assez Bien';
        if ($this->note >= 10) return 'Passable';
        return 'Insuffisant';
    }
}