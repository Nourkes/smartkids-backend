<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            
            // Relations polymorphiques pour l'expéditeur et le destinataire
            $table->string('notifiable_type'); // App\Models\ParentModel, App\Models\Educateur, App\Models\Admin
            $table->unsignedBigInteger('notifiable_id'); // ID du destinataire
            
            // Expéditeur (optionnel, peut être système)
            $table->string('sender_type')->nullable(); // Type de l'expéditeur
            $table->unsignedBigInteger('sender_id')->nullable(); // ID de l'expéditeur
            
            // Contenu de la notification
            $table->string('type'); // rappel_paiement, activite_recente, etat_sante, etc.
            $table->string('titre');
            $table->text('message');
            $table->json('data')->nullable(); // Données supplémentaires (IDs, URLs, etc.)
            
            // Gestion des états
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->enum('canal', ['app', 'email', 'sms', 'push'])->default('app');
            $table->boolean('lu')->default(false);
            $table->timestamp('lu_at')->nullable();
            $table->boolean('archive')->default(false);
            $table->timestamp('archive_at')->nullable();
            
            // Planification (pour notifications différées)
            $table->timestamp('envoye_at')->nullable();
            $table->timestamp('planifie_pour')->nullable();
            
            $table->timestamps();
            
            // Index pour optimiser les performances
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['type', 'created_at']);
            $table->index(['lu', 'archive']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};