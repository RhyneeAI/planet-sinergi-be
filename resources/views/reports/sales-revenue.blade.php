{{-- resources/views/reports/sales-revenue.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Omset Penjualan</title>
    <style>
        * { margin: 10px 0; padding: 10px 20px; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        /* Header */
        .header {
            text-align: center;
            margin: 15px 15px 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }

        .header h2 {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 11px;
            color: #555;
        }

        /* Warning Box */
        .warning-box {
            background-color: #FFF3CD;
            border-left: 4px solid #FFC107;
            padding: 8px 12px;
            margin: 0 15px 16px;
            font-size: 10px;
            color: #856404;
            border-radius: 4px;
        }

        /* Section Title */
        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin: 16px 15px 8px;
            padding: 4px 8px;
            background-color: #f0f0f0;
            border-left: 4px solid #333;
        }

        /* Tabel */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            padding: 20px;
        }

        thead tr {
            background-color: #333;
            color: #fff;
        }

        thead th {
            padding: 6px 8px;
            font-size: 10px;
            text-align: center;
            border: 1px solid #444;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody td {
            padding: 5px 8px;
            font-size: 10px;
            border-bottom: 1px solid #eee;
            border: 1px solid #ddd;
        }

        /* td[rowspan] {
            border: 2px solid red !important; 
        } */

        /* Alignment */
        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        /* Rank Colors */
        .rank-1-soft { background-color: #FFF9C4; }
        .rank-2-soft { background-color: #FFF3E0; }
        .rank-3-soft { background-color: #F5F5F5; }

        /* Status Cicilan */
        .status-aktif { color: #3182ce; font-weight: bold; }
        .status-lunas { color: #38a169; font-weight: bold; }
        .status-jatuh-tempo { color: #e53e3e; font-weight: bold; }

        .text-danger { color: #e53e3e; }
        .font-bold { font-weight: bold; }

        /* Summary */
        .summary {
            margin: 16px 15px;
            padding: 10px;
            background-color: #333;
            color: #fff;
            text-align: right;
            font-size: 11px;
        }

        .summary span {
            margin-left: 24px;
        }

        .summary .text-danger {
            color: #fc8181;
        }

        /* Footer */
        .footer {
            margin: 16px 15px;
            font-size: 9px;
            color: #999;
            text-align: center;
        }

        /* Small text */
        small {
            display: block;
            font-size: 9px;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Omset Penjualan</h2>
        <p>Periode: {{ $period['from'] }} s/d {{ $period['to'] }}</p>
    </div>

    <div class="warning-box">
        <strong>Informasi Penting :</strong><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Harga jual pada laporan ini adalah harga saat transaksi dilakukan,
        Perubahan harga pada master data tidak akan mempengaruhi data historis penjualan untuk menjaga keaslian laporan.
    </div>

    {{-- Top 10 Produk Terlaris --}}
    <div class="section-title">Top 10 Produk Terlaris</div>
    <table>
        <thead>
            <tr>
                <th style="width:30px">No</th>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th class="text-right">Harga Jual</th>
                <th class="text-right">Qty Terjual</th>
                <th class="text-right">Omset</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($top_products as $index => $item)
            @php
                $rankClass = '';
                if ($index == 0) $rankClass = 'rank-1-soft';
                elseif ($index == 1) $rankClass = 'rank-2-soft';
                elseif ($index == 2) $rankClass = 'rank-3-soft';
            @endphp
            <tr class="{{ $rankClass }}">
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item['code'] }}</td>
                <td>{{ $item['name'] }}</td>
                <td class="text-right">Rp {{ number_format($item['sell_price'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item['qty_sold'], 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($item['revenue'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Detail Penjualan Per Transaksi --}}
    <div class="section-title">Detail Penjualan Per Transaksi</div>
    <table>
        <thead>
            <tr>
                <th style="width:30px">No</th>
                <th>Kode Transaksi</th>
                <th>Tanggal Transaksi</th>
                <th>Tipe Pembayaran</th>
                <th>Kasir</th>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th class="text-right">Harga Jual</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Total (Omset)</th>
                <th class="text-right">Kekurangan (CICIL)</th>
                <th class="text-right">Keuntungan</th>  
            </tr>
        </thead>
        <tbody>
            @foreach ($details as $transaction)
                @php $itemCount = count($transaction['items']); @endphp

                @foreach ($transaction['items'] as $itemIndex => $item)
                    <tr>
                        @if ($itemIndex === 0)
                            {{-- Baris pertama: info transaksi dengan rowspan --}}
                            <td class="text-center" rowspan="{{ $itemCount }}">{{ $loop->parent->iteration }}</td>
                            <td rowspan="{{ $itemCount }}">{{ $transaction['transaction_code'] }}</td>
                            <td rowspan="{{ $itemCount }}">{{ $transaction['date'] }}</td>
                            <td rowspan="{{ $itemCount }}">
                                {{ $transaction['payment_type'] }}
                                @if($transaction['is_cicil'])
                                    <small class="status-{{ str_replace(' ', '-', strtolower($transaction['cicil_info']['status'])) }}">
                                        ({{ $transaction['cicil_info']['status'] }})
                                    </small>
                                @endif
                            </td>
                            <td rowspan="{{ $itemCount }}">{{ $transaction['cashier'] }}</td>
                        @endif

                        {{-- Detail produk --}}
                        <td>{{ $item['code'] }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="text-right">Rp {{ number_format($item['sell_price'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($item['quantity'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>

                        @if ($itemIndex === 0)
                            <td class="text-right" rowspan="{{ $itemCount }}">
                                @if($transaction['is_cicil'] && $transaction['cicil_info']['remaining_amount'] > 0)
                                    <span class="text-danger">
                                        Rp {{ number_format($transaction['total'] - $transaction['cicil_info']['remaining_amount'], 0, ',', '.') }}
                                    </span>
                                @else
                                    Rp {{ number_format($transaction['total'], 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="text-right" rowspan="{{ $itemCount }}">
                                @if($transaction['is_cicil'] && $transaction['cicil_info']['remaining_amount'] > 0)
                                    <span class="text-danger">
                                        Rp {{ number_format($transaction['cicil_info']['remaining_amount'], 0, ',', '.') }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-right" rowspan="{{ $itemCount }}">
                                Rp {{ number_format($transaction['profit'], 0, ',', '.') }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        TOTAL OMSET KESELURUHAN
        <span>Total Qty : {{ number_format($grand_total['total_qty'], 0, ',', '.') }}</span>
        <span>Total Omset : Rp {{ number_format($grand_total['total_revenue'], 0, ',', '.') }}</span>
        @if($grand_total['total_remaining'] > 0)
            <span class="text-danger">
                Total Kekurangan: Rp {{ number_format($grand_total['total_remaining'], 0, ',', '.') }}
            </span>
        @endif
    </div>

    <div class="footer">
        Digenerate pada : {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>