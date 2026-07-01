<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsAttendanceReportRequest;
use App\Http\Requests\Absence\AbsEmployeeReportRequest;
use App\Http\Requests\Absence\AbsPayrollReportRequest;
use App\Http\Resources\Absence\AbsAttendanceResource;
use App\Http\Resources\Absence\AbsPayrollPeriodResource;
use App\Http\Resources\Absence\AbsReportBonusResource;
use App\Http\Resources\Absence\AbsReportDeductionResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Traits\DataTablesResponse;
use App\Services\Absence\AbsReportService;
use App\Services\ExportService;

class AbsReportController extends Controller
{
    use DataTablesResponse;

    public function __construct(
        protected AbsReportService $reportService,
        protected ExportService $exportService,
    ) {}

    public function attendance(AbsAttendanceReportRequest $request)
    {
        $query = $this->reportService->attendanceQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $cached = $this->exportService->resolveCache($request, 'attendance', $request->all(), 'xlsx', 'absence/attendance');
            if ($cached) {
                return response()->json([
                    'success' => true,
                    'message' => __('absence.reports.attendance_exported'),
                    'data' => $cached,
                ]);
            }

            $records = $query->get();
            $rows = $this->reportService->attendanceExportRows($records);
            $filename = 'attendance-' . now()->format('YmdHis') . '.xlsx';

            $queued = $this->exportService->enqueueOrFallback($request, 'xlsx', 'absence/attendance', $filename, [
                'headers' => ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Shift', 'Status', 'Jam Masuk', 'Jam Keluar', 'Alasan Terlambat', 'Alasan Pulang Awal'],
                'rows' => $rows->toArray(),
            ]);
            if ($queued) {
                return response()->json(['success' => true, 'data' => $queued]);
            }

            $export = $this->reportService->storeXlsxExport(
                $request,
                'absence/attendance',
                $filename,
                ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Shift', 'Status', 'Jam Masuk', 'Jam Keluar', 'Alasan Terlambat', 'Alasan Pulang Awal'],
                $rows,
            );

            $this->exportService->saveCacheAlias($request, 'attendance', $request->all(), 'xlsx', 'absence/attendance', "reports/absence/attendance/{$filename}");

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.attendance_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json($this->dataTablesResponse($request, $records, [
            'success' => true,
            'message' => __('absence.reports.attendance'),
            'data' => AbsAttendanceResource::collection($records),
        ]));
    }

    public function payroll(AbsPayrollReportRequest $request)
    {
        $query = $this->reportService->payrollQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $cached = $this->exportService->resolveCache($request, 'payroll', $request->all(), 'xlsx', 'absence/payroll');
            if ($cached) {
                return response()->json([
                    'success' => true,
                    'message' => __('absence.reports.payroll_exported'),
                    'data' => $cached,
                ]);
            }

            $records = $query->get();
            $rows = $this->reportService->payrollExportRows($records);
            $filename = 'payroll-' . now()->format('YmdHis') . '.xlsx';

            $queued = $this->exportService->enqueueOrFallback($request, 'xlsx', 'absence/payroll', $filename, [
                'headers' => ['No', 'Periode', 'Karyawan', 'Tarif Harian', 'Total Hari', 'Gaji Kotor', 'Total Bonus', 'Total Potongan', 'Gaji Bersih', 'Status'],
                'rows' => $rows->toArray(),
            ]);
            if ($queued) {
                return response()->json(['success' => true, 'data' => $queued]);
            }

            $export = $this->reportService->storeXlsxExport(
                $request,
                'absence/payroll',
                $filename,
                ['No', 'Periode', 'Karyawan', 'Tarif Harian', 'Total Hari', 'Gaji Kotor', 'Total Bonus', 'Total Potongan', 'Gaji Bersih', 'Status'],
                $rows,
            );

            $this->exportService->saveCacheAlias($request, 'payroll', $request->all(), 'xlsx', 'absence/payroll', "reports/absence/payroll/{$filename}");

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.payroll_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json($this->dataTablesResponse($request, $records, [
            'success' => true,
            'message' => __('absence.reports.payroll'),
            'data' => AbsPayrollPeriodResource::collection($records),
        ]));
    }

