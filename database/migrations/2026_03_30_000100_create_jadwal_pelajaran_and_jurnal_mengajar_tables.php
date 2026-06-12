<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pelajaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('hari')->comment('1=Senin ... 7=Minggu');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('mata_pelajaran', 120);
            $table->string('ruang', 120)->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['kelas_id', 'hari']);
            $table->index(['guru_id', 'hari']);
            $table->unique(['kelas_id', 'hari', 'jam_mulai'], 'jadwal_pelajaran_unique_slot');
        });

        Schema::create('jurnal_mengajar_harian', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->index();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jadwal_pelajaran_id')->nullable()->constrained('jadwal_pelajaran')->nullOnDelete();
            $table->string('mata_pelajaran', 120);
            $table->string('topik_materi', 500);
            $table->text('ringkasan_pembelajaran')->nullable();
            $table->text('tugas_siswa')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 20)->default('selesai')->index();
            $table->timestamps();

            $table->index(['kelas_id', 'tanggal']);
            $table->index(['guru_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_mengajar_harian');
        Schema::dropIfExists('jadwal_pelajaran');
    }
};
