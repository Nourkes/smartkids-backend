<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Salle extends Model
{
    protected $fillable = ['code','nom','capacite','is_active'];

    public function classes() { return $this->hasMany(Classe::class, 'salle_id'); }
    public function slots()   { return $this->hasMany(EmploiTemplateSlot::class, 'salle_id'); }
}
