<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Transaksi {{ $transaction->nomor_bukti }}</title>
    <style>
        @page {
            margin: 12px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 9px;
            margin: 0;
        }

        .sheet {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
        }

        .header {
            background: #eff6ff;
            border-bottom: 2px solid #1d4ed8;
            padding: 10px 12px 9px 12px;
        }

        .brand {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .subtitle {
            font-size: 8px;
            color: #475569;
            margin: 0;
        }

        .receipt-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin: 8px 0 1px 0;
        }

        .receipt-number {
            font-size: 8.5px;
            font-weight: 700;
            color: #1d4ed8;
            margin: 0;
        }

        .body {
            padding: 10px 12px 12px 12px;
        }

        .amount-box {
            border: 1px solid #bfdbfe;
            background: #f8fbff;
            padding: 9px 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .amount-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 4px;
        }

        .amount-value {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .amount-note {
            font-size: 8px;
            color: #475569;
            margin: 0;
        }

        .section-title {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin: 0 0 5px 0;
        }

        .panel-table,
        .detail-table,
        .summary-table,
        .sign-table {
            width: 100%;
            border-collapse: collapse;
        }

        .panel-table {
            margin-bottom: 8px;
        }

        .panel-cell {
            width: 50%;
            vertical-align: top;
        }

        .panel-cell-left {
            padding-right: 8px;
        }

        .panel-cell-right {
            padding-left: 8px;
        }

        .detail-table {
            margin-bottom: 0;
        }

        .detail-table td {
            vertical-align: top;
            padding: 3px 0;
        }

        .detail-label {
            width: 82px;
            color: #64748b;
        }

        .detail-separator {
            width: 8px;
            color: #94a3b8;
        }

        .detail-value {
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
        }

        .summary-table {
            margin-bottom: 10px;
        }

        .summary-table td {
            width: 33.33%;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            padding: 8px 9px;
        }

        .summary-label {
            display: block;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            margin-bottom: 4px;
        }

        .summary-value {
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }

        .remark-box {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 8px 9px;
            margin-bottom: 10px;
        }

        .remark-text {
            color: #0f172a;
            line-height: 1.35;
            min-height: 24px;
        }

        .sign-table td {
            width: 50%;
            vertical-align: top;
            padding-top: 4px;
        }

        .sign-label {
            font-size: 8px;
            color: #64748b;
            margin-bottom: 16px;
        }

        .sign-name {
            font-weight: 700;
            color: #0f172a;
            border-top: 1px solid #94a3b8;
            padding-top: 5px;
            margin-right: 10px;
        }

        .footer {
            margin-top: 8px;
            font-size: 7.5px;
            color: #64748b;
            line-height: 1.3;
        }
    </style>
</head>
@php
    $formatRupiah = static fn ($amount) => 'Rp ' . number_format((int) $amount, 0, ',', '.');
    $typeLabel = match ($transaction->jenis_transaksi) {
        \App\Models\TabunganSiswaTransaction::TYPE_SETORAN => 'Setoran',
        \App\Models\TabunganSiswaTransaction::TYPE_PENARIKAN => 'Penarikan',
        \App\Models\TabunganSiswaTransaction::TYPE_PENYESUAIAN_MASUK => 'Penyesuaian Masuk',
        default => 'Penyesuaian Keluar',
    };
    $directionLabel = in_array($transaction->jenis_transaksi, [
        \App\Models\TabunganSiswaTransaction::TYPE_PENARIKAN,
        \App\Models\TabunganSiswaTransaction::TYPE_PENYESUAIAN_KELUAR,
    ], true) ? 'Dana Keluar' : 'Dana Masuk';
    $operatorName = trim((string) ($transaction->performedBy?->name ?: ($transaction->performedBy?->username ?? '-')));
    $editorName = trim((string) ($transaction->updatedBy?->name ?: ($transaction->updatedBy?->username ?? '')));
    $brandName = trim((string) ($appUiSettings['website_nama'] ?? 'ABSENSINDO'));
@endphp
<body>
    <div class="sheet">
        <div class="header">
            <p class="brand">{{ $brandName }}</p>
            <p class="subtitle">Dokumen bukti transaksi tabungan siswa</p>
            <p class="receipt-title">Bukti Transaksi</p>
            <p class="receipt-number">No. Bukti {{ $transaction->nomor_bukti ?: '-' }}</p>
        </div>

        <div class="body">
            <div class="amount-box">
                <div class="amount-label">{{ $typeLabel }} | {{ $directionLabel }}</div>
                <p class="amount-value">{{ $formatRupiah($transaction->nominal) }}</p>
                <p class="amount-note">
                    Transaksi pada {{ $transaction->transacted_at?->format('d M Y H:i') ?: '-' }}
                </p>
            </div>

            <table class="panel-table">
                <tr>
                    <td class="panel-cell panel-cell-left">
                        <p class="section-title">Informasi Rekening</p>
                        <table class="detail-table">
                            <tr>
                                <td class="detail-label">Nama Siswa</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $transaction->account?->siswa?->nama ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="detail-label">NISN</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $transaction->account?->siswa?->nisn ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="detail-label">Kelas</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $transaction->account?->siswa?->kelas ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="detail-label">Rekening</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $transaction->account?->nomor_rekening ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="detail-label">Tabungan</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">
                                    {{ $transaction->account?->jenisTabungan?->nama ?: '-' }}
                                    @if ($transaction->account?->jenisTabungan?->kode)
                                        ({{ $transaction->account?->jenisTabungan?->kode }})
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="panel-cell panel-cell-right">
                        <p class="section-title">Rincian Transaksi</p>
                        <table class="detail-table">
                            <tr>
                                <td class="detail-label">Jenis</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $typeLabel }}</td>
                            </tr>
                            <tr>
                                <td class="detail-label">Operator</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $operatorName !== '' ? $operatorName : '-' }}</td>
                            </tr>
                            @if ($editorName !== '')
                                <tr>
                                    <td class="detail-label">Editor</td>
                                    <td class="detail-separator">:</td>
                                    <td class="detail-value">{{ $editorName }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="detail-label">Cetak Oleh</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $printedBy !== '' ? $printedBy : '-' }}</td>
                            </tr>
                            <tr>
                                <td class="detail-label">Tgl Cetak</td>
                                <td class="detail-separator">:</td>
                                <td class="detail-value">{{ $printedAt?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="summary-table">
                <tr>
                    <td>
                        <span class="summary-label">Saldo Sebelum</span>
                        <span class="summary-value">{{ $formatRupiah($transaction->saldo_sebelum) }}</span>
                    </td>
                    <td>
                        <span class="summary-label">Nominal</span>
                        <span class="summary-value">{{ $formatRupiah($transaction->nominal) }}</span>
                    </td>
                    <td>
                        <span class="summary-label">Saldo Sesudah</span>
                        <span class="summary-value">{{ $formatRupiah($transaction->saldo_sesudah) }}</span>
                    </td>
                </tr>
            </table>

            <p class="section-title">Keterangan</p>
            <div class="remark-box">
                <div class="remark-text">{{ $transaction->keterangan ?: 'Tidak ada keterangan tambahan.' }}</div>
            </div>

            <table class="sign-table">
                <tr>
                    <td>
                        <div class="sign-label">Petugas / Operator</div>
                        <div class="sign-name">{{ $operatorName !== '' ? $operatorName : '-' }}</div>
                    </td>
                    <td>
                        <div class="sign-label">Penerima / Siswa</div>
                        <div class="sign-name">{{ $transaction->account?->siswa?->nama ?: '-' }}</div>
                    </td>
                </tr>
            </table>

            <div class="footer">
                Dokumen ini dibuat otomatis oleh sistem dan berlaku sebagai bukti transaksi tabungan siswa pada periode yang tercantum.
            </div>
        </div>
    </div>
</body>
</html>
