<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Absensi{{ $filters['date'] ? ' '.$filters['date']->format('d-m-Y') : '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 24px; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #eef1f6; }
        tr { page-break-inside: avoid; }
        .bar { margin-bottom: 16px; }
        button { padding: 8px 14px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; cursor: pointer; }
        @media print { .bar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
        <span style="color:#555; margin-left:8px">Di dialog cetak, pilih tujuan "Simpan sebagai PDF".</span>
    </div>

    <h1>Rekap Absensi Karyawan</h1>
    <p class="meta">
        PT. Tinta Inspirasi Indonesia
        &middot; Tanggal: {{ $filters['date'] ? $filters['date']->locale('id')->translatedFormat('j F Y') : 'Semua tanggal' }}
        &middot; Dicetak oleh {{ $printedBy }} pada {{ now()->locale('id')->translatedFormat('j F Y H.i') }}
    </p>

    <table>
        <thead>
            <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell ?? '-' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headers) }}" style="text-align:center">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="meta" style="margin-top:12px">Total {{ count($rows) }} baris.</p>
</body>
</html>
