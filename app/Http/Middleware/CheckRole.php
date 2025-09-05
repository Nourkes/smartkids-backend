<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;   // <-- important
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Log d'entrée : qui arrive et quels rôles sont requis
        Log::info('CheckRole: entering', [
            'uid'       => optional($request->user())->id,
            'user_role' => optional($request->user())->role,
            'required'  => $roles,
            'path'      => $request->path(),
            'method'    => $request->method(),
        ]);

        if (! $request->user()) {
            Log::warning('CheckRole: unauthenticated', [
                'required' => $roles,
                'path'     => $request->path(),
            ]);
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        $userRole = $request->user()->role;
        $hasRole  = $userRole && in_array($userRole, $roles, true);

        if (! $hasRole) {
            Log::warning('CheckRole: forbidden', [
                'uid'       => $request->user()->id,
                'user_role' => $userRole,
                'required'  => $roles,
            ]);
            return response()->json([
                'success'   => false,
                'message'   => 'Accès refusé. Rôle requis: ' . implode(' ou ', $roles),
                'user_role' => $userRole,
            ], 403);
        }

        Log::info('CheckRole: allowed', [
            'uid'       => $request->user()->id,
            'user_role' => $userRole,
        ]);

        return $next($request);
    }
}
