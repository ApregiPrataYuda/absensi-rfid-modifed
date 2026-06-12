<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'kelas',
        'jenis_kelamin',
        'tanggal_lahir',
        'agama',
        'no_hp',
        'alamat',
        'avatar_path',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'tanggal_lahir' => 'date',
            'password' => 'hashed',
        ];
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'guru_id');
    }

    public function jurnalMengajarHarian(): HasMany
    {
        return $this->hasMany(JurnalMengajarHarian::class, 'guru_id');
    }

    public function performedSavingsTransactions(): HasMany
    {
        return $this->hasMany(TabunganSiswaTransaction::class, 'performed_by_user_id');
    }

    public function updatedSavingsTransactions(): HasMany
    {
        return $this->hasMany(TabunganSiswaTransaction::class, 'updated_by_user_id');
    }

    public function deletedSavingsTransactions(): HasMany
    {
        return $this->hasMany(TabunganSiswaTransaction::class, 'deleted_by_user_id');
    }
}
