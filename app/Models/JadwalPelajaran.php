<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalPelajaran extends Model
{
    use HasFactory;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = [
        'kelas_id',
        'guru_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'mata_pelajaran',
        'ruang',
        'keterangan',
    ];

    protected $casts = [
        'kelas_id' => 'integer',
        'guru_id' => 'integer',
        'hari' => 'integer',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function jurnalMengajarHarian(): HasMany
    {
        return $this->hasMany(JurnalMengajarHarian::class, 'jadwal_pelajaran_id');
    }
}
