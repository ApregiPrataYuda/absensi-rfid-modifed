<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KartuAbsensi extends Model
{
    use HasFactory;

    protected $table = 'kartu_absensi';

    public const TYPE_QR = 'qr';
    public const TYPE_RFID = 'rfid';

    protected $fillable = [
        'type',
        'code',
        'siswa_id',
        'last_scanned_at',
        'last_scanned_source',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
