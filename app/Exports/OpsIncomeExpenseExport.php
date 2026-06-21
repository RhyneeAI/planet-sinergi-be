<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class OpsIncomeExpenseExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected array $data,
        protected string $sheetTitle = 'Laporan Operasional',
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function view(): View
    {
        return view('reports.operational.income-expense-excel', [
            'period'         => $this->data['period'],
            'saldo_awal'     => $this->data['saldo_awal'],
            'saldo_akhir'    => $this->data['saldo_akhir'],
            'groups'         => $this->data['groups'],
            'total_income'   => $this->data['total_income'],
            'total_expense'  => $this->data['total_expense'],
            'total_remaining'=> $this->data['total_remaining'],
            'is_specific'    => $this->data['groups']->count() === 1 && $this->data['groups']->first()['mandor'] !== null,
        ]);
    }
}
