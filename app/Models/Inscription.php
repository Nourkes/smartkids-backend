<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    protected $table = 'inscriptions';

    // ✅ NE METS QUE les colonnes qui existent (cf. capture phpMyAdmin)
    protected $fillable = [
        'form_id',
        'niveau_souhaite',
        'annee_scolaire',
        'date_inscription',
        'statut',                         // 'pending' | 'accepted' | 'rejected' | 'waiting'
        'nom_parent',
        'prenom_parent',
        'email_parent',
        'telephone_parent',
        'adresse_parent',
        'profession_parent',
        'nom_enfant',
        'prenom_enfant',
        'date_naissance_enfant',
        'genre_enfant',
        'problemes_sante',
        'allergies',
        'medicaments',
        'contact_urgence_nom',
        'contact_urgence_telephone',
        'classe_id',
        'parent_id',
        'position_attente',
        'documents_fournis',
        'remarques',
        'remarques_admin',
        'date_traitement',
        'traite_par_admin_id',
    ];

    protected $casts = [
        'date_inscription'        => 'datetime',
        'date_traitement'         => 'datetime',
        'date_naissance_enfant'   => 'date',

        // JSON
        'problemes_sante'         => 'array',
        'allergies'               => 'array',
        'medicaments'             => 'array',
        'documents_fournis'       => 'array',
        'position_attente'        => 'integer',
    ];

    /** Statuts */
    public const S_PENDING  = 'pending';
    public const S_ACCEPTED = 'accepted';
    public const S_REJECTED = 'rejected';
    public const S_WAITING  = 'waiting';

    /** Relations */
    public function form()        { return $this->belongsTo(InscriptionForm::class, 'form_id'); }
    public function classe()      { return $this->belongsTo(Classe::class, 'classe_id'); }
    public function parent()      { return $this->belongsTo(ParentModel::class, 'parent_id'); }
    public function adminTraitant(){ return $this->belongsTo(User::class, 'traite_par_admin_id'); }

    // Si tu as déjà la table paiements:
    public function paiements()   { return $this->hasMany(Paiement::class, 'inscription_id'); }
    public function paiementActif(){ return $this->hasOne(Paiement::class, 'inscription_id')->latestOfMany(); }

    /** Helpers “métier” */
    public function getEstPayeAttribute(): bool
    {
        $p = $this->paiementActif;
        return $p && in_array($p->statut, ['paye','confirmé','valide']); // adapte aux valeurs de ta table paiements
    }

    public function scopePourNiveau($q, $niveau)   { return $q->where('niveau_souhaite', $niveau); }
    public function scopeWaiting($q)               { return $q->where('statut', self::S_WAITING)->orderBy('position_attente'); }
}
