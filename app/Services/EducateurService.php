<?php

namespace App\Services;

use App\Mail\TempPasswordMail;
use App\Models\Educateur;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EducateurService
{
    
    public function getAllEducateurs(int $perPage = 15, ?string $search = null)
    {
        $q = Educateur::with(['user', 'classes', 'activites']);

        if ($search && trim($search) !== '') {
            $q->whereHas('user', function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $q->paginate($perPage);
    }


    public function createEducateur(array $data): Educateur
    {
        return DB::transaction(function () use ($data) {
            // 1) MDP provisoire
            $tempPassword = Str::random(12);

            // 2) Créer l'utilisateur
            $user = User::create([
                'name'                 => $data['name'],
                'email'                => $data['email'],
                'role'                 => 'educateur',
                'password'             => Hash::make($tempPassword),
                'must_change_password' => true, // nécessite la colonne en DB
            ]);

            Cache::put("first_login:{$user->id}:force", true, now()->addDays(7));

            // 4) Photo éventuelle
            $photoPath = null;
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $photoPath = $data['photo']->store('educateurs', 'public'); 
            }

            // 5) Créer l’éducateur
            $educateur = Educateur::create([
                'user_id'       => $user->id,
                'diplome'       => $data['diplome']       ?? null,
                'date_embauche' => $data['date_embauche'] ?? null,
                'salaire'       => $data['salaire']       ?? 0,
                'photo'         => $photoPath,
                'telephone'     => $data['telephone'] ?? null,   

            ]);

            // 6) Envoyer l’email (on ne casse pas la transaction si le mail échoue)
            try {
                Mail::to($user->email)->send(
                    new TempPasswordMail(
                        name: $user->name,
                        email: $user->email,
                        tempPassword: $tempPassword
                    )
                );
            } catch (\Throwable $e) {
                Log::warning('Envoi mail mot de passe provisoire échoué: '.$e->getMessage());
            }

            return $educateur->load('user');
        });
    }


    public function updateEducateur(Educateur $educateur, array $data): Educateur
    {
        return DB::transaction(function () use ($educateur, $data) {

            // --- USER ---
            $userData = [];
            if (array_key_exists('name', $data))     $userData['name']     = $data['name'];
            if (array_key_exists('email', $data))    $userData['email']    = $data['email'];
            if (!empty($data['password']))           $userData['password'] = Hash::make($data['password']);

            if (!empty($userData)) {
                $educateur->user()->update($userData);
            }
            if (array_key_exists('telephone', $data))     $eduData['telephone'] = $data['telephone']; // ✅


            // --- PHOTO ---
            $educateurData = [];
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                // supprimer l’ancienne si présente
                if (!empty($educateur->photo) && Storage::disk('public')->exists($educateur->photo)) {
                    Storage::disk('public')->delete($educateur->photo);
                }
                $educateurData['photo'] = $data['photo']->store('educateurs', 'public');
            }

            if (array_key_exists('diplome', $data))        $educateurData['diplome']       = $data['diplome'];
            if (array_key_exists('date_embauche', $data))  $educateurData['date_embauche'] = $data['date_embauche'];
            if (array_key_exists('salaire', $data))        $educateurData['salaire']       = $data['salaire'];

            if (!empty($educateurData)) {
                $educateur->update($educateurData);
            }

            return $educateur->fresh('user');
        });
    }



    public function updateEducateurProfile(Educateur $educateur, array $data): Educateur
    {
        return DB::transaction(function () use ($educateur, $data) {
            $userData = [];
            if (array_key_exists('name', $data))     $userData['name']     = $data['name'];
            if (array_key_exists('email', $data))    $userData['email']    = $data['email'];
            if (!empty($data['password']))           $userData['password'] = Hash::make($data['password']);

            if (!empty($userData)) {
                $educateur->user()->update($userData);
            }

            return $educateur->fresh('user');
        });
    }

   
    public function deleteEducateur(Educateur $educateur): bool
    {
        return DB::transaction(function () use ($educateur) {
            // supprimer le fichier photo si présent
            if (!empty($educateur->photo) && Storage::disk('public')->exists($educateur->photo)) {
                Storage::disk('public')->delete($educateur->photo);
            }

            // supprimer le user d’abord (FK)
            if ($educateur->user) {
                $educateur->user->delete();
            }

            $educateur->delete();
            return true;
        });
    }


    public function getEducateurById(int|string $id): Educateur
    {
        return Educateur::with(['user', 'classes', 'activites'])->findOrFail($id);
    }
}
