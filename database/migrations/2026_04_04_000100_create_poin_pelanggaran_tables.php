<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_pelanggaran', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->string('nama', 150);
            $table->string('kategori', 80)->nullable();
            $table->unsignedInteger('poin')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'nama'], 'jenis_pelanggaran_tenant_nama_unique');
        });

        Schema::create('poin_pelanggaran_siswa', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('jenis_pelanggaran_id')->nullable()->constrained('jenis_pelanggaran')->nullOnDelete();
            $table->string('nama_pelanggaran', 150);
            $table->unsignedInteger('poin')->default(0);
            $table->date('tanggal')->index();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'tanggal']);
            $table->index(['tenant_id', 'siswa_id', 'tanggal'], 'poin_pelanggaran_siswa_tenant_siswa_tanggal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poin_pelanggaran_siswa');
        Schema::dropIfExists('jenis_pelanggaran');
    }
};
