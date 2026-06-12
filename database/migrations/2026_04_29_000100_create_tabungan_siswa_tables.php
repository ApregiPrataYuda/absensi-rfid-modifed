<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_tabungan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tabungan_siswa_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('jenis_tabungan_id')->constrained('jenis_tabungan')->restrictOnDelete();
            $table->unsignedBigInteger('saldo_cached')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'jenis_tabungan_id'], 'tabungan_siswa_unique_account');
            $table->index(['jenis_tabungan_id', 'is_active'], 'tabungan_siswa_type_active_idx');
        });

        Schema::create('tabungan_siswa_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('tabungan_siswa_accounts')->cascadeOnDelete();
            $table->string('nomor_bukti', 50)->unique();
            $table->dateTime('transacted_at')->index();
            $table->string('jenis_transaksi', 30)->index();
            $table->unsignedBigInteger('nominal');
            $table->unsignedBigInteger('saldo_sebelum')->default(0);
            $table->unsignedBigInteger('saldo_sesudah')->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('delete_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'transacted_at'], 'tabungan_siswa_account_date_idx');
        });

        Schema::create('tabungan_siswa_transaction_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('tabungan_siswa_transactions')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20)->index();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabungan_siswa_transaction_audits');
        Schema::dropIfExists('tabungan_siswa_transactions');
        Schema::dropIfExists('tabungan_siswa_accounts');
        Schema::dropIfExists('jenis_tabungan');
    }
};
