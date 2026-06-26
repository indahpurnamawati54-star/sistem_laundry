<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Daftarkan middleware alias di sini
        $middleware->alias([
            'admin' => App\Http\Middleware\AdminMiddleware::class,
            'role' => App\Http\Middleware\CheckRole::class,
        ]);
        
        // Middleware group 'web' - PASTIKAN NAMESPACE BENAR
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,  // Namespace yang benar
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,  // Namespace yang benar
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();