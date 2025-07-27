<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relations avec les tables d'héritage
    public function educateur()
    {
        return $this->hasOne(Educateur::class);
    }

    public function admin()
    {
        return $this->hasOne(Admin::class);
    }

    public function parent()
    {
        return $this->hasOne(ParentModel::class);
    }

    // Méthodes utilitaires
    public function isEducateur()
    {
        return $this->role === 'educateur';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isParent()
    {
        return $this->role === 'parent';
    }
}