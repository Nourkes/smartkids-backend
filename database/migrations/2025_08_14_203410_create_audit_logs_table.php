<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action'); // create, update, delete, status_change
            $table->string('model'); // ParentModel, User, etc.
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('user_id'); // Qui a fait l'action
            $table->json('old_values')->nullable(); // Anciennes valeurs
            $table->json('new_values')->nullable(); // Nouvelles valeurs
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['model', 'model_id']);
            $table->index('user_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};