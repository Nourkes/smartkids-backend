<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // (Optionnel) créer un user de test
        // Assure-toi que ta factory met bien un mot de passe.
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Appeler tes seeders
        $this->call([
            AuthSeeder::class,
            // ... autres seeders si besoin
        ]);
    }
}
