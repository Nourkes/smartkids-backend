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
use Illuminate\Support\Facades\Cache;
use App\Notifications\FirstLoginCodeMail;

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
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['success' => false, 'message' => 'Identifiants invalides'], 401);
    }

    /** @var \App\Models\User $user */
    $user = Auth::user();
    $token = $user->createToken('auth_token')->plainTextToken;

    if ($user->must_change_password) {
        Cache::put("first_login:{$user->id}:force", true, now()->addMinutes(30));
    }

    return response()->json([
        'success' => true,
        'message' => 'Connexion réussie',
        'token'   => $token,
        'token_type' => 'Bearer',
        'user' => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
            'must_change_password' => (bool)$user->must_change_password,
        ],
        'force_password_change' => (bool)$user->must_change_password,
    ], 200);
}

   public function sendFirstLoginCode(Request $request)
{
    $user = $request->user();

    if (!Cache::get("first_login:{$user->id}:force", false)) {
        return response()->json(['message' => 'Aucune vérification requise'], 400);
    }

    if (Cache::has("first_login:{$user->id}:cooldown")) {
        return response()->json(['message' => 'Veuillez patienter avant de renvoyer le code'], 429);
    }

    $channel     = $request->input('channel', 'email');        // 'email' ou 'sms'
    $destination = $request->input('destination');             // optionnel

    if ($channel === 'email' && empty($destination)) {
        $destination = $user->email;
    }

    $code = (string) random_int(100000, 999999);
    Cache::put("first_login:{$user->id}:code", $code, now()->addMinutes(10));
    Cache::put("first_login:{$user->id}:cooldown", true, now()->addSeconds(30));

    // envoi
    if ($channel === 'email') {
        $user->notify(new FirstLoginCodeMail($code, $destination)); 
    } else {
        return response()->json(['message' => 'SMS non configuré'], 501);
    }

    $masked = $destination
        ? preg_replace('/(?<=.).(?=[^@]*?@)/', '*', $destination) 
        : null;

    return response()->json(['message' => 'Code envoyé', 'to' => $masked]);
}
    public function verifyFirstLoginCode(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();

        $expected = Cache::get("first_login:{$user->id}:code");
        if (!$expected) {
            return response()->json(['message' => 'Code expiré ou inexistant'], 422);
        }
        if ($expected !== $request->code) {
            return response()->json(['message' => 'Code invalide'], 422);
        }

        Cache::put("first_login:{$user->id}:verified", true, now()->addMinutes(15));

        return response()->json(['message' => 'Code vérifié. Vous pouvez définir un nouveau mot de passe.']);
    }
    public function resetFirstLoginPassword(Request $request)
{
    $request->validate([
        'new_password' => 'required|string|min:8|confirmed' // => exige aussi new_password_confirmation
    ]);

    /** @var \App\Models\User $user */
    $user = $request->user();
    if (!Cache::get("first_login:{$user->id}:force", false)) {
        return response()->json(['message' => 'Aucun changement requis'], 400);
    }
    if (!Cache::get("first_login:{$user->id}:verified", false)) {
        return response()->json(['message' => 'Veuillez vérifier le code d’abord'], 422);
    }
    $user->password = Hash::make($request->new_password); 
    $user->must_change_password = false;
    $user->save();
    $user->refresh();
    \Log::info('Password reset OK', ['user_id' => $user->id, 'must_change_password' => $user->must_change_password]);
    Cache::forget("first_login:{$user->id}:force");
    Cache::forget("first_login:{$user->id}:code");
    Cache::forget("first_login:{$user->id}:verified");

    return response()->json(['message' => 'Mot de passe mis à jour.']);
}

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