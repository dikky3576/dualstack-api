<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Masuk atau daftar memakai Google Identity Services.
     *
     * Frontend mengirim ID token (credential). Token diverifikasi ke Google,
     * dan hanya diterima jika audience-nya adalah Client ID aplikasi ini
     * serta email-nya sudah diverifikasi oleh Google.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string', 'max:4096'],
        ]);

        // trim: spasi atau baris baru tersembunyi di .env membuat aud tidak cocok.
        $clientId = trim((string) config('services.google.client_id'));

        if ($clientId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Login Google belum dikonfigurasi.',
            ], 503);
        }

        try {
            $response = Http::timeout(10)->get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['id_token' => $validated['credential']]
            );
        } catch (\Throwable $e) {
            Log::error('Google tokeninfo request failed', [
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghubungi Google. Coba lagi.',
            ], 502);
        }

        $claims = $response->successful() ? $response->json() : null;

        if (! is_array($claims)) {
            Log::warning('Google login rejected', [
                'reason' => 'tokeninfo_rejected',
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 200),
            ]);

            return $this->invalidToken();
        }

        $emailVerified = filter_var(
            $claims['email_verified'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $reason = match (true) {
            ($claims['aud'] ?? null) !== $clientId => 'aud_mismatch',
            ! in_array(
                $claims['iss'] ?? null,
                ['accounts.google.com', 'https://accounts.google.com'],
                true
            ) => 'iss_invalid',
            (int) ($claims['exp'] ?? 0) < time() => 'expired',
            ! $emailVerified => 'email_not_verified',
            ! is_string($claims['email'] ?? null) || $claims['email'] === '' => 'email_missing',
            default => null,
        };

        if ($reason !== null) {
            // Client ID bukan rahasia, aman dicatat untuk membandingkan nilainya.
            Log::warning('Google login rejected', [
                'reason' => $reason,
                'token_aud' => $claims['aud'] ?? null,
                'expected_aud' => $clientId,
            ]);

            return $this->invalidToken();
        }

        $email = strtolower($claims['email']);

        $user = User::where('email', $email)->first();

        if (! $user) {
            $name = is_string($claims['name'] ?? null) && trim($claims['name']) !== ''
                ? trim($claims['name'])
                : Str::before($email, '@');

            // Akun Google tidak memakai password; nilai acak mencegah login
            // password sampai pengguna menetapkannya sendiri.
            $user = User::create([
                'name' => Str::limit($name, 100, ''),
                'email' => $email,
                'password' => Str::random(64),
            ]);

            // Muat ulang agar kolom bawaan database (role) ikut terbaca.
            $user->refresh();
        }

        $token = $user->createToken('dualstack-web')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login Google berhasil.',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role']),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    private function invalidToken(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Token Google tidak valid atau sudah kedaluwarsa.',
        ], 401);
    }
}
