<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Enregistrement de tous vos middlewares personnalisés
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,   // compat
            'app.role' => \App\Http\Middleware\CheckRole::class,
            'parent.child' => \App\Http\Middleware\CheckParentChild::class,
            'educateur.class' => \App\Http\Middleware\CheckEducateurClass::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();