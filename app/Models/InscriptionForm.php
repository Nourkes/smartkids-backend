<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InscriptionForm extends Model
{
    protected $table = 'inscription_forms';

    protected $fillable = ['payload'];

    protected $casts = [
        'payload' => 'array',
    ];

    public function inscription()
    {
        return $this->hasOne(Inscription::class, 'form_id');
    }
}
