<?php
// database/migrations/2025_01_01_000000_create_chat_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('chat_rooms', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('classe_id')->unique(); // 1 salon par classe
            $t->string('title')->nullable();
            $t->timestamps();
            $t->foreign('classe_id')->references('id')->on('classe')->cascadeOnDelete();
        });

        Schema::create('chat_participants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('room_id');
            $t->unsignedBigInteger('user_id'); // parents & éducateurs -> users table
            $t->string('role', 20)->nullable(); // 'parent' | 'educateur'
            $t->timestamp('last_read_at')->nullable();
            $t->timestamps();
            $t->unique(['room_id','user_id']);
            $t->foreign('room_id')->references('id')->on('chat_rooms')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('room_id');
            $t->unsignedBigInteger('user_id');        // auteur
            $t->text('body')->nullable();             // texte
            $t->string('type',20)->default('text');   // text|image|file|system
            $t->string('attachment_path')->nullable();// storage path
            $t->timestamps();
            $t->foreign('room_id')->references('id')->on('chat_rooms')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->index(['room_id','created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_rooms');
    }
};
