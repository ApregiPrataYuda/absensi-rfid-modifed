<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalMengajarHarian extends Model
{
    use HasFactory;

    protected $table = 'jurnal_mengajar_harian';

    protected $fillable = [
        'tanggal',
        'kelas_id',
        'guru_id',
        'jadwal_pelajaran_id',
        'mata_pelajaran',
        'topik_materi',
        'ringkasan_pembelajaran',
        'tugas_siswa',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'kelas_id' => 'integer',
        'guru_id' => 'integer',
        'jadwal_pelajaran_id' => 'integer',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_pelajaran_id');
    }
}
