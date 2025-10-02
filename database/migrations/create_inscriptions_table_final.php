<?php
// ================================================================================
// 1. MIGRATION POUR LA TABLE INSCRIPTIONS MISE À JOUR
// database/migrations/2024_01_20_000001_create_inscriptions_table_final.php
// ================================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Désactiver les contraintes FK avant suppression
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('inscriptions');
        Schema::enableForeignKeyConstraints();

        // Créer la nouvelle table avec la structure complète
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            
            // Informations de base de l'inscription
            $table->string('annee_scolaire'); // Ex: "2024-2025"
            $table->date('date_inscription');
            $table->enum('statut', ['pending', 'accepted', 'rejected', 'waiting'])->default('pending');
            
            // Informations temporaires du parent (avant création du compte)
            $table->string('nom_parent');
            $table->string('prenom_parent');
            $table->string('email_parent');
            $table->string('telephone_parent');
            $table->text('adresse_parent')->nullable();
            $table->string('profession_parent')->nullable();
            
            // Informations temporaires de l'enfant (avant création)
            $table->string('nom_enfant');
            $table->string('prenom_enfant');
            $table->date('date_naissance_enfant');
            $table->enum('genre_enfant', ['M', 'F'])->nullable();
            $table->json('problemes_sante')->nullable();
            $table->json('allergies')->nullable();
            $table->json('medicaments')->nullable();
            $table->string('contact_urgence_nom')->nullable();
            $table->string('contact_urgence_telephone')->nullable();
            
            // Classe demandée
            $table->foreignId('classe_id')->constrained('classe')->onDelete('cascade');
            
            // Références après acceptation (null au début)
            $table->foreignId('enfant_id')->nullable()->constrained('enfant')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('parents')->onDelete('cascade');
            
            // Gestion administrative
            $table->integer('position_attente')->nullable(); // Position dans la liste d'attente
            $table->decimal('frais_inscription', 10, 2)->nullable();
            $table->decimal('frais_mensuel', 10, 2)->nullable();
            $table->json('documents_fournis')->nullable();
            $table->text('remarques')->nullable();
            $table->text('remarques_admin')->nullable(); // Remarques de l'admin
            
            // Traitement par l'admin
            $table->timestamp('date_traitement')->nullable();
            $table->foreignId('traite_par_admin_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index(['classe_id', 'statut']);
            $table->index(['statut', 'position_attente']);
            $table->index(['annee_scolaire']);
            $table->index(['email_parent']);
        });
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('inscriptions');
        Schema::enableForeignKeyConstraints();
    }
};