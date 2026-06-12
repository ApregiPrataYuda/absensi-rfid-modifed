<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiPelajaran extends Model
{
    use HasFactory;

    protected $table = 'sesi_pelajaran';

    protected $fillable = [
        'tanggal',
        'jadwal_pelajaran_id',
        'kelas_id',
        'guru_id',
        'status',
        'opened_by_user_id',
        'opened_at',
        'closed_by_user_id',
        'closed_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jadwal_pelajaran_id' => 'integer',
        'kelas_id' => 'integer',
        'guru_id' => 'integer',
        'opened_by_user_id' => 'integer',
        'opened_at' => 'datetime',
        'closed_by_user_id' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_pelajaran_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function absensiPelajaran(): HasMany
    {
        return $this->hasMany(AbsensiPelajaran::class, 'sesi_pelajaran_id');
    }
}
