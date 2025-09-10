<?php

namespace App\Services;

use App\Models\User;
use App\Models\Educateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EducateurService
{
    public function getAllEducateurs($perPage = 15, $search = null)
    {
        $query = Educateur::with(['user', 'classes', 'activites']);
        
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        return $query->paginate($perPage);
    }

    /**
     * Créer un éducateur et son utilisateur associé
     */
    public function createEducateur(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1) Créer l'utilisateur SANS mot de passe
            $user = User::create([
                'name'  => $data['name'],
                'email' => $data['email'],
                'role'  => 'educateur',
                // Option A (recommandée) : password nullable en DB => on ne met rien
                // 'password' => null,
                //
                // Option B (si colonne non-nullable) : stocker une chaîne vide
                'password' => '',
            ]);

            // 2) Créer l’éducateur
            $educateur = Educateur::create([
                'user_id'       => $user->id,
                'diplome'       => $data['diplome'] ?? null,
                'date_embauche' => $data['date_embauche'] ?? null,
                'salaire'       => $data['salaire'] ?? 0,
            ]);

            return $educateur->load('user');
        });
    }


    public function updateEducateur(Educateur $educateur, array $data)
    {
        return DB::transaction(function () use ($educateur, $data) {
            // Mettre à jour les données utilisateur
            $userData = [];
            if (isset($data['name'])) $userData['name'] = $data['name'];
            if (isset($data['email'])) $userData['email'] = $data['email'];
            if (isset($data['password']) && $data['password']) {
                $userData['password'] = Hash::make($data['password']);
            }
            
            if (!empty($userData)) {
                $educateur->user()->update($userData);
            }

            // Mettre à jour les données éducateur
            $educateurData = [];
            if (isset($data['diplome'])) $educateurData['diplome'] = $data['diplome'];
            if (isset($data['date_embauche'])) $educateurData['date_embauche'] = $data['date_embauche'];
            if (isset($data['salaire'])) $educateurData['salaire'] = $data['salaire'];
            
            if (!empty($educateurData)) {
                $educateur->update($educateurData);
            }

            return $educateur->fresh('user');
        });
    }

    public function updateEducateurProfile(Educateur $educateur, array $data)
    {
        return DB::transaction(function () use ($educateur, $data) {
            $userData = [];
            if (isset($data['name'])) $userData['name'] = $data['name'];
            if (isset($data['email'])) $userData['email'] = $data['email'];
            if (isset($data['password']) && $data['password']) {
                $userData['password'] = Hash::make($data['password']);
            }
            
            if (!empty($userData)) {
                $educateur->user()->update($userData);
            }

            return $educateur->fresh('user');
        });
    }

    /**
     * Supprimer un éducateur et son utilisateur associé
     */
    public function deleteEducateur(Educateur $educateur)
    {
        return DB::transaction(function () use ($educateur) {
            $user = $educateur->user;

            if ($user) {
                $user->delete(); // Supprimer d'abord l'utilisateur
            }

            $educateur->delete(); // Puis l'éducateur

            return true;
        });
    }

    public function getEducateurById($id)
    {
        return Educateur::with(['user', 'classes', 'activites'])->findOrFail($id);
    }
}