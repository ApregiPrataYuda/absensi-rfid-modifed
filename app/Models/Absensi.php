<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    use HasFactory;

    protected $table = 'absensi';

    protected $fillable = [
        'tanggal',
        'siswa_id',
        'nisn',
        'nama',
        'kelas',
        'jam_datang',
        'jam_pulang',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jam_datang' => 'string',
        'jam_pulang' => 'string',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
