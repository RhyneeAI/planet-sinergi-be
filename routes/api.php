<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login', function () {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Please login first.',
            'code' => 403
        ], 403);
    })->name('login');

    
    // Protected
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::group(['middleware' => ['role:admin,owner']], function () {
            Route::apiResource('categories', CategoryController::class)->parameters([
                'categories' => 'category:uuid'
            ]);    
    
            Route::apiResource('units', UnitController::class)->parameters([
                'units' => 'unit:uuid'
            ]);    
            
            Route::apiResource('suppliers', SupplierController::class)->parameters([
                'suppliers' => 'supplier:uuid'
            ]);
        });
    });
});