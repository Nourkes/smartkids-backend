<?php 
// database/seeders/AuthSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use App\Models\Educateur;
use App\Models\ParentModel;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        // Créer un admin
        $adminUser = User::create([
            'name' => 'Administrateur Principal',
            'email' => 'admin@smartkids.tn',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        Admin::create([
            'user_id' => $adminUser->id,
            'poste' => 'Directeur',
        ]);

        // Créer un éducateur
        $educateurUser = User::create([
            'name' => 'Amina Ben Salem',
            'email' => 'amina@smartkids.tn',
            'password' => Hash::make('educateur123'),
            'role' => 'educateur',
            'email_verified_at' => now(),
        ]);

        Educateur::create([
            'user_id' => $educateurUser->id,
            'diplome' => 'Master en Psychologie de l\'Enfant',
            'date_embauche' => '2024-01-15',
            'salaire' => 1500.00,
        ]);

        // Créer un parent
        $parentUser = User::create([
            'name' => 'Mohamed Triki',
            'email' => 'mohamed@example.com',
            'password' => Hash::make('parent123'),
            'role' => 'parent',
            'email_verified_at' => now(),
        ]);

        ParentModel::create([
            'user_id' => $parentUser->id,
            'telephone' => '+216 98 123 456',
            'adresse' => '123 Rue de la République, Tunis',
            'profession' => 'Ingénieur',
            'contact_urgence_nom' => 'Fatma Triki',
            'contact_urgence_telephone' => '+216 97 654 321',
        ]);

        // Utilisateurs fictifs supplémentaires
        User::factory(3)->create(['role' => 'educateur']);
        User::factory(5)->create(['role' => 'parent']);
    }
}