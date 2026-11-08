<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Menampilkan profil pengguna yang sedang login.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diambil.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
                'orders_count' => $user->orders()->count(),
                'has_password' => $user->hasPassword(),
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }

    /**
     * Memperbarui nama dan email pengguna.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $emailChanged = strcasecmp($user->email, $validated['email']) !== 0;

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Email baru belum terbukti milik pengguna: minta verifikasi ulang.
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::error('Verification email failed', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }

    /**
     * Mengganti password pengguna. Akun yang dibuat lewat Google belum punya
     * password, jadi untuk pertama kalinya cukup menetapkan password baru.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasPassword = $user->hasPassword();

        $validated = $request->validate([
            'current_password' => [Rule::requiredIf($hasPassword), 'nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (
            $hasPassword &&
            ! Hash::check($validated['current_password'], $user->password)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        // Hanya bila kolomnya sudah ada (migrasi sudah dijalankan).
        if (array_key_exists('password_set', $user->getAttributes())) {
            $user->password_set = true;
        }

        $user->save();

        // Cabut seluruh token agar pengguna perlu login ulang.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Silakan login kembali.',
        ]);
    }
}
