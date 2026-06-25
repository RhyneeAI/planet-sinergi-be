<?php

use App\Http\Controllers\Api\Pos\PosCategoryController;
use App\Http\Controllers\Api\Pos\PosCustomerController;
use App\Http\Controllers\Api\Pos\PosCustomerTypeController;
use App\Http\Controllers\Api\Pos\PosMarketingController;
use App\Http\Controllers\Api\Pos\PosMarketingProductController;
use App\Http\Controllers\Api\Pos\PosProductController;
use App\Http\Controllers\Api\Pos\PosPurchaseInstallmentController;
use App\Http\Controllers\Api\Pos\PosPurchaseTransactionController;
use App\Http\Controllers\Api\Pos\PosReturnController;
use App\Http\Controllers\Api\Pos\PosSalesInstallmentController;
use App\Http\Controllers\Api\Pos\PosSalesTransactionController;
use App\Http\Controllers\Api\Pos\PosStockCardController;
use App\Http\Controllers\Api\Pos\PosStockMutationController;
use App\Http\Controllers\Api\Pos\PosSupplierController;
use App\Http\Controllers\Api\Pos\PosUnitController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // Generate-code harus sebelum apiResource agar tidak tertelan route {product}
    Route::get('products/generate-code', [PosProductController::class, 'generateCode'])
        ->middleware('role:SUPERADMIN,ADMIN,MANAGER_GUDANG');

    // =======================================================
    // MASTER DATA — READ (index & show)
    // =======================================================
    Route::group(['middleware' => ['role:SUPERADMIN,OWNER,ADMIN,MANAGER_GUDANG']], function () {
        Route::apiResource('categories', PosCategoryController::class)->parameters([
            'categories' => 'category:uuid',
        ])->only(['index', 'show']);

        Route::apiResource('units', PosUnitController::class)->parameters([
            'units' => 'unit:uuid',
        ])->only(['index', 'show']);

        Route::get('stock-card/{product:uuid}', [PosStockCardController::class, 'show']);

        Route::apiResource('products', PosProductController::class)->parameters([
            'products' => 'product:uuid',
        ])->only(['index', 'show']);

        Route::apiResource('suppliers', PosSupplierController::class)->parameters([
            'suppliers' => 'supplier:uuid',
        ])->only(['index', 'show']);

        Route::apiResource('customer-types', PosCustomerTypeController::class)->parameters([
            'customer-types' => 'customerType:uuid',
        ])->only(['index', 'show']);

        Route::apiResource('customers', PosCustomerController::class)->parameters([
            'customers' => 'customer:uuid',
        ])->only(['index', 'show']);

        Route::apiResource('marketings', PosMarketingController::class)->parameters([
            'marketings' => 'marketing:uuid',
        ])->only(['index', 'show']);

        Route::apiResource('marketing-products', PosMarketingProductController::class)->parameters([
            'marketing-products' => 'marketingProduct:uuid',
        ])->only(['index', 'show']);
    });

    // =======================================================
    // MASTER DATA — WRITE (store, update, destroy)
    // =======================================================
    Route::group(['middleware' => ['role:SUPERADMIN,ADMIN,MANAGER_GUDANG']], function () {
        Route::apiResource('categories', PosCategoryController::class)->parameters([
            'categories' => 'category:uuid',
        ])->except(['index', 'show']);

        Route::apiResource('units', PosUnitController::class)->parameters([
            'units' => 'unit:uuid',
        ])->except(['index', 'show']);

        Route::apiResource('products', PosProductController::class)->parameters([
            'products' => 'product:uuid',
        ])->except(['index', 'show']);

        Route::apiResource('suppliers', PosSupplierController::class)->parameters([
            'suppliers' => 'supplier:uuid',
        ])->except(['index', 'show']);

        Route::apiResource('customer-types', PosCustomerTypeController::class)->parameters([
            'customer-types' => 'customerType:uuid',
        ])->except(['index', 'show']);

        Route::apiResource('customers', PosCustomerController::class)->parameters([
            'customers' => 'customer:uuid',
        ])->except(['index', 'show']);

        Route::apiResource('marketings', PosMarketingController::class)->parameters([
            'marketings' => 'marketing:uuid',
        ])->except(['index', 'show']);

        Route::apiResource('marketing-products', PosMarketingProductController::class)->parameters([
            'marketing-products' => 'marketingProduct:uuid',
        ])->except(['index', 'show']);

        // Stock Mutations
        Route::prefix('stock-mutations')->group(function () {
            Route::post('/', [PosStockMutationController::class, 'store']);
            Route::get('/products', [PosStockMutationController::class, 'index']);
            Route::get('/products/{product:uuid}', [PosStockMutationController::class, 'show']);
        });
    });

    // =======================================================
    // SALES TRANSACTIONS, INSTALLMENTS & RETURNS
    // =======================================================
    Route::group(['middleware' => ['role:SUPERADMIN,OWNER,ADMIN,MANAGER_GUDANG,KASIR']], function () {
        Route::prefix('sales-transactions')->group(function () {
            Route::get('/', [PosSalesTransactionController::class, 'index']);
            Route::post('/', [PosSalesTransactionController::class, 'store']);
            Route::get('/{salesTransaction:ulid}', [PosSalesTransactionController::class, 'show']);
            Route::patch('/{salesTransaction:ulid}/cancel', [PosSalesTransactionController::class, 'cancel']);
        });

        Route::prefix('sales-installments')->group(function () {
            Route::get('/',                                 [PosSalesInstallmentController::class, 'index']);
            Route::get('/{salesInstallmentPlan:ulid}',      [PosSalesInstallmentController::class, 'show']);
            Route::post('/{salesInstallmentPlan:ulid}/pay', [PosSalesInstallmentController::class, 'pay']);
        });

        Route::prefix('returns')->group(function () {
            Route::get('/',  [PosReturnController::class, 'index']);
            Route::post('/', [PosReturnController::class, 'store']);
        });
    });

    // =======================================================
    // PURCHASE TRANSACTIONS & INSTALLMENTS (legacy, read-only)
    // =======================================================
    Route::group(['middleware' => ['role:SUPERADMIN,ADMIN,MANAGER_GUDANG']], function () {
        Route::prefix('purchase-transactions')->group(function () {
            Route::get('/', [PosPurchaseTransactionController::class, 'index']);
            Route::post('/', [PosPurchaseTransactionController::class, 'store']);
            Route::get('/{purchaseTransaction:ulid}', [PosPurchaseTransactionController::class, 'show']);
            Route::patch('/{purchaseTransaction:ulid}/cancel', [PosPurchaseTransactionController::class, 'cancel']);
        });

        Route::prefix('purchase-installments')->group(function () {
            Route::get('/',                                    [PosPurchaseInstallmentController::class, 'index']);
            Route::get('/{purchaseInstallmentPlan:ulid}',      [PosPurchaseInstallmentController::class, 'show']);
            Route::post('/{purchaseInstallmentPlan:ulid}/pay', [PosPurchaseInstallmentController::class, 'pay']);
        });
    });

    // =======================================================
    // REPORTS
    // =======================================================
    Route::group(['middleware' => ['role:SUPERADMIN,OWNER,ADMIN,MANAGER_GUDANG,MARKETING_LEAD,MARKETING']], function () {
        Route::prefix('reports')->group(function () {
            Route::get('/marketing-commission', [ReportController::class, 'marketingCommission']);
            Route::get('/sales-revenue',        [ReportController::class, 'salesRevenue']);
        });
    });
});
