<?php
// app/Policies/ParentPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\ParentModel;
use Illuminate\Auth\Access\HandlesAuthorization;

class ParentPolicy
{
    use HandlesAuthorization;

    /**
     * Voir la liste de tous les parents (Admin seulement)
     */
    public function view(User $user, ParentModel $parent)
    {
        // Admin peut voir tous les parents
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Un parent peut voir son propre profil
        return $user->parent && $user->parent->id === $parent->id;
    }
    
    public function viewAny(User $user)
    {
        // Seuls les admins peuvent voir la liste complète
        return $user->hasRole('admin');
    }

    /**
     * Créer un nouveau parent (Admin seulement)
     */
    public function create(User $user)
    {
        return $user->hasRole('admin');
    }

    /**
     * Modifier un parent (Admin ou le parent lui-même)
     */
    public function update(User $user, ParentModel $parent)
    {
        // Admin peut modifier tous les parents
        if ($user->hasRole('admin')) {
            return true;
        }

        // Parent peut modifier son propre profil
        return $user->parent && $user->parent->id === $parent->id;
    }

    /**
     * Supprimer un parent (Admin seulement)
     */
    public function delete(User $user, ParentModel $parent)
    {
        return $user->hasRole('admin');
    }

    /**
     * Changer le statut d'un parent (Admin seulement)
     */
    public function updateStatus(User $user, ParentModel $parent)
    {
        return $user->hasRole('admin');
    }

    /**
     * Voir les statistiques (Admin seulement)
     */
    public function viewStats(User $user)
    {
        return $user->hasRole('admin');
    }

    /**
     * Voir les notes d'un enfant (Admin ou parent de l'enfant)
     */
    public function viewChildNotes(User $user, ParentModel $parent)
    {
        // Admin peut voir toutes les notes
        if ($user->hasRole('admin')) {
            return true;
        }

        // Parent peut voir les notes de ses propres enfants
        return $user->parent && $user->parent->id === $parent->id;
    }
}