<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoinPelanggaranSiswa extends Model
{
    use HasFactory;

    protected $table = 'poin_pelanggaran_siswa';

    protected $fillable = [
        'tenant_id',
        'siswa_id',
        'jenis_pelanggaran_id',
        'nama_pelanggaran',
        'poin',
        'tanggal',
        'catatan',
        'input_by_user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'poin' => 'integer',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function jenisPelanggaran(): BelongsTo
    {
        return $this->belongsTo(JenisPelanggaran::class, 'jenis_pelanggaran_id');
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by_user_id');
    }
}
