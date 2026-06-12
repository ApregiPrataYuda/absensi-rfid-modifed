<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramChatLink extends Model
{
    use HasFactory;

    protected $table = 'telegram_chat_links';

    protected $fillable = [
        'siswa_id',
        'nisn_snapshot',
        'telegram_bot_id',
        'telegram_chat_id',
        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'chat_type',
        'linked_at',
        'last_interaction_at',
        'is_active',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
