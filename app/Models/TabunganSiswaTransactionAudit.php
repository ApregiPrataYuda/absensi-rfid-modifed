<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabunganSiswaTransactionAudit extends Model
{
    use HasFactory;

    protected $table = 'tabungan_siswa_transaction_audits';

    protected $fillable = [
        'transaction_id',
        'actor_user_id',
        'action',
        'old_data',
        'new_data',
        'note',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(TabunganSiswaTransaction::class, 'transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
