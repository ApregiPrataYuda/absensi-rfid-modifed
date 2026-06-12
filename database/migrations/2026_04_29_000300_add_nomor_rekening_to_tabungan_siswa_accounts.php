<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabungan_siswa_accounts', function (Blueprint $table): void {
            $table->string('nomor_rekening', 100)->nullable()->after('jenis_tabungan_id');
        });

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

        Schema::table('tabungan_siswa_accounts', function (Blueprint $table): void {
            $table->unique('nomor_rekening', 'tabungan_siswa_accounts_nomor_rekening_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tabungan_siswa_accounts', function (Blueprint $table): void {
            $table->dropUnique('tabungan_siswa_accounts_nomor_rekening_unique');
            $table->dropColumn('nomor_rekening');
        });
    }
};
