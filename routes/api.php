<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubCompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:api'])->group(function () {
    // Public
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login', function () {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Please login first.',
            'code' => 403
        ], 403);
    })->name('login');

    Route::prefix('forgot-password')->group(function () {
        Route::post('/verify', [AuthController::class, 'forgotPasswordVerify']);
        Route::post('/reset',  [AuthController::class, 'forgotPasswordReset'])->middleware('auth:sanctum');
    });
    
    // Protected
    Route::middleware(['auth:sanctum'])->group(function () {
        // Auth
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
        
        Route::get('/profile',         [ProfileController::class, 'show']);
        Route::patch('/profile',       [ProfileController::class, 'update']);

        Route::middleware(['role:SUPERADMIN,OWNER,ADMIN,MANDOR,KEPALA_MANDOR'])->group(function () {
            Route::apiResource('sub-companies', SubCompanyController::class)
                ->parameters(['sub-companies' => 'uuid'])
                ->only(['index', 'show']);
        });

        Route::middleware(['role:SUPERADMIN,OWNER,ADMIN'])->group(function () {
            Route::apiResource('sub-companies', SubCompanyController::class)
                ->parameters(['sub-companies' => 'uuid'])
                ->only(['store', 'update', 'destroy']);

            Route::post('sub-companies/{uuid}/restore', [SubCompanyController::class, 'restore']);
        });

        // Employees — cross-module user management
        Route::middleware(['role:SUPERADMIN,OWNER,ADMIN,HRD'])->group(function () {
            Route::apiResource('employees', EmployeeController::class)
                ->parameters(['employees' => 'user:uuid'])
                ->only(['index', 'show']);
        });

        Route::middleware(['role:SUPERADMIN,ADMIN,HRD'])->group(function () {
            Route::apiResource('employees', EmployeeController::class)
                ->parameters(['employees' => 'user:uuid'])
                ->only(['store', 'update', 'destroy']);
            Route::put('employees/{user:uuid}/reset-password', [EmployeeController::class, 'resetPassword']);
            Route::put('employees/{user:uuid}/toggle-active', [EmployeeController::class, 'toggleActive']);
        });

        Route::get('/exports/{token}', [ExportController::class, 'status'])->name('exports.status');

        // Positions — cross-module job positions
        Route::middleware(['role:SUPERADMIN,OWNER,ADMIN,HRD'])->group(function () {
            Route::apiResource('positions', PositionController::class)
                ->parameters(['positions' => 'position:uuid'])
                ->only(['index', 'show']);
        });

        Route::middleware(['role:SUPERADMIN,ADMIN,HRD'])->group(function () {
            Route::apiResource('positions', PositionController::class)
                ->parameters(['positions' => 'position:uuid'])
                ->only(['store', 'update', 'destroy']);
        });
    });
});