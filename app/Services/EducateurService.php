<?php

namespace App\Services;

use App\Models\User;
use App\Models\Educateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
// ➜ AJOUTE CECI :
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// (si tu envoies un Mailable)
use App\Mail\TempPasswordMail;
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
        // 1) Générer un mot de passe provisoire
        $tempPassword = Str::random(12);

        // 2) Créer l'utilisateur avec ce MDP
        $user = User::create([
            'name'                  => $data['name'],
            'email'                 => $data['email'],
            'role'                  => 'educateur',
            'password'              => Hash::make($tempPassword),
            'must_change_password'  => true, // ⬅️ important (si colonne en DB)
        ]);

        // 3) Forcer le changement au premier login (flag de workflow)
        Cache::put("first_login:{$user->id}:force", true, now()->addDays(7));

        // 4) Créer l’éducateur
        $educateur = Educateur::create([
            'user_id'       => $user->id,
            'diplome'       => $data['diplome'] ?? null,
            'date_embauche' => $data['date_embauche'] ?? null,
            'salaire'       => $data['salaire'] ?? 0,
        ]);

        // 5) Envoyer le mot de passe provisoire par email (sans casser la transac)
        try {
            // Version Mailable (recommandée)
            Mail::to($user->email)->send(
                new TempPasswordMail(
                    name: $user->name,
                    email: $user->email,
                    tempPassword: $tempPassword
                )
            );

        } catch (\Throwable $e) {
            \Log::warning('Envoi mail (temp password) échoué: '.$e->getMessage());
        }

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