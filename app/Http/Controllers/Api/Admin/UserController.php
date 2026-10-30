<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Menampilkan seluruh pengguna.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['customer', 'admin'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $users = User::query()
            ->select('id', 'name', 'email', 'role', 'created_at')
            ->withCount('orders')
            ->when($request->filled('search'), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), function ($query) use ($validated) {
                $query->where('role', $validated['role']);
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengguna berhasil diambil.',
            'data' => $users,
        ]);
    }

    /**
     * Menampilkan detail pengguna.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::query()
            ->withCount('orders')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail pengguna berhasil diambil.',
            'data' => $user->only([
                'id',
                'name',
                'email',
                'role',
                'created_at',
                'orders_count',
            ]),
        ]);
    }

    /**
     * Memperbarui profil pengguna.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil pengguna berhasil diperbarui.',
            'data' => $user->only([
                'id',
                'name',
                'email',
                'role',
            ]),
        ]);
    }

    /**
     * Mengubah role pengguna.
     */
    public function updateRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['customer', 'admin'])],
        ]);

        $user = User::findOrFail($id);

        // Administrator tidak boleh mengubah role akunnya sendiri.
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Role akun sendiri tidak dapat diubah melalui endpoint ini.',
            ], 422);
        }

        // Lindungi agar selalu ada minimal satu administrator.
        if (
            $user->role === 'admin' &&
            $validated['role'] === 'customer'
        ) {
            $adminCount = User::where('role', 'admin')->count();

            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menurunkan role administrator terakhir.',
                ], 422);
            }
        }

        $user->role = $validated['role'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Role pengguna berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}