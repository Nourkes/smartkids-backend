<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Supprimer les colonnes polymorphiques
            $table->dropColumn(['notifiable_type', 'notifiable_id', 'sender_type']);

            // Ajouter user_id
            $table->foreignId('user_id')->after('id')->constrained('users')->cascadeOnDelete();

            // Modifier sender_id pour être une FK vers users
            $table->foreignId('sender_id')->nullable()->change()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Restaurer les colonnes polymorphiques
            $table->string('notifiable_type')->after('id');
            $table->unsignedBigInteger('notifiable_id')->after('notifiable_type');
            $table->string('sender_type')->nullable()->after('sender_id');

            // Supprimer user_id
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');

            // Restaurer sender_id sans contrainte
            $table->dropForeign(['sender_id']);
            $table->unsignedBigInteger('sender_id')->nullable()->change();
        });
    }
};
