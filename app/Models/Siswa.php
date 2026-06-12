<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswa';

    protected $fillable = [
        'nama',
        'nisn',
        'jenis_kelamin',
        'tanggal_lahir',
        'agama',
        'nama_ayah',
        'nama_ibu',
        'no_hp',
        'kelas',
        'alamat',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function kelasRef(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas', 'nama');
    }

    public function kartuAbsensi(): HasMany
    {
        return $this->hasMany(KartuAbsensi::class);
    }

    public function tabunganAccounts(): HasMany
    {
        return $this->hasMany(TabunganSiswaAccount::class, 'siswa_id');
    }

    public function telegramChatLinks(): HasMany
    {
        return $this->hasMany(TelegramChatLink::class, 'siswa_id');
    }
}
