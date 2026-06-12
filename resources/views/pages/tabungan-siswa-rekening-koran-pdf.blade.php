<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekening Koran {{ $account->nomor_rekening }}</title>
    <style>
        @page {
            margin: 18px 18px 22px 18px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            font-size: 9px;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header-title {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .header-subtitle {
            font-size: 9px;
            color: #475569;
            margin: 0;
        }

        .meta-table,
        .summary-table,
        .statement-table {
            width: 100%;
            border-collapse: collapse;
        }

        .statement-table {
            table-layout: fixed;
        }

        .meta-table {
            margin-bottom: 10px;
        }

        .meta-table td {
            vertical-align: top;
            padding: 2px 5px 2px 0;
        }

        .meta-label {
            width: 102px;
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .meta-value {
            font-weight: 600;
            color: #0f172a;
            font-size: 9px;
        }

        .summary-wrap {
            margin-bottom: 12px;
        }

        .summary-table td {
            width: 25%;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            padding: 7px 8px;
        }

        .summary-label {
            display: block;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 3px;
        }

        .summary-value {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }

        .section-title {
            font-size: 9px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .statement-table thead th {
            background: #e2e8f0;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            padding: 3px 2px;
            font-size: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: left;
        }

        .statement-table tbody td {
            border: 1px solid #dbe2ea;
            padding: 3px 2px;
            vertical-align: top;
            font-size: 7px;
            line-height: 1.15;
            word-break: break-word;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .mutasi {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1px;
        }

        .note {
            color: #475569;
            font-size: 6px;
            line-height: 1.1;
        }

        .saldo-awal-row td {
            background: #f8fafc;
            font-weight: 700;
        }

        .empty-row td {
            text-align: center;
            color: #64748b;
            padding: 10px 4px;
        }

        .footer {
            margin-top: 12px;
            font-size: 8px;
            color: #64748b;
        }

        .col-no {
            width: 4%;
            white-space: nowrap;
            text-align: center;
            font-size: 7px;
            line-height: 1.1;
            letter-spacing: 0;
            padding-left: 1px !important;
            padding-right: 1px !important;
        }

        .col-date {
            white-space: nowrap;
        }

        .col-bukti {
            white-space: normal;
            word-break: break-all;
            overflow-wrap: anywhere;
            line-height: 1.1;
            font-size: 6px;
        }
    </style>
</head>
@php
    $formatRupiah = static fn (int $amount): string => 'Rp ' . number_format($amount, 0, ',', '.');
    $brandName = trim((string) ($appUiSettings['website_nama'] ?? 'ABSENSINDO'));
    $periodLabel = ($periodStart?->format('d M Y') ?? '-') . ' s.d. ' . ($periodEnd?->format('d M Y') ?? '-');
    $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $selectedMonthLabel = ($monthNames[(int) ($selectedMonth ?? 0)] ?? '-') . ' ' . ($selectedYear ?? '-');
@endphp
<body>
    <div class="header">
        <h1 class="header-title">Rekening Koran Tabungan Siswa</h1>
        <p class="header-subtitle">{{ $brandName }} | Dokumen mutasi tabungan per rekening</p>
    </div>

    <table class="meta-table">
        <tr>
            <td width="50%">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Nama Siswa</td>
                        <td class="meta-value">{{ $account->siswa?->nama ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">NISN</td>
                        <td class="meta-value">{{ $account->siswa?->nisn ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Kelas</td>
                        <td class="meta-value">{{ $account->siswa?->kelas ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Jenis Tabungan</td>
                        <td class="meta-value">{{ $account->jenisTabungan?->nama ?: '-' }} ({{ $account->jenisTabungan?->kode ?: '-' }})</td>
                    </tr>
                </table>
            </td>
            <td width="50%">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">No Rekening</td>
                        <td class="meta-value">{{ $account->nomor_rekening ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Periode</td>
                        <td class="meta-value">{{ $periodLabel }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Bulan Buku</td>
                        <td class="meta-value">{{ $selectedMonthLabel }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Tanggal Cetak</td>
                        <td class="meta-value">{{ $printedAt?->format('d M Y H:i') ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Dicetak Oleh</td>
                        <td class="meta-value">{{ $printedBy !== '' ? $printedBy : '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="summary-wrap">
        <table class="summary-table">
            <tr>
                <td>
                    <span class="summary-label">Saldo Awal</span>
                    <span class="summary-value">{{ $formatRupiah((int) $saldoAwal) }}</span>
                </td>
                <td>
                    <span class="summary-label">Total Debit</span>
                    <span class="summary-value">{{ $formatRupiah((int) $totalDebit) }}</span>
                </td>
                <td>
                    <span class="summary-label">Total Kredit</span>
                    <span class="summary-value">{{ $formatRupiah((int) $totalKredit) }}</span>
                </td>
                <td>
                    <span class="summary-label">Saldo Akhir</span>
                    <span class="summary-value">{{ $formatRupiah((int) $saldoAkhir) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <h2 class="section-title">Rincian Mutasi</h2>

    <table class="statement-table">
        <colgroup>
            <col style="width: 2.5%;">
            <col style="width: 12%;">
            <col style="width: 16%;">
            <col style="width: 9%;">
            <col style="width: 23.5%;">
            <col style="width: 10%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
        </colgroup>
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-date">Tanggal</th>
                <th class="col-bukti">No Bukti</th>
                <th>Mutasi</th>
                <th>Keterangan</th>
                <th>Operator</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr class="saldo-awal-row">
                <td class="col-no">-</td>
                <td>{{ $periodStart?->format('d M Y') ?: '-' }}</td>
                <td>-</td>
                <td>Saldo Awal</td>
                <td>Saldo awal periode rekening koran</td>
                <td class="text-center">-</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">{{ $formatRupiah((int) $saldoAwal) }}</td>
            </tr>
            @forelse ($statementRows as $index => $row)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td class="col-date">{{ $row['tanggal'] }}</td>
                    <td class="col-bukti">{{ $row['nomor_bukti'] }}</td>
                    <td>
                        <div class="mutasi">{{ $row['mutasi_label'] }}</div>
                    </td>
                    <td>
                        <div>{{ $row['keterangan'] !== '' ? $row['keterangan'] : '-' }}</div>
                    </td>
                    <td>
                        <div>{{ $row['operator'] !== '' ? $row['operator'] : '-' }}</div>
                        @if ($row['editor'] !== '')
                            <div class="note">Edit: {{ $row['editor'] }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ $row['debit'] > 0 ? $formatRupiah((int) $row['debit']) : '-' }}</td>
                    <td class="text-right">{{ $row['kredit'] > 0 ? $formatRupiah((int) $row['kredit']) : '-' }}</td>
                    <td class="text-right">{{ $formatRupiah((int) $row['saldo']) }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="9">Belum ada transaksi pada rekening ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh sistem. Rincian mutasi menampilkan transaksi aktif beserta saldo berjalan pada rekening tabungan siswa.
    </div>
</body>
</html>
