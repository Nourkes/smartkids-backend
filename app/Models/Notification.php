<?php
// app/Models/Notification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',          // Destinataire (remplace notifiable_type/id)
        'sender_id',        // Expéditeur (User qui envoie)
        'type',
        'titre',
        'message',
        'data',
        'priorite',
        'canal',
        'lu',
        'lu_at',
        'archive',
        'archive_at',
        'envoye_at',
        'planifie_pour',
    ];

    protected $casts = [
        'data' => 'array',
        'lu' => 'boolean',
        'archive' => 'boolean',
        'lu_at' => 'datetime',
        'archive_at' => 'datetime',
        'envoye_at' => 'datetime',
        'planifie_pour' => 'datetime',
    ];

    // Relations simples avec User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Scopes pour filtrer les notifications
    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }

    public function scopeNonArchivees($query)
    {
        return $query->where('archive', false);
    }

    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeParPriorite($query, $priorite)
    {
        return $query->where('priorite', $priorite);
    }

    public function scopeEnvoyees($query)
    {
        return $query->whereNotNull('envoye_at');
    }

    public function scopePlanifiees($query)
    {
        return $query->whereNotNull('planifie_pour')
            ->where('planifie_pour', '>', now());
    }

    // Méthodes utilitaires
    public function marquerCommeLue()
    {
        $this->update([
            'lu' => true,
            'lu_at' => now()
        ]);
    }

    public function archiver()
    {
        $this->update([
            'archive' => true,
            'archive_at' => now()
        ]);
    }

    public function marquerCommeEnvoyee()
    {
        $this->update([
            'envoye_at' => now()
        ]);
    }

    // Attributs calculés
    public function getEstUrgente()
    {
        return $this->priorite === 'urgente';
    }

    public function getEstRecente()
    {
        return $this->created_at->diffInHours() < 24;
    }
}