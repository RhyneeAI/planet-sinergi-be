<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerTypeController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\MarketingProductController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseTransactionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesTransactionController;
use App\Http\Controllers\Api\StockMutationController;
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
        
        // Dashboard/Home
        Route::get('/home', [HomeController::class, 'index']);

        Route::group(['middleware' => ['role:SUPERADMIN,OWNER']], function () {
            Route::apiResource('categories', CategoryController::class)->parameters([
                'categories' => 'category:uuid'
            ]);    
    
            Route::apiResource('units', UnitController::class)->parameters([
                'units' => 'unit:uuid'
            ]);    

            Route::apiResource('products', ProductController::class)->parameters([
                'products' => 'product:uuid'
            ]);
            
            Route::apiResource('suppliers', SupplierController::class)->parameters([
                'suppliers' => 'supplier:uuid'
            ]);

            Route::apiResource('customers', CustomerController::class)->parameters([
                'customers' => 'customer:uuid'
            ]);

            Route::apiResource('customer-types', CustomerTypeController::class)->parameters([
                'customer-types' => 'customerType:uuid'
            ]);

            Route::apiResource('marketings', MarketingController::class)->parameters([
                'marketings' => 'marketing:uuid'
            ]);

            Route::apiResource('marketing-products', MarketingProductController::class)->parameters([
                'marketing-products' => 'marketingProduct:uuid'
            ]);

            Route::prefix('stock-mutations')->group(function () {
                Route::post('/', [StockMutationController::class, 'store']);
                Route::get('/products', [StockMutationController::class, 'index']);
                Route::get('/products/{product:uuid}', [StockMutationController::class, 'show']);
            });
        });

        Route::group(['middleware' => ['role:SUPERADMIN,OWNER,MARKETING']], function () {
            Route::prefix('purchase-transactions')->group(function () {
                Route::get('/', [PurchaseTransactionController::class, 'index']);
                Route::post('/',[PurchaseTransactionController::class, 'store']);
                Route::get('/{purchaseTransaction:ulid}', [PurchaseTransactionController::class, 'show']);
                Route::patch('/{purchaseTransaction:ulid}/cancel', [PurchaseTransactionController::class, 'cancel']);
            });

            Route::prefix('sales-transactions')->group(function () {
                Route::get('/', [SalesTransactionController::class, 'index']);
                Route::post('/', [SalesTransactionController::class, 'store']);
                Route::get('/{salesTransaction:ulid}', [SalesTransactionController::class, 'show']);
                Route::patch('/{salesTransaction:ulid}/cancel', [SalesTransactionController::class, 'cancel']);
            });

        });
            
        Route::prefix('reports')->group(function () {
            Route::get('/marketing-commission', [ReportController::class, 'marketingCommission']);
            Route::get('/sales-revenue',        [ReportController::class, 'salesRevenue']);
        });
    });
});