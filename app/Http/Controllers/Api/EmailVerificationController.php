<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EmailVerificationLink;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Memverifikasi email dari link di email. Tidak perlu login: pemilik
     * email bisa membukanya dari perangkat lain.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'expires' => ['required', 'integer'],
            'hash' => ['required', 'string', 'max:64'],
            'signature' => ['required', 'string', 'max:128'],
        ]);

        $user = User::find($validated['id']);

        if (
            ! $user ||
            ! EmailVerificationLink::isValid(
                $user,
                $validated['hash'],
                (int) $validated['expires'],
                $validated['signature']
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Link verifikasi tidak valid atau sudah kedaluwarsa. Kirim ulang email verifikasi.',
            ], 422);
        }

        // Aman dibuka dua kali: yang sudah terverifikasi tetap dianggap berhasil.
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil diverifikasi.',
        ]);
    }

    /**
     * Mengirim ulang email verifikasi untuk pengguna yang sedang login.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email kamu sudah terverifikasi.',
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::error('Verification email failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Email verifikasi tidak dapat dikirim sekarang. Coba lagi nanti.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verifikasi sudah dikirim. Cek kotak masuk dan folder spam.',
        ]);
    }
}
