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
            Log::info('Creating parent user', ['email' => $data['email']]);

            $user = User::create([
                'name' => $data['nom'] . ' ' . $data['prenom'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'parent',
            ]);

            $parent = ParentModel::create([
                'user_id' => $user->id,
                'profession' => $data['profession'] ?? null,
                'telephone' => $data['telephone'],
                'adresse' => $data['adresse'] ?? null,
                'contact_urgence_nom' => $data['contact_urgence_nom'] ?? null,
                'contact_urgence_telephone' => $data['contact_urgence_telephone'] ?? null,
            ]);

            if (!empty($data['enfants']) && is_array($data['enfants'])) {
                $parent->enfants()->attach($data['enfants']);
            }

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
