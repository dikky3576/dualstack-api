<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Mengirim link atur ulang password ke email.
     *
     * Responsnya selalu sama, baik email terdaftar maupun tidak, supaya
     * endpoint ini tidak bisa dipakai untuk menebak email yang terdaftar.
     */
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Password::sendResetLink(['email' => $validated['email']]);
        } catch (\Throwable $e) {
            // Biasanya konfigurasi email (MAIL_*) di server belum benar.
            Log::error('Password reset email failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, link untuk mengatur ulang password sudah dikirim.',
        ]);
    }

    /**
     * Mengatur password baru memakai token dari email.
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    // Link reset dikirim ke emailnya, jadi email itu terbukti miliknya.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);

                // Kolom ini baru ada setelah migrasinya dijalankan.
                if (array_key_exists('password_set', $user->getAttributes())) {
                    $user->password_set = true;
                }

                $user->save();

                // Cabut semua sesi login yang masih aktif.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Link atur ulang password tidak valid atau sudah kedaluwarsa. Minta link baru.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diatur ulang. Silakan masuk.',
        ]);
    }
}
