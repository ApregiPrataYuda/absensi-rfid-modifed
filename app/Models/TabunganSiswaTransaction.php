<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TabunganSiswaTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_SETORAN = 'setoran';
    public const TYPE_PENARIKAN = 'penarikan';
    public const TYPE_PENYESUAIAN_MASUK = 'penyesuaian_masuk';
    public const TYPE_PENYESUAIAN_KELUAR = 'penyesuaian_keluar';

    protected $table = 'tabungan_siswa_transactions';

    protected $fillable = [
        'account_id',
        'nomor_bukti',
        'transacted_at',
        'jenis_transaksi',
        'nominal',
        'saldo_sebelum',
        'saldo_sesudah',
        'keterangan',
        'performed_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
        'delete_reason',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'nominal' => 'integer',
        'saldo_sebelum' => 'integer',
        'saldo_sesudah' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(TabunganSiswaAccount::class, 'account_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(TabunganSiswaTransactionAudit::class, 'transaction_id');
    }

    public function signedAmount(): int
    {
        return match ((string) $this->jenis_transaksi) {
            self::TYPE_PENARIKAN, self::TYPE_PENYESUAIAN_KELUAR => -1 * (int) $this->nominal,
            default => (int) $this->nominal,
        };
    }
}
