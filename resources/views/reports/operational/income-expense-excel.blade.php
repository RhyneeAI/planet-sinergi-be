<table>
    {{-- Title --}}
    <tr>
        <td colspan="8" style="font-size: 16px; font-weight: bold; text-align: center; padding: 8px;">
            Laporan Operasional Gudang Planet
            @if ($is_specific)
                Cabang {{ $groups->first()['sub_companies']->pluck('name')->implode(', ') }}
            @endif
        </td>
    </tr>

    {{-- Subtitle: Periode --}}
    <tr>
        <td colspan="8" style="font-size: 11px; text-align: center; padding: 4px; color: #555;">
            Periode {{ \Carbon\Carbon::parse($period['start_date'])->locale('id')->translatedFormat('d M Y') }}
            s/d
            {{ \Carbon\Carbon::parse($period['end_date'])->locale('id')->translatedFormat('d M Y') }}
        </td>
    </tr>

    {{-- Blank row --}}
    <tr><td colspan="8" style="padding: 2px;"></td></tr>

    {{-- Saldo --}}
    <tr>
        <td colspan="8" style="font-size: 11px; padding: 2px 4px;">
            Saldo Awal: <strong>Rp {{ number_format($saldo_awal, 0, ',', '.') }}</strong>
            &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
            Saldo Akhir: <strong>Rp {{ number_format($saldo_akhir, 0, ',', '.') }}</strong>
        </td>
    </tr>

    <tr><td colspan="8" style="padding: 2px;"></td></tr>

    @php $groupIndex = 0; @endphp
    @foreach ($groups as $group)
        @php $groupIndex++; @endphp

        {{-- Spacer antar group --}}
        @if ($groupIndex > 1)
            <tr><td colspan="8" style="padding: 4px;"></td></tr>
        @endif

        {{-- Group header: Mandor + Cabang + Saldo --}}
        <tr>
            <td colspan="8" style="font-size: 12px; font-weight: bold; padding: 6px 4px; background-color: #f0f0f0; border-left: 3px solid #ff9800;">
                {{ $group['mandor'] ? $group['mandor']['name'] : 'PUSAT (Internal)' }}
                @if ($group['sub_companies']->isNotEmpty())
                    — {{ $group['sub_companies']->pluck('name')->implode(', ') }}
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="8" style="font-size: 10px; padding: 2px 4px; color: #666;">
                Saldo Awal: <strong>Rp {{ number_format($group['saldo_awal'], 0, ',', '.') }}</strong>
                &nbsp;|&nbsp;
                Saldo Akhir: <strong>Rp {{ number_format($group['saldo_akhir'], 0, ',', '.') }}</strong>
            </td>
        </tr>

        {{-- Table header --}}
        <tr>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: center; font-weight: bold;">No</th>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: left; font-weight: bold;">Tanggal</th>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: left; font-weight: bold;">Nama Transaksi</th>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: center; font-weight: bold;">Tipe</th>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: left; font-weight: bold;">Metode</th>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: right; font-weight: bold;">Pemasukan</th>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: right; font-weight: bold;">Pengeluaran</th>
            <th style="background-color: #333; color: #fff; font-size: 10px; padding: 6px 4px; text-align: left; font-weight: bold;">Catatan</th>
        </tr>

        {{-- Incomes --}}
        @foreach ($group['incomes'] as $income)
            @php
                $dateFormatted = \Carbon\Carbon::parse($income['date'])->locale('id')->translatedFormat('d/m/Y');
                $rowBg = $loop->even ? 'background-color: #f9f9f9;' : '';
            @endphp
            <tr>
                <td style="font-size: 10px; padding: 4px; text-align: center; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $loop->iteration }}</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $dateFormatted }}</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $income['name'] }}</td>
                <td style="font-size: 10px; padding: 4px; text-align: center; border-bottom: 1px solid #eee; color: #2e7d32; {{ $rowBg }}">Pemasukan</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $income['payment_method'] }}</td>
                <td style="font-size: 10px; padding: 4px; text-align: right; border-bottom: 1px solid #eee; color: #2e7d32; {{ $rowBg }}">Rp {{ number_format($income['amount'], 0, ',', '.') }}</td>
                <td style="font-size: 10px; padding: 4px; text-align: right; border-bottom: 1px solid #eee; {{ $rowBg }}">-</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $income['note'] ?? '' }}</td>
            </tr>
        @endforeach

        {{-- Expenses --}}
        @foreach ($group['expenses'] as $expense)
            @php
                $dateFormatted = \Carbon\Carbon::parse($expense['date'])->locale('id')->translatedFormat('d/m/Y');
                $rowBg = $loop->even ? 'background-color: #f9f9f9;' : '';
            @endphp
            <tr>
                <td style="font-size: 10px; padding: 4px; text-align: center; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $loop->iteration }}</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $dateFormatted }}</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $expense['name'] }}</td>
                <td style="font-size: 10px; padding: 4px; text-align: center; border-bottom: 1px solid #eee; color: #c62828; {{ $rowBg }}">Pengeluaran</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $expense['payment_method'] }}</td>
                <td style="font-size: 10px; padding: 4px; text-align: right; border-bottom: 1px solid #eee; {{ $rowBg }}">-</td>
                <td style="font-size: 10px; padding: 4px; text-align: right; border-bottom: 1px solid #eee; color: #c62828; {{ $rowBg }}">Rp {{ number_format($expense['amount'], 0, ',', '.') }}</td>
                <td style="font-size: 10px; padding: 4px; border-bottom: 1px solid #eee; {{ $rowBg }}">{{ $expense['note'] ?? '' }}</td>
            </tr>
        @endforeach

        {{-- Group summary --}}
        <tr>
            <td colspan="8" style="font-size: 10px; padding: 4px; background-color: #f0f0f0; text-align: right;">
                <strong>{{ $group['mandor'] ? $group['mandor']['name'] : 'PUSAT' }}:</strong>
                &nbsp;Pemasukan: <strong>Rp {{ number_format($group['total_income'], 0, ',', '.') }}</strong>
                &nbsp;|&nbsp;Pengeluaran: <strong>Rp {{ number_format($group['total_expense'], 0, ',', '.') }}</strong>
                &nbsp;|&nbsp;Sisa: <strong>Rp {{ number_format($group['remaining'], 0, ',', '.') }}</strong>
            </td>
        </tr>
    @endforeach

    {{-- Blank row --}}
    <tr><td colspan="8" style="padding: 2px;"></td></tr>

    {{-- Grand total --}}
    <tr>
        <td colspan="8" style="font-size: 11px; padding: 8px 4px; background-color: #333; color: #fff; text-align: right;">
            <strong>TOTAL KESELURUHAN</strong>
            &nbsp;&nbsp;Pemasukan: <strong>Rp {{ number_format($total_income, 0, ',', '.') }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;Pengeluaran: <strong>Rp {{ number_format($total_expense, 0, ',', '.') }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;Sisa: <strong>Rp {{ number_format($total_remaining, 0, ',', '.') }}</strong>
        </td>
    </tr>

    {{-- Footer --}}
    <tr><td colspan="8" style="padding: 4px;"></td></tr>
    <tr>
        <td colspan="8" style="font-size: 9px; color: #999; text-align: center;">
            Digenerate pada: {{ now()->locale('id')->translatedFormat('d/m/Y H:i:s') }}
        </td>
    </tr>
</table>
