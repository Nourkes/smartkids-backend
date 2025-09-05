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
        'nom_menu',
        'date_menu',
        'type_repas',
        'plat_principal',
        'accompagnement',
        'dessert',
        'boisson',
        'ingredients',
        'informations_nutritionnelles',
        'prix',
        'actif',
    ];

    protected $casts = [
        'date_menu' => 'date',
        'prix' => 'decimal:2',
        'actif' => 'boolean',
    ];
}