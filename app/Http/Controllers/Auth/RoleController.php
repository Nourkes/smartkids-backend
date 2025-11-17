<?php
// app/Http/Controllers/Auth/RoleController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function changeRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:admin,educateur,parent'
        ]);

        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        return response()->json([
            'message' => "Rôle changé de {$oldRole} à {$request->role}",
            'user' => $user
        ]);
    }

    public function getUsersByRole($role)
    {
        if (!in_array($role, ['admin', 'educateur', 'parent'])) {
            return response()->json(['message' => 'Rôle invalide'], 400);
        }

        $users = User::where('role', $role)->with($role)->get();

        return response()->json([
            'role' => $role,
            'users' => $users
        ]);
    }

    public function getRoleStats()
    {
        $stats = [
            'admin' => User::where('role', 'admin')->count(),
            'educateur' => User::where('role', 'educateur')->count(),
            'parent' => User::where('role', 'parent')->count(),
            'total' => User::count()
        ];

        return response()->json($stats);
    }
}