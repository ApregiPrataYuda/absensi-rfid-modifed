<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chat_links', function (Blueprint $table) {
            $table->dropUnique(['telegram_bot_id', 'telegram_chat_id']);
            $table->index(['telegram_bot_id', 'telegram_chat_id'], 'telegram_chat_links_bot_chat_idx');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_chat_links', function (Blueprint $table) {
            $table->dropIndex('telegram_chat_links_bot_chat_idx');
            $table->unique(['telegram_bot_id', 'telegram_chat_id']);
        });
    }
};
