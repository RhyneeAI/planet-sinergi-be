<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/operational-api.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/abs-api.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/pos-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'telescope.auth' => \App\Http\Middleware\TelescopeAuth::class,
        ]);

        // Apply TelescopeAuth middleware globally ke semua requests
        // Middleware ini akan smart check untuk /telescope requests saja
        $middleware->web(append: [
            \App\Http\Middleware\TelescopeAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'Unauthenticated, Please login' : $e->getMessage(),
                'code' => 401
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'Unauthorized.' : $e->getMessage()    ,
                'code' => 403
            ], 403);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'Not found.' : $e->getMessage(),
                'code' => 404
            ], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'Method not allowed.' : $e->getMessage(),
                'code' => 405
            ], 405);
        });

        $exceptions->render(function (ConflictHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'Conflict.' : $e->getMessage(),
                'code' => 409
            ], 409);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'Validation error.' : $e->getMessage(),
                'errors' => $e->errors(),
                'code' => 422
            ], 422);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction() ? 'Too many requests. Please slow down.' : $e->getMessage(),
                'code' => 429
            ], 429);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
		    \Log::error('Telescope error: ' . $e->getMessage(), [
		        'file' => $e->getFile(),
		        'line' => $e->getLine(),
		        'trace' => $e->getTraceAsString()
		    ]);
		    
		    return response()->json([
		        'success' => false,
		        'message' => app()->isProduction() ? 'Internal Server Error.' : $e->getMessage(),
		        'code' => 500
		    ], 500);
		});
    })->create();