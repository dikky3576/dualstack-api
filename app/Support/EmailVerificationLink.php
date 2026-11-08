<?php

namespace App\Support;

use App\Models\User;

/**
 * Link verifikasi email yang mengarah ke frontend dan ditandatangani HMAC
 * memakai APP_KEY. Tidak bergantung pada URL API (proxy, http/https), karena
 * frontend yang mengirim parameternya kembali ke API.
 */
final class EmailVerificationLink
{
    /** Masa berlaku link, dalam menit. */
    public const TTL_MINUTES = 1440;

    public static function url(User $user): string
    {
        $expires = now()->addMinutes(self::TTL_MINUTES)->getTimestamp();
        $hash = sha1($user->getEmailForVerification());

        $query = http_build_query([
            'id' => $user->getKey(),
            'expires' => $expires,
            'hash' => $hash,
            'signature' => self::sign($user->getKey(), $hash, $expires),
        ]);

        return rtrim((string) config('app.frontend_url'), '/').'/verify-email?'.$query;
    }

    /**
     * Link valid bila belum kedaluwarsa, email-nya masih sama dengan saat
     * link dibuat, dan tanda tangannya cocok.
     */
    public static function isValid(User $user, string $hash, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return false;
        }

        return hash_equals(self::sign($user->getKey(), $hash, $expires), $signature);
    }

    private static function sign(int|string $id, string $hash, int $expires): string
    {
        return hash_hmac('sha256', "{$id}|{$hash}|{$expires}", (string) config('app.key'));
    }
}
