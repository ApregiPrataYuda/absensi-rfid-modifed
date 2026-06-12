<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'name',
        'serial_number',
        'mac_address',
        'device_token',
        'firmware_version',
        'status',
        'last_seen',
        'activated_at',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
