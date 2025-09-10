<?php
// app/Models/Menu.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'menu';

    protected $fillable = [
        'date_menu',
        'type_repas',
        'description',
        'ingredients',
    ];

    protected $casts = [
        'date_menu' => 'date:Y-m-d',
    ];
}