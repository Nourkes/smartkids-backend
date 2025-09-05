<?php
// app/Http/Controllers/Auth/AuthController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,educateur,parent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }

public function login(Request $request)
{
    // Validation
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Vérifie si les informations sont valides
    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json([
            'success' => false,
            'message' => 'Identifiants invalides'
        ], 401);
    }

    $user = Auth::user();
    $token = $user->createToken('auth_token')->plainTextToken;

    // Réponse de succès sans champs manquants
    return response()->json([
        'success' => true,
        'message' => 'Connexion réussie',
        'token' => $token,
        'token_type' => 'Bearer',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role, // présent dans ton modèle
        ]
    ], 200);
}

    /**
     * Déconnexion utilisateur
     */

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Déconnexion de tous les appareils réussie'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $profil = $user->getProfil();

        return response()->json([
            'user' => $user,
            'profil' => $profil
        ]);
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();
        
        // Supprimer le token actuel
        $request->user()->currentAccessToken()->delete();
        
        // Créer un nouveau token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Token rafraîchi avec succès',
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}