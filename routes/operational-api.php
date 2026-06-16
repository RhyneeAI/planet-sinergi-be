<?php

use App\Http\Controllers\Api\Operational\OpsDashboardController;
use App\Http\Controllers\Api\Operational\OpsEditLogController;
use App\Http\Controllers\Api\Operational\OpsExpenseController;
use App\Http\Controllers\Api\Operational\OpsIncomeController;
use App\Http\Controllers\Api\Operational\OpsMandorController;
use App\Http\Controllers\Api\Operational\OpsNotificationController;
use App\Http\Controllers\Api\Operational\OpsTransferConfirmationController;
use App\Http\Controllers\Api\Operational\OpsWalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/operational')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::middleware(['role:SUPERADMIN,OWNER,ADMIN,MANDOR'])->group(function () {
            Route::get('mandor/dashboard-data', [OpsDashboardController::class, 'index']);

            Route::prefix('notifications')->group(function () {
                Route::get('/', [OpsNotificationController::class, 'index']);
                Route::patch('/read-all', [OpsNotificationController::class, 'markAllAsRead']);
                Route::patch('/{opsNotification:uuid}/read', [OpsNotificationController::class, 'markAsRead']);
            });

            Route::get('/transfer-confirmations', [OpsTransferConfirmationController::class, 'index']);
            Route::get('/transfer-confirmations/{opsTransferConfirmation:uuid}', [OpsTransferConfirmationController::class, 'show']);
        });

        Route::prefix('admin')->middleware(['role:SUPERADMIN,OWNER,ADMIN'])->group(function () {
            Route::get('dashboard-data', [OpsDashboardController::class, 'index']);

            Route::get('/mandors', [OpsMandorController::class, 'index']);
            Route::post('/mandors', [OpsMandorController::class, 'store']);

            Route::get('/incomes', [OpsIncomeController::class, 'adminIndex']);
            Route::post('/incomes', [OpsIncomeController::class, 'adminStore']);
            Route::get('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'adminShow']);
            Route::patch('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'adminUpdate']);
            Route::delete('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'adminDestroy']);

            Route::get('/expenses', [OpsExpenseController::class, 'adminIndex']);
            Route::get('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'adminShow']);

            Route::get('/edit-logs', [OpsEditLogController::class, 'index']);
        });

        Route::prefix('mandor')->middleware(['role:MANDOR'])->group(function () {
            Route::get('dashboard-data', [OpsDashboardController::class, 'index']);

            Route::get('/incomes', [OpsIncomeController::class, 'mandorIndex']);
            Route::post('/incomes', [OpsIncomeController::class, 'mandorStore']);
            Route::get('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'mandorShow']);
            Route::patch('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'mandorUpdate']);
            Route::delete('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'mandorDestroy']);

            Route::get('/expenses', [OpsExpenseController::class, 'mandorIndex']);
            Route::post('/expenses', [OpsExpenseController::class, 'mandorStore']);
            Route::get('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'mandorShow']);
            Route::patch('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'mandorUpdate']);
            Route::delete('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'mandorDestroy']);

            Route::get('/wallet', [OpsWalletController::class, 'show']);
            Route::get('/wallet/transactions', [OpsWalletController::class, 'transactions']);

            Route::post('/transfer-confirmations/{opsTransferConfirmation:uuid}/confirm', [OpsTransferConfirmationController::class, 'confirm']);
            Route::post('/transfer-confirmations/{opsTransferConfirmation:uuid}/reject', [OpsTransferConfirmationController::class, 'reject']);
        });
    });
});
