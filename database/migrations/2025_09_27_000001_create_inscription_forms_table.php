<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inscription_forms', function (Blueprint $table) {
            $table->id();
            // soit tu mets tout en JSON...
            $table->json('payload');
            // ...soit tu ajoutes des colonnes à la demande (email_parent, telephone_parent, etc.)
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('inscription_forms');
    }
};
