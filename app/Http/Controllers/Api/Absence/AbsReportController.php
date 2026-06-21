<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsAttendanceReportRequest;
use App\Http\Requests\Absence\AbsEmployeeReportRequest;
use App\Http\Requests\Absence\AbsPayrollReportRequest;
use App\Http\Resources\Absence\AbsAttendanceResource;
use App\Http\Resources\Absence\AbsPayrollPeriodResource;
use App\Http\Resources\Absence\AbsReportDeductionResource;
use App\Http\Resources\Operational\OpsEmployeeResource;
use App\Services\Absence\AbsReportService;

class AbsReportController extends Controller
{
    public function __construct(
        protected AbsReportService $reportService,
    ) {}

    public function attendance(AbsAttendanceReportRequest $request)
    {
        $query = $this->reportService->attendanceQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $records = $query->get();
            $export = $this->reportService->storeXlsxExport(
                $request,
                'laporan-absensi-' . now()->format('YmdHis') . '.xlsx',
                ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Shift', 'Status', 'Jam Masuk', 'Jam Keluar', 'Alasan Terlambat', 'Alasan Pulang Awal'],
                $this->reportService->attendanceExportRows($records),
            );

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.attendance_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => __('absence.reports.attendance'),
            'data' => AbsAttendanceResource::collection($records),
        ]);
    }

    public function payroll(AbsPayrollReportRequest $request)
    {
        $query = $this->reportService->payrollQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $records = $query->get();
            $export = $this->reportService->storeXlsxExport(
                $request,
                'laporan-payroll-' . now()->format('YmdHis') . '.xlsx',
                ['No', 'Periode', 'Karyawan', 'Tarif Harian', 'Total Hari', 'Gaji Kotor', 'Total Potongan', 'Gaji Bersih', 'Status'],
                $this->reportService->payrollExportRows($records),
            );

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.payroll_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => __('absence.reports.payroll'),
            'data' => AbsPayrollPeriodResource::collection($records),
        ]);
    }

    public function deductions(AbsAttendanceReportRequest $request)
    {
        $query = $this->reportService->deductionsQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $records = $query->get();
            $export = $this->reportService->storeXlsxExport(
                $request,
                'laporan-pemotongan-' . now()->format('YmdHis') . '.xlsx',
                ['No', 'Tanggal', 'Karyawan', 'Cabang', 'Periode Payroll', 'Alasan', 'Nominal', 'Dibuat Oleh'],
                $this->reportService->deductionsExportRows($records),
            );

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.deductions_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => __('absence.reports.deductions'),
            'data' => AbsReportDeductionResource::collection($records),
        ]);
    }

    public function employees(AbsEmployeeReportRequest $request)
    {
        $query = $this->reportService->employeesQuery($request);

        if ($this->reportService->isExportMode($request)) {
            $records = $query->get();
            $export = $this->reportService->storeXlsxExport(
                $request,
                'laporan-karyawan-' . now()->format('YmdHis') . '.xlsx',
                ['No', 'Karyawan', 'Nomor Telepon', 'Cabang', 'Jabatan', 'Status', 'Shift'],
                $this->reportService->employeesExportRows($records),
            );

            return response()->json([
                'success' => true,
                'message' => __('absence.reports.employees_exported'),
                'data' => $export,
            ]);
        }

        $records = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => __('absence.reports.employees'),
            'data' => OpsEmployeeResource::collection($records),
        ]);
    }
}
