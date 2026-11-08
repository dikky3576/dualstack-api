<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set' => 'boolean',
        ];
    }

    /**
     * Apakah pemilik akun sudah menetapkan password sendiri. Akun yang dibuat
     * lewat Google belum.
     *
     * Gagal dengan aman: bila kolom password_set belum ada (migrasi belum
     * dijalankan), dianggap sudah punya password supaya password lama tetap
     * diminta saat mengganti password.
     */
    public function hasPassword(): bool
    {
        return ! array_key_exists('password_set', $this->attributes)
            || (bool) $this->attributes['password_set'];
    }

    /**
     * Dipakai di respons API sebagai `email_verified` (boolean).
     */
    protected function emailVerified(): Attribute
    {
        return Attribute::get(fn () => $this->hasVerifiedEmail());
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
