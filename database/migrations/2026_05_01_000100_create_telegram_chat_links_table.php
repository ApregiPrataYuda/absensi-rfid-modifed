<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chat_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('nisn_snapshot', 32)->index();
            $table->string('telegram_bot_id', 64)->index();
            $table->bigInteger('telegram_chat_id')->index();
            $table->bigInteger('telegram_user_id')->nullable()->index();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->string('chat_type', 20)->default('private');
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['telegram_bot_id', 'siswa_id']);
            $table->unique(['telegram_bot_id', 'telegram_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_links');
    }
};
