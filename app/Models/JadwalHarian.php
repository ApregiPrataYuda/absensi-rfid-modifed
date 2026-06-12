<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class JadwalHarian extends Model
{
    use HasFactory;

    protected $table = 'jadwal_harian';

    protected $fillable = [
        'kelas_id',
        'hari',
        'is_libur',
        'jam_masuk_mulai',
        'jam_masuk_akhir',
        'jam_masuk_telat',
        'jam_pulang_mulai',
        'jam_pulang_akhir',
        'keterangan',
    ];

    protected $casts = [
        'kelas_id' => 'integer',
        'hari' => 'integer',
        'is_libur' => 'boolean',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
