<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->foreignId('wali_kelas')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('kapasitas')->nullable();
            $table->timestamps();
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nisn', 32)->unique();
            $table->string('jenis_kelamin')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('kelas', 100)->nullable()->index();
            $table->text('alamat')->nullable();
            $table->timestamps();

            $table->foreign('kelas')
                ->references('nama')
                ->on('kelas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->index();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('nisn', 32);
            $table->string('nama');
            $table->string('kelas', 100);
            $table->time('jam_datang')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('status')->default('Hadir');
            $table->timestamps();

            $table->unique(['tanggal', 'siswa_id']);
            $table->index(['nisn', 'kelas']);
        });

        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->nullable()->index();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('kelas', 100)->nullable()->index();
            $table->string('keterangan');
            $table->timestamps();
        });

        Schema::create('konfigurasi', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jadwal_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->unsignedTinyInteger('hari')->comment('1=Senin ... 7=Minggu');
            $table->boolean('is_libur')->default(false);
            $table->time('jam_masuk_mulai')->nullable();
            $table->time('jam_masuk_akhir')->nullable();
            $table->time('jam_masuk_telat')->nullable();
            $table->time('jam_pulang_mulai')->nullable();
            $table->time('jam_pulang_akhir')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['kelas_id', 'hari']);
            $table->index(['hari', 'is_libur']);
        });

        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nisn', 32)->index();
            $table->string('jenis_kelamin')->nullable();
            $table->string('kelas_terakhir')->nullable();
            $table->unsignedInteger('tahun_lulus')->nullable();
            $table->string('kontak')->nullable();
            $table->timestamps();
        });

        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->string('role');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_tokens');
        Schema::dropIfExists('jadwal_harian');
        Schema::dropIfExists('alumni');
        Schema::dropIfExists('konfigurasi');
        Schema::dropIfExists('hari_libur');
        Schema::dropIfExists('absensi');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('kelas');
    }
};
