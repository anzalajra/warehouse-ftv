<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
        .muted { color: #666; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        td, th { padding: 3px 4px; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .row td { border-bottom: 0.5px solid #e5e5e5; }
        .total td { border-top: 1px solid #333; font-weight: bold; }
        .sub { color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; padding-top: 8px; }
    </style>
</head>
<body>
    @php $fmt = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.'); @endphp

    <h1>{{ $siteName }}</h1>
    <div class="muted">Laporan Keuangan · Periode {{ $startDate }} s.d. {{ $endDate }} (Neraca per {{ $endDate }})</div>

    <h2>Laporan Laba Rugi</h2>
    <table>
        <tr><td class="sub" colspan="2">Pendapatan</td></tr>
        @foreach ($incomeStatement['revenue'] as $r)
            <tr class="row"><td>{{ $r['code'] }} · {{ $r['name'] }}</td><td class="num">{{ $fmt($r['amount']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total Pendapatan</td><td class="num">{{ $fmt($incomeStatement['total_revenue']) }}</td></tr>

        <tr><td class="sub" colspan="2">Beban</td></tr>
        @foreach ($incomeStatement['expense'] as $r)
            <tr class="row"><td>{{ $r['code'] }} · {{ $r['name'] }}</td><td class="num">{{ $fmt($r['amount']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total Beban</td><td class="num">{{ $fmt($incomeStatement['total_expense']) }}</td></tr>

        <tr class="total"><td>Laba / (Rugi) Bersih</td><td class="num">{{ $fmt($incomeStatement['net_income']) }}</td></tr>
    </table>

    <h2>Neraca @if (! $balanceSheet['balanced']) <span class="muted">(selisih {{ $fmt($balanceSheet['difference']) }})</span> @endif</h2>
    <table>
        <tr><td class="sub" colspan="2">Aset</td></tr>
        @foreach ($balanceSheet['assets'] as $r)
            <tr class="row"><td>{{ $r['code'] }} · {{ $r['name'] }}</td><td class="num">{{ $fmt($r['amount']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total Aset</td><td class="num">{{ $fmt($balanceSheet['total_assets']) }}</td></tr>

        <tr><td class="sub" colspan="2">Liabilitas</td></tr>
        @foreach ($balanceSheet['liabilities'] as $r)
            <tr class="row"><td>{{ $r['code'] }} · {{ $r['name'] }}</td><td class="num">{{ $fmt($r['amount']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total Liabilitas</td><td class="num">{{ $fmt($balanceSheet['total_liabilities']) }}</td></tr>

        <tr><td class="sub" colspan="2">Ekuitas</td></tr>
        @foreach ($balanceSheet['equity'] as $r)
            <tr class="row"><td>{{ $r['code'] }} · {{ $r['name'] }}</td><td class="num">{{ $fmt($r['amount']) }}</td></tr>
        @endforeach
        <tr class="row"><td>Laba / (Rugi) Berjalan</td><td class="num">{{ $fmt($balanceSheet['net_income']) }}</td></tr>
        <tr class="total"><td>Total Ekuitas</td><td class="num">{{ $fmt($balanceSheet['total_equity']) }}</td></tr>

        <tr class="total"><td>Total Liabilitas + Ekuitas</td><td class="num">{{ $fmt($balanceSheet['total_liabilities'] + $balanceSheet['total_equity']) }}</td></tr>
    </table>
</body>
</html>