    public function deductions(AbsAttendanceReportRequest $request)
    {
        $query = $this->reportService->deductionsQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $cached = $this->exportService->resolveCache($request, 'deductions', $request->all(), 'xlsx', 'absence/deduction');
            if ($cached) {
                return response()->json([
                    'success' => true,
                    'message' => __('absence.reports.deductions_exported'),
                    'data' => $cached,
                ]);
            }

            $records = $query->get();
            $rows = $this->reportService->deductionsExportRows($records);
            $filename = 'deduction-' . now()->format('YmdHis') . '.xlsx';

            $queued = $this->exportService->enqueueOrFallback($request, 'xlsx', 'absence/deduction', $filename, [
                'headers' => ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Periode Payroll', 'Alasan', 'Nominal', 'Dibuat Oleh'],
                'rows' => $rows->toArray(),
            ]);
            if ($queued) {
                return response()->json(['success' => true, 'data' => $queued]);
            }

            $export = $this->reportService->storeXlsxExport(
                $request,
                'absence/deduction',
                $filename,
                ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Periode Payroll', 'Alasan', 'Nominal', 'Dibuat Oleh'],
                $rows,
            );

            $this->exportService->saveCacheAlias($request, 'deductions', $request->all(), 'xlsx', 'absence/deduction', "reports/absence/deduction/{$filename}");

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.deductions_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json($this->dataTablesResponse($request, $records, [
            'success' => true,
            'message' => __('absence.reports.deductions'),
            'data' => AbsReportDeductionResource::collection($records),
        ]));
    }

    public function bonuses(AbsAttendanceReportRequest $request)
    {
        $query = $this->reportService->bonusesQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $cached = $this->exportService->resolveCache($request, 'bonuses', $request->all(), 'xlsx', 'absence/bonus');
            if ($cached) {
                return response()->json([
                    'success' => true,
                    'message' => __('absence.reports.bonuses_exported'),
                    'data' => $cached,
                ]);
            }

            $records = $query->get();
            $rows = $this->reportService->bonusesExportRows($records);
            $filename = 'bonus-' . now()->format('YmdHis') . '.xlsx';

            $queued = $this->exportService->enqueueOrFallback($request, 'xlsx', 'absence/bonus', $filename, [
                'headers' => ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Periode Payroll', 'Alasan', 'Nominal', 'Dibuat Oleh'],
                'rows' => $rows->toArray(),
            ]);
            if ($queued) {
                return response()->json(['success' => true, 'data' => $queued]);
            }

            $export = $this->reportService->storeXlsxExport(
                $request,
                'absence/bonus',
                $filename,
                ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Periode Payroll', 'Alasan', 'Nominal', 'Dibuat Oleh'],
                $rows,
            );

            $this->exportService->saveCacheAlias($request, 'bonuses', $request->all(), 'xlsx', 'absence/bonus', "reports/absence/bonus/{$filename}");

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.bonuses_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json($this->dataTablesResponse($request, $records, [
            'success' => true,
            'message' => __('absence.reports.bonuses'),
            'data' => AbsReportBonusResource::collection($records),
        ]));
    }

    public function employees(AbsEmployeeReportRequest $request)
    {
        $query = $this->reportService->employeesQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $cached = $this->exportService->resolveCache($request, 'employees', $request->all(), 'xlsx', 'absence/employee');
            if ($cached) {
                return response()->json([
                    'success' => true,
                    'message' => __('absence.reports.employees_exported'),
                    'data' => $cached,
                ]);
            }

            $records = $query->get();
            $rows = $this->reportService->employeesExportRows($records);
            $filename = 'employee-' . now()->format('YmdHis') . '.xlsx';

            $queued = $this->exportService->enqueueOrFallback($request, 'xlsx', 'absence/employee', $filename, [
                'headers' => ['No', 'Karyawan', 'Nomor Telepon', 'Cabang', 'Jabatan', 'Status', 'Shift'],
                'rows' => $rows->toArray(),
            ]);
            if ($queued) {
                return response()->json(['success' => true, 'data' => $queued]);
            }

            $export = $this->reportService->storeXlsxExport(
                $request,
                'absence/employee',
                $filename,
                ['No', 'Karyawan', 'Nomor Telepon', 'Cabang', 'Jabatan', 'Status', 'Shift'],
                $rows,
            );

            $this->exportService->saveCacheAlias($request, 'employees', $request->all(), 'xlsx', 'absence/employee', "reports/absence/employee/{$filename}");

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.employees_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json($this->dataTablesResponse($request, $records, [
            'success' => true,
            'message' => __('absence.reports.employees'),
            'data' => EmployeeResource::collection($records),
        ]));
    }
}
