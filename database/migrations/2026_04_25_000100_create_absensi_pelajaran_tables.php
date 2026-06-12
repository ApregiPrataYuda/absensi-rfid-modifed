<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_pelajaran', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->foreignId('jadwal_pelajaran_id')->constrained('jadwal_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tanggal', 'jadwal_pelajaran_id'], 'sesi_pelajaran_unique_slot');
            $table->index(['kelas_id', 'tanggal'], 'sesi_pelajaran_kelas_tanggal_idx');
        });

        Schema::create('absensi_pelajaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sesi_pelajaran_id')->constrained('sesi_pelajaran')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->timestamp('recorded_at')->nullable();
            $table->string('method', 20)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['sesi_pelajaran_id', 'siswa_id'], 'absensi_pelajaran_unique_sesi_siswa');
            $table->index(['siswa_id', 'created_at'], 'absensi_pelajaran_siswa_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pelajaran');
        Schema::dropIfExists('sesi_pelajaran');
    }
};
