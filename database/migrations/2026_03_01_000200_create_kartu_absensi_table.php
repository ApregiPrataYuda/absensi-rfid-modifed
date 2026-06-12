<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_absensi', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('code');
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('last_scanned_source', 50)->nullable();
            $table->timestamps();

            $table->unique(['type', 'code']);
            $table->index(['siswa_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_absensi');
    }
};
