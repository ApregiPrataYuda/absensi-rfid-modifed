<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisPelanggaran extends Model
{
    use HasFactory;

    protected $table = 'jenis_pelanggaran';

    protected $fillable = [
        'tenant_id',
        'nama',
        'kategori',
        'poin',
        'is_active',
    ];

    protected $casts = [
        'poin' => 'integer',
        'is_active' => 'boolean',
    ];

    public function pelanggaranSiswa(): HasMany
    {
        return $this->hasMany(PoinPelanggaranSiswa::class, 'jenis_pelanggaran_id');
    }
}
