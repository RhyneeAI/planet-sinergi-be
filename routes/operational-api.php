<?php

use App\Http\Controllers\Api\Operational\OpsEditLogController;
use App\Http\Controllers\Api\Operational\OpsExpenseController;
use App\Http\Controllers\Api\Operational\OpsIncomeController;
use App\Http\Controllers\Api\Operational\OpsMandorController;
use App\Http\Controllers\Api\Operational\OpsNotificationController;
use App\Http\Controllers\Api\Operational\OpsTransferConfirmationController;
use App\Http\Controllers\Api\Operational\OpsWalletController;
use Illuminate\Support\Facades\Route;

// Route::prefix('v1/operational')->middleware(['throttle:api'])->group(function () {
Route::prefix('v1/operational')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::middleware(['role:SUPERADMIN,OWNER,ADMIN,MANDOR'])->group(function () {
            Route::prefix('notifications')->group(function () {
                Route::get('/', [OpsNotificationController::class, 'index']);
                Route::patch('/read-all', [OpsNotificationController::class, 'markAllAsRead']);
                Route::patch('/{opsNotification:uuid}/read', [OpsNotificationController::class, 'markAsRead']);
            });

            Route::get('/transfer-confirmations', [OpsTransferConfirmationController::class, 'index']);
            Route::get('/transfer-confirmations/{opsTransferConfirmation:uuid}', [OpsTransferConfirmationController::class, 'show']);
        });

        Route::group(['middleware' => ['role:SUPERADMIN,OWNER,ADMIN']], function () {
            Route::get('/mandors', [OpsMandorController::class, 'index']);

            Route::get('/incomes', [OpsIncomeController::class, 'index']);
            Route::post('/incomes', [OpsIncomeController::class, 'store']);
            Route::get('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'show']);
            Route::patch('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'update']);
            Route::delete('/incomes/{opsIncome:uuid}', [OpsIncomeController::class, 'destroy']);

            Route::get('/expenses', [OpsExpenseController::class, 'index']);
            Route::get('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'show']);

            Route::get('/edit-logs', [OpsEditLogController::class, 'index']);
        });

        Route::group(['middleware' => ['role:MANDOR']], function () {
            Route::get('/wallet', [OpsWalletController::class, 'show']);
            Route::get('/wallet/transactions', [OpsWalletController::class, 'transactions']);

            Route::post('/transfer-confirmations/{opsTransferConfirmation:uuid}/confirm', [OpsTransferConfirmationController::class, 'confirm']);
            Route::post('/transfer-confirmations/{opsTransferConfirmation:uuid}/reject', [OpsTransferConfirmationController::class, 'reject']);

            Route::get('/expenses', [OpsExpenseController::class, 'index']);
            Route::post('/expenses', [OpsExpenseController::class, 'store']);
            Route::get('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'show']);
            Route::patch('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'update']);
            Route::delete('/expenses/{opsExpense:uuid}', [OpsExpenseController::class, 'destroy']);
        });
    });
});
