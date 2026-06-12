<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiPelajaran extends Model
{
    use HasFactory;

    protected $table = 'absensi_pelajaran';

    protected $fillable = [
        'sesi_pelajaran_id',
        'siswa_id',
        'status',
        'recorded_at',
        'method',
        'recorded_by_user_id',
        'note',
    ];

    protected $casts = [
        'sesi_pelajaran_id' => 'integer',
        'siswa_id' => 'integer',
        'recorded_by_user_id' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function sesiPelajaran(): BelongsTo
    {
        return $this->belongsTo(SesiPelajaran::class, 'sesi_pelajaran_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
