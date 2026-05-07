<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Komisi Marketing</title>
    <style>
        * { margin: 15px; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }

        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #333; }
        .header h2 { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #555; }

        .marketing-section { margin-bottom: 24px; page-break-inside: avoid; }
        .marketing-name { font-size: 13px; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding: 4px 8px; background-color: #f0f0f0; border-left: 4px solid #333; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        thead tr { background-color: #333; color: #fff; }
        thead th { padding: 6px 8px; text-align: left; font-size: 10px; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #eee; }
        td.number { text-align: right; }

        .marketing-summary { text-align: right; font-size: 10px; padding: 4px 8px; background-color: #f0f0f0; }
        .marketing-summary span { margin-left: 24px; font-weight: bold; }

        .grand-total { margin-top: 20px; padding: 10px; background-color: #333; color: #fff; text-align: right; font-size: 11px; }
        .grand-total span { margin-left: 24px; }

        .footer { margin-top: 16px; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Komisi Marketing</h2>
        <p>Periode: {{ $period['from'] }} s/d {{ $period['to'] }}</p>
    </div>

    @foreach ($report as $item)
        <div class="marketing-section">
            <div class="marketing-name">{{ $item['marketing']['name'] }}</div>

            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Transaksi</th>
                        <th>Pelanggan</th>
                        <th>Payment</th>
                        <th class="number">Total Penjualan</th>
                        <th class="number">Diskon</th>
                        {{-- <th class="number">Setelah Diskon</th> --}}
                        <th class="number">Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['transactions'] as $trx)
                    <tr>
                        <td>{{ $trx['date'] }}</td>
                        <td>{{ $trx['transaction_code'] }}</td>
                        <td>{{ $trx['customer'] }}</td>
                        <td>{{ $trx['payment_type'] }}</td>
                        <td class="number">{{ number_format($trx['total'], 0, ',', '.') }}</td>
                        <td class="number">{{ number_format($trx['discount'], 0, ',', '.') }}</td>
                        {{-- <td class="number">{{ number_format($trx['total_after_discount'], 0, ',', '.') }}</td> --}}
                        <td class="number">{{ number_format($trx['commission'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="marketing-summary">
                Jumlah Penjualan & Komisi {{ $item['marketing']['name'] }}:
                <span>Rp {{ number_format($item['summary']['total_sales'], 0, ',', '.') }}</span>
                <span>Komisi: Rp {{ number_format($item['summary']['total_commission'], 0, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    <div class="grand-total">
        TOTAL KESELURUHAN
        <span>Penjualan: Rp {{ number_format($grand_total['total_sales'], 0, ',', '.') }}</span>
        <span>Diskon: Rp {{ number_format($grand_total['total_discount'], 0, ',', '.') }}</span>
        <span>Komisi: Rp {{ number_format($grand_total['total_commission'], 0, ',', '.') }}</span>
    </div>

    <div class="footer">
        Digenerate pada: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>