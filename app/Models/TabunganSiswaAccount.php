<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TabunganSiswaAccount extends Model
{
    use HasFactory;

    protected $table = 'tabungan_siswa_accounts';

    protected $fillable = [
        'siswa_id',
        'jenis_tabungan_id',
        'nomor_rekening',
        'saldo_cached',
        'is_active',
        'opened_at',
    ];

    protected $casts = [
        'saldo_cached' => 'integer',
        'is_active' => 'boolean',
        'opened_at' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function jenisTabungan(): BelongsTo
    {
        return $this->belongsTo(JenisTabungan::class, 'jenis_tabungan_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TabunganSiswaTransaction::class, 'account_id');
    }
}
