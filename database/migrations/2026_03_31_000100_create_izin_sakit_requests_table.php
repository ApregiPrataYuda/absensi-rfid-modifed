<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin_sakit_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('jenis', 20)->index(); // izin / sakit
            $table->date('tanggal_mulai')->index();
            $table->date('tanggal_selesai')->index();
            $table->text('alasan');
            $table->string('status', 20)->default('pending')->index(); // pending / approved / rejected
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal_mulai']);
            $table->index(['siswa_id', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_sakit_requests');
    }
};
