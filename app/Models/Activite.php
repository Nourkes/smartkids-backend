<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activite extends Model
{
    use HasFactory;

    // ⚠️ Garde ceci uniquement si ta table s’appelle vraiment "activite"
    // (si ta table est "activites", supprime cette ligne)
    protected $table = 'activite';
    public const TYPES = ['musique','peinture','sport','lecture','sortie','autre'];
    protected $fillable = [
        'nom',
        'description',
        'type',
        'date_activite',
        'heure_debut',
        'heure_fin',
        'prix',
        'image'
    ];

    protected $casts = [
        'date_activite' => 'date',
        'prix'          => 'float',
    ];

    // Pour exposer directement ces compteurs en JSON (optional)
    protected $appends = [
        'nombre_participants',
        'nombre_presents',
        'image_url'
    ];
    public function getImageUrlAttribute() {
        return $this->image ? asset('storage/'.$this->image) : null;
    }
    

    /* ===================== Relations ===================== */

    // Many-to-many avec Educateur via pivot "educateur_activite"
    public function educateurs()
    {
        return $this->belongsToMany(Educateur::class, 'educateur_activite', 'activite_id', 'educateur_id')
                    ->withTimestamps();
    }

    // Many-to-many avec Enfant via pivot "participation_activite"
    // + colonnes pivot supplémentaires
    public function enfants()
    {
        return $this->belongsToMany(Enfant::class, 'participation_activite', 'activite_id', 'enfant_id')
                    ->withPivot('statut_participation', 'remarques', 'note_evaluation')
                    ->withTimestamps();
    }

    // Si tu as un modèle pour la table pivot (ex: ParticipationActivite)
    public function participationsActivite()
    {
        return $this->hasMany(ParticipationActivite::class, 'activite_id');
    }

    /* ================== Helpers / Scopes ================= */

    public function enfantsInscrits()
    {
        return $this->enfants()->wherePivot('statut_participation', 'inscrit');
    }

    public function enfantsPresents()
    {
        return $this->enfants()->wherePivot('statut_participation', 'present');
    }

    /* ============== Attributs calculés (accessors) ============== */

    public function getNombreParticipantsAttribute(): int
    {
        return $this->enfants()->count();
    }

    public function getNombrePresentsAttribute(): int
    {
        return $this->enfants()->wherePivot('statut_participation', 'present')->count();
    }
}
