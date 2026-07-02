<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
        .section { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Slip Gaji</h1>
    <p><strong>Nama:</strong> {{ $period->user->name }}</p>
    <p><strong>Periode:</strong> {{ sprintf('%02d', $period->period_month) }}/{{ $period->period_year }}</p>
    <p><strong>Cabang:</strong> {{ $period->user->absEmployeeProfile?->subCompany?->name ?? '-' }}</p>
    <p><strong>Jabatan:</strong> {{ $period->user->absEmployeeProfile?->position?->name ?? '-' }}</p>

    <div class="section">
        <table>
            <tr><th>Total Hari Hadir</th><td>{{ $period->total_days }} hari</td></tr>
            <tr><th>Harga Per Hari</th><td>Rp {{ number_format((float) $period->daily_rate, 0, ',', '.') }}</td></tr>
            <tr><th>Gaji Kotor</th><td>Rp {{ number_format((float) $period->gross_salary, 0, ',', '.') }}</td></tr>
            <tr><th>Total Bonus</th><td>Rp {{ number_format((float) $period->total_bonus, 0, ',', '.') }}</td></tr>
            <tr><th>Total Pemotongan</th><td>Rp {{ number_format((float) $period->total_deduction, 0, ',', '.') }}</td></tr>
            <tr><th><strong>Gaji Bersih</strong></th><td><strong>Rp {{ number_format((float) $period->net_salary, 0, ',', '.') }}</strong></td></tr>
        </table>
    </div>

    @if($period->bonuses->isNotEmpty())
    <div class="section">
        <h3>Rincian Bonus</h3>
        <table>
            <thead>
                <tr><th>Alasan</th><th class="right">Nominal</th></tr>
            </thead>
            <tbody>
                @foreach($period->bonuses as $bonus)
                <tr>
                    <td>{{ $bonus->reason }}</td>
                    <td class="right">Rp {{ number_format((float) $bonus->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($period->deductions->isNotEmpty())
    <div class="section">
        <h3>Rincian Pemotongan</h3>
        <table>
            <thead>
                <tr><th>Alasan</th><th class="right">Nominal</th></tr>
            </thead>
            <tbody>
                @foreach($period->deductions as $deduction)
                <tr>
                    <td>{{ $deduction->reason }}</td>
                    <td class="right">Rp {{ number_format((float) $deduction->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <h3>Rincian Kehadiran</h3>
        <table>
            <thead>
                <tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach($attendances as $attendance)
                <tr>
                    <td>{{ $attendance->date->format('d/m/Y') }}</td>
                    <td>{{ $attendance->check_in_time ?? '-' }}</td>
                    <td>{{ $attendance->check_out_time ?? '-' }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', $attendance->status->value)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
