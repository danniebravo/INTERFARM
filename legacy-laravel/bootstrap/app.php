<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/wompi',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminAccess::class,
            'client.not_suspended' => \App\Http\Middleware\EnsureClientIsNotSuspended::class,
            'track.client.activity' => \App\Http\Middleware\TrackClientActivity::class,
            'track.admin.activity' => \App\Http\Middleware\TrackAdminActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu sesión expiró. Actualiza la página e intenta nuevamente.',
                ], 419);
            }

            return redirect()
                ->guest(route('login'))
                ->with('status', 'Tu sesión expiró por seguridad. Inicia sesión nuevamente para continuar.');
        });
    })->create();
