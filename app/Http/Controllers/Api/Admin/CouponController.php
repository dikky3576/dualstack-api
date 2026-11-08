<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->input('search'));
                $q->where('code', 'like', '%' . $term . '%');
            })
            ->latest()
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $coupons]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $coupon = Coupon::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Kupon dibuat.',
            'data' => $coupon->fresh(),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $coupon->update($this->validated($request, $coupon));

        return response()->json([
            'success' => true,
            'message' => 'Kupon diperbarui.',
            'data' => $coupon->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        // Riwayat pesanan menyimpan salinan kode, jadi aman dihapus;
        // coupon_id di pesanan menjadi null.
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kupon dihapus.',
        ]);
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge([
            'code' => Coupon::normalize((string) $request->input('code', '')),
        ]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'min_purchase' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_discount' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'code.regex' => 'Kode hanya boleh huruf, angka, strip, dan garis bawah.',
        ]);

        if ($data['type'] === Coupon::TYPE_PERCENT && (float) $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => ['Persen tidak boleh lebih dari 100.'],
            ]);
        }

        // Batas potongan hanya bermakna untuk kupon persen.
        if ($data['type'] === Coupon::TYPE_FIXED) {
            $data['max_discount'] = null;
        }

        $data['min_purchase'] = $data['min_purchase'] ?? 0;

        return $data;
    }
}
