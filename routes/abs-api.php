<?php

use App\Http\Controllers\Api\Absence\AbsAdminAttendanceController;
use App\Http\Controllers\Api\Absence\AbsDashboardController;
use App\Http\Controllers\Api\Absence\AbsEmployeeAttendanceController;
use App\Http\Controllers\Api\Absence\AbsEmployeeController;
use App\Http\Controllers\Api\Absence\AbsEmployeePayrollController;
use App\Http\Controllers\Api\Absence\AbsLoanController;
use App\Http\Controllers\Api\Absence\AbsOvertimeController;
use App\Http\Controllers\Api\Absence\AbsPayrollController;
use App\Http\Controllers\Api\Absence\AbsReportController;
use App\Http\Controllers\Api\Absence\AbsShiftController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/abs')->middleware(['throttle:api'])->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::middleware(['role:KARYAWAN'])->prefix('me')->group(function () {
            Route::get('/attendance/today', [AbsEmployeeAttendanceController::class, 'today']);
            Route::post('/attendance/check-in', [AbsEmployeeAttendanceController::class, 'checkIn']);
            Route::post('/attendance/check-out', [AbsEmployeeAttendanceController::class, 'checkOut']);
            Route::get('/attendances', [AbsEmployeeAttendanceController::class, 'history']);
            Route::get('/payroll', [AbsEmployeePayrollController::class, 'index']);
            Route::get('/payroll/{absPayrollPeriod:ulid}', [AbsEmployeePayrollController::class, 'show']);
            Route::get('/payroll/{absPayrollPeriod:ulid}/slip', [AbsEmployeePayrollController::class, 'slip']);
        });

        Route::middleware(['role:SUPERADMIN,OWNER,ADMIN,HRD'])->group(function () {
            Route::get('/dashboard', [AbsDashboardController::class, 'index']);

            Route::get('/shifts', [AbsShiftController::class, 'index']);
            Route::get('/shifts/{absShift:uuid}', [AbsShiftController::class, 'show']);

            Route::get('/attendances', [AbsAdminAttendanceController::class, 'index']);
            Route::get('/attendances/{absAttendance:ulid}', [AbsAdminAttendanceController::class, 'show']);

            Route::get('/payrolls', [AbsPayrollController::class, 'index']);
            Route::get('/payrolls/{absPayrollPeriod:ulid}', [AbsPayrollController::class, 'show']);

            Route::get('/reports/attendance', [AbsReportController::class, 'attendance']);
            Route::get('/reports/payroll', [AbsReportController::class, 'payroll']);
            Route::get('/reports/deductions', [AbsReportController::class, 'deductions']);
            Route::get('/reports/bonuses', [AbsReportController::class, 'bonuses']);
            Route::get('/reports/employees', [AbsReportController::class, 'employees']);

            Route::get('/employees', [AbsEmployeeController::class, 'index']);
            Route::get('/employees/{user:uuid}', [AbsEmployeeController::class, 'show']);

            Route::get('/overtimes', [AbsOvertimeController::class, 'index']);
            Route::get('/overtimes/{absOvertime}', [AbsOvertimeController::class, 'show']);

            Route::get('/loans', [AbsLoanController::class, 'index']);
            Route::get('/loans/{absLoan}', [AbsLoanController::class, 'show']);
        });

        Route::middleware(['role:SUPERADMIN,ADMIN'])->group(function () {
            Route::post('/shifts', [AbsShiftController::class, 'store']);
            Route::put('/shifts/{absShift:uuid}', [AbsShiftController::class, 'update']);
            Route::patch('/shifts/{absShift:uuid}', [AbsShiftController::class, 'update']);
            Route::delete('/shifts/{absShift:uuid}', [AbsShiftController::class, 'destroy']);

            Route::post('/payrolls/generate', [AbsPayrollController::class, 'generate']);
            Route::post('/payrolls/{absPayrollPeriod}/deductions', [AbsPayrollController::class, 'storeDeduction']);
            Route::put('/payrolls/{absPayrollPeriod}/deductions/{absDeduction}', [AbsPayrollController::class, 'updateDeduction']);
            Route::delete('/payrolls/{absPayrollPeriod}/deductions/{absDeduction}', [AbsPayrollController::class, 'destroyDeduction']);
            Route::post('/payrolls/{absPayrollPeriod}/bonuses', [AbsPayrollController::class, 'storeBonus']);
            Route::put('/payrolls/{absPayrollPeriod}/bonuses/{absBonus}', [AbsPayrollController::class, 'updateBonus']);
            Route::delete('/payrolls/{absPayrollPeriod}/bonuses/{absBonus}', [AbsPayrollController::class, 'destroyBonus']);
            Route::put('/payrolls/{absPayrollPeriod}/finalize', [AbsPayrollController::class, 'finalize']);
            Route::put('/payrolls/{absPayrollPeriod}/unlock', [AbsPayrollController::class, 'unlock']);
        });

        Route::middleware(['role:SUPERADMIN,ADMIN,HRD'])->group(function () {
            Route::post('/overtimes', [AbsOvertimeController::class, 'store']);
            Route::put('/overtimes/{absOvertime}/approve', [AbsOvertimeController::class, 'approve']);
            Route::put('/overtimes/{absOvertime}/reject', [AbsOvertimeController::class, 'reject']);

            Route::post('/loans', [AbsLoanController::class, 'store']);
            Route::put('/loans/{absLoan}/approve', [AbsLoanController::class, 'approve']);
            Route::put('/loans/{absLoan}/reject', [AbsLoanController::class, 'reject']);
        });
    });
});
