<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('tabungan_siswa_accounts')
            ->select(['id'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('tabungan_siswa_accounts')
                ->where('id', $row->id)
                ->update([
                    'nomor_rekening' => $this->generateUniqueAccountNumber((int) $row->id),
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
                    'nomor_rekening' => str_pad((string) $row->id, 10, '0', STR_PAD_LEFT),
                ]);
        }
    }

    protected function generateUniqueAccountNumber(int $ignoreId): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $this->randomNumericAccountNumber();
            $exists = DB::table('tabungan_siswa_accounts')
                ->where('nomor_rekening', $candidate)
                ->where('id', '!=', $ignoreId)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return now()->format('ymdHis') . str_pad((string) ($ignoreId % 10000), 4, '0', STR_PAD_LEFT);
    }

    protected function randomNumericAccountNumber(): string
    {
        return (string) random_int(100000, 999999) . (string) random_int(100000, 999999);
    }
};
