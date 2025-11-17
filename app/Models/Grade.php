<?php
// app/Models/Grade.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'enfant_id','classe_id','matiere_id','teacher_id',
        'school_year','term','grade','remark'
    ];

    public function enfant(){ return $this->belongsTo(Enfant::class); }
    public function matiere(){ return $this->belongsTo(Matiere::class); }
    public function classe(){ return $this->belongsTo(Classe::class); }
}
