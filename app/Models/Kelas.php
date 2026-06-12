<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';

    protected $fillable = [
        'nama',
        'wali_kelas',
        'kapasitas',
    ];

    protected $casts = [
        'wali_kelas' => 'integer',
        'kapasitas' => 'integer',
    ];

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'kelas', 'nama');
    }

    public function waliKelasUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas');
    }

    public function jadwalHarian(): HasMany
    {
        return $this->hasMany(JadwalHarian::class, 'kelas_id');
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'kelas_id');
    }

    public function jurnalMengajarHarian(): HasMany
    {
        return $this->hasMany(JurnalMengajarHarian::class, 'kelas_id');
    }
}
