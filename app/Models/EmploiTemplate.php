<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmploiTemplate extends Model
{
    protected $fillable = ['classe_id','period_start','period_end','effective_from','status','version','generated_by'];
    public function classe(){ return $this->belongsTo(Classe::class, 'classe_id'); }
    public function slots(){ return $this->hasMany(EmploiTemplateSlot::class); }
}
