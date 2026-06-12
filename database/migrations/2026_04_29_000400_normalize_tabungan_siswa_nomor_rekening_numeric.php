<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('tabungan_siswa_accounts')
            ->select(['id', 'nomor_rekening'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $normalized = str_pad((string) $row->id, 10, '0', STR_PAD_LEFT);

            if ((string) ($row->nomor_rekening ?? '') === $normalized) {
                continue;
            }

            DB::table('tabungan_siswa_accounts')
                ->where('id', $row->id)
                ->update([
                    'nomor_rekening' => $normalized,
                ]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('tabungan_siswa_accounts')
            ->select(['id'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('tabungan_siswa_accounts')
                ->where('id', $row->id)
                ->update([
                    'nomor_rekening' => 'TBS-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
                ]);
        }
    }
};
