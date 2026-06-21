<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Pemasukan & Pengeluaran Operasional</title>
    <style>
        * { margin: 15px; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }

        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #333; }
        .header h2 { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #555; }

        .saldo-section { margin-bottom: 20px; }
        .saldo-box { display: inline-block; padding: 8px 16px; margin: 4px; border: 1px solid #ddd; border-radius: 4px; text-align: center; }
        .saldo-box .label { font-size: 10px; color: #888; }
        .saldo-box .value { font-size: 14px; font-weight: bold; }
        .saldo-box.saldo-akhir { background-color: #e8f5e9; border-color: #4caf50; }

        .mandor-section { margin-bottom: 24px; page-break-inside: avoid; }
        .mandor-name { font-size: 13px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding: 4px 8px; background-color: #f0f0f0; border-left: 4px solid #ff9800; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        thead tr { background-color: #333; color: #fff; }
        thead th { padding: 6px 8px; text-align: left; font-size: 10px; text-align: center; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #eee; }
        td.number { text-align: right; }
        td.type-income { color: #2e7d32; }
        td.type-expense { color: #c62828; }

        .type-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .type-badge.income { background-color: #e8f5e9; color: #2e7d32; }
        .type-badge.expense { background-color: #ffebee; color: #c62828; }

        .mandor-summary { text-align: right; font-size: 10px; padding: 4px 8px; background-color: #f0f0f0; }
        .mandor-summary span { margin-left: 24px; font-weight: bold; }

        .grand-total { margin-top: 20px; padding: 10px; background-color: #333; color: #fff; text-align: right; font-size: 11px; }
        .grand-total span { margin-left: 24px; }

        .footer { margin-top: 16px; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Pemasukan & Pengeluaran Operasional</h2>
        <p>Periode: {{ $period['start_date'] }} s/d {{ $period['end_date'] }}</p>
    </div>

    <div class="saldo-section">
        <div class="saldo-box">
            <div class="label">Saldo Awal</div>
            <div class="value">Rp {{ number_format($saldo_awal, 0, ',', '.') }}</div>
        </div>
        <div class="saldo-box saldo-akhir">
            <div class="label">Saldo Akhir</div>
            <div class="value">Rp {{ number_format($saldo_akhir, 0, ',', '.') }}</div>
        </div>
    </div>

    @foreach ($groups as $group)
        <div class="mandor-section">
            <div class="mandor-name">{{ $group['mandor'] ? $group['mandor']['name'] : 'PUSAT (Internal)' }}</div>
            <div style="margin-bottom: 8px; font-size: 10px;">
                <span>Saldo Awal: <strong>Rp {{ number_format($group['saldo_awal'], 0, ',', '.') }}</strong></span>
                &nbsp;|&nbsp;
                <span>Saldo Akhir: <strong>Rp {{ number_format($group['saldo_akhir'], 0, ',', '.') }}</strong></span>
            </div>

            @if ($group['incomes']->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Cabang</th>
                            <th>Metode</th>
                            <th class="number">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['incomes'] as $income)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $income['date'] }}</td>
                            <td>{{ $income['name'] }}</td>
                            <td><span class="type-badge income">Pemasukan</span></td>
                            <td>{{ $income['sub_company']['name'] ?? '-' }}</td>
                            <td>{{ $income['payment_method'] }}</td>
                            <td class="number type-income">Rp {{ number_format($income['amount'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($group['expenses']->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Cabang</th>
                            <th>Metode</th>
                            <th class="number">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['expenses'] as $expense)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $expense['date'] }}</td>
                            <td>{{ $expense['name'] }}</td>
                            <td><span class="type-badge expense">Pengeluaran</span></td>
                            <td>{{ $expense['sub_company']['name'] ?? '-' }}</td>
                            <td>{{ $expense['payment_method'] }}</td>
                            <td class="number type-expense">Rp {{ number_format($expense['amount'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="mandor-summary">
                {{ $group['mandor'] ? $group['mandor']['name'] : 'PUSAT' }}:
                <span>Pemasukan: Rp {{ number_format($group['total_income'], 0, ',', '.') }}</span>
                <span>Pengeluaran: Rp {{ number_format($group['total_expense'], 0, ',', '.') }}</span>
                <span>Sisa: Rp {{ number_format($group['remaining'], 0, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    <div class="grand-total">
        TOTAL KESELURUHAN
        <span>Pemasukan: Rp {{ number_format($total_income, 0, ',', '.') }}</span>
        <span>Pengeluaran: Rp {{ number_format($total_expense, 0, ',', '.') }}</span>
        <span>Sisa: Rp {{ number_format($total_remaining, 0, ',', '.') }}</span>
    </div>

    <div class="footer">
        Digenerate pada: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
