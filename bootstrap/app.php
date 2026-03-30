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
    ->withMiddleware(function (Middleware $middleware): void {
        // Stateless API - no Sanctum, no CSRF
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure all exceptions return JSON for API routes
        $exceptions->render(function (\App\Exceptions\BankingException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code'  => $e->getErrorCode(),
            ], $e->getHttpStatus());
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error'  => 'Validation failed',
                    'code'   => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'Endpoint not found',
                    'code'  => 'NOT_FOUND',
                ], 404);
            }
        });
    })->create();
