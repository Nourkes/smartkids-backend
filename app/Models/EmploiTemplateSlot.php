<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmploiTemplateSlot extends Model
{
    protected $fillable = ['emploi_template_id','jour_semaine','debut','fin','matiere_id','educateur_id','salle_id','status'];

    public function template(){ return $this->belongsTo(EmploiTemplate::class, 'emploi_template_id'); }
    public function matiere(){ return $this->belongsTo(Matiere::class); }
    public function educateur(){ return $this->belongsTo(Educateur::class); }
    public function salle(){ return $this->belongsTo(Salle::class); }
}
