<?php

namespace App\Services;

use App\Models\User;
use App\Models\ParentModel;
use App\Models\Enfant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class ParentService
{
    
 public function createParent(array $data): ParentModel
    {
        return DB::transaction(function () use ($data) {
            // 1) Log d'intention
            Log::info('Creating parent user', ['email' => $data['email'] ?? null]);

            // 2) Générer un mot de passe provisoire fort
            $tempPassword = Str::random(12);

            // 3) Construire le nom d'affichage
            $displayName = trim(($data['nom'] ?? '') . ' ' . ($data['prenom'] ?? ''));
            if ($displayName === '') {
                // fallback si nom/prenom absents : utiliser "name" ou l'email
                $displayName = $data['name'] ?? ($data['email'] ?? 'Parent');
            }

            // 4) Créer l'utilisateur (role: parent)
            /** @var \App\Models\User $user */
            $user = User::create([
                'name'     => $displayName,
                'email'    => $data['email'],
                'role'     => 'parent',
                'password' => Hash::make($tempPassword),
            ]);

            // 5) Créer le ParentModel
            /** @var \App\Models\ParentModel $parent */
            $parent = ParentModel::create([
                'user_id'                   => $user->id,
                'profession'                => $data['profession']            ?? null,
                'telephone'                 => $data['telephone']             ?? null,
                'adresse'                   => $data['adresse']               ?? null,
                'contact_urgence_nom'       => $data['contact_urgence_nom']   ?? null,
                'contact_urgence_telephone' => $data['contact_urgence_telephone'] ?? null,
            ]);

            // 6) Lier les enfants si fournis
            if (!empty($data['enfants']) && is_array($data['enfants'])) {
                // On filtre pour ne garder que des IDs scalaires
                $ids = array_values(array_filter($data['enfants'], fn($v) => is_scalar($v)));
                if (!empty($ids)) {
                    $parent->enfants()->syncWithoutDetaching($ids);
                }
            }

            // 7) Forcer le changement de mot de passe au premier login (via Cache, pas DB)
            Cache::put("first_login:{$user->id}:force", true, now()->addDays(7));

            // 8) Envoyer l'email avec le mot de passe provisoire
            Mail::to($user->email)->send(
                new TempPasswordMail(name: $user->name, tempPassword: $tempPassword, email: $user->email)
            );

            Log::info('Parent user created successfully', ['user_id' => $user->id]);

            // 9) Retour avec relations utiles
            return $parent->load(['user', 'enfants']);
        });
    }

    public function updateParent(ParentModel $parent, array $data): ParentModel
    {
        return DB::transaction(function () use ($parent, $data) {
            $userData = [
                'name' => $data['nom'] . ' ' . $data['prenom'],
                'email' => $data['email'],
            ];
            if (!empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }
            $parent->user->update($userData);

            $parent->update([
                'profession' => $data['profession'] ?? $parent->profession,
                'telephone' => $data['telephone'],
                'adresse' => $data['adresse'] ?? $parent->adresse,
                'contact_urgence_nom' => $data['contact_urgence_nom'],
                'contact_urgence_telephone' => $data['contact_urgence_telephone'],
            ]);

            if (isset($data['enfants'])) {
                $parent->enfants()->sync($data['enfants']);
            }

            return $parent->fresh(['user', 'enfants']);
        });
    }

    public function deleteParent(ParentModel $parent): bool
    {
        return DB::transaction(function () use ($parent) {
            $parent->enfants()->detach();
            $parent->user->tokens()->delete();
            $parent->user->delete();
            return $parent->delete();
        });
    }

    public function getParentNotes(int $parentId, int $enfantId)
    {
        $parent = ParentModel::findOrFail($parentId);
        $enfant = $parent->enfants()->findOrFail($enfantId);
        return $enfant->notes()->with(['matiere', 'evaluation'])->get();
    }
public function search(array $filters): Builder
{
    $query = ParentModel::with(['user', 'enfants']);

    if (!empty($filters['search'])) {
        $search = $filters['search'];

        $query->whereHas('user', function ($q) use ($search) {
            $q->where(function ($subQuery) use ($search) {
            $q->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");

            });
        });
    }

    return $query;
}
public function searchParents(array $filters = []): Builder
    {
        $q = ParentModel::query()
            ->with(['user:id,name,email,created_at', 'enfants:id,nom,prenom,classe_id']);

        // statut (dans users)
        if (!empty($filters['statut'])) {
            $q->whereHas('user', fn($u) => $u->where('statut', $filters['statut']));
        }

        // recherche globale
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $q->where(function ($qq) use ($s) {
                $qq->where('nom', 'like', "%{$s}%")
                   ->orWhere('prenom', 'like', "%{$s}%")
                   ->orWhere('telephone', 'like', "%{$s}%")
                   ->orWhere('adresse', 'like', "%{$s}%")
                   ->orWhereHas('user', fn($u) => $u->where('email', 'like', "%{$s}%"));
            });
        }

        // profession
        if (!empty($filters['profession'])) {
            $q->where('profession', 'like', '%'.$filters['profession'].'%');
        }

        // has_children (true/false)
        if (isset($filters['has_children'])) {
            $bool = filter_var($filters['has_children'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === true) $q->has('enfants');
            elseif ($bool === false) $q->doesntHave('enfants');
        }

        // tri
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $dir    = strtolower($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        switch ($sortBy) {
            case 'nom':
            case 'prenom':
            case 'profession':
            case 'telephone':
                $q->orderBy($sortBy, $dir);
                break;

            case 'email':
                $q->orderBy(
                    User::select('email')->whereColumn('users.id', 'parents.user_id'),
                    $dir
                );
                break;

            case 'statut':
                $q->orderBy(
                    User::select('statut')->whereColumn('users.id', 'parents.user_id'),
                    $dir
                );
                break;

            default:
                $q->orderBy('created_at', $dir);
        }

        return $q;
    }


}
