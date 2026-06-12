<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('serial_number')->unique();
            $table->string('mac_address')->nullable();
            $table->string('device_token', 100)->nullable()->unique();
            $table->string('firmware_version')->nullable();
            $table->enum('status', ['pending', 'active', 'inactive', 'revoked'])->default('pending')->index();
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('uid');
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['device_id', 'scanned_at']);
            $table->index('uid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('devices');
    }
};
