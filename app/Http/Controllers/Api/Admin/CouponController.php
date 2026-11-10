<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /** Tanpa 0/O dan 1/I/L: kode sering dibacakan dan diketik ulang. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

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

    /**
     * Membuat banyak kupon sekaligus dengan aturan yang sama dan kode acak,
     * mis. satu kode untuk satu penerima.
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate([
            'count' => ['required', 'integer', 'min:2', 'max:200'],
            'prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]*$/'],
        ], [
            'prefix.regex' => 'Awalan hanya boleh huruf, angka, strip, dan garis bawah.',
        ]);

        $data = $this->validated($request, null, true);

        $count = (int) $request->input('count');
        $prefix = strtoupper((string) $request->input('prefix', ''));

        $codes = DB::transaction(function () use ($count, $prefix, $data) {
            $codes = [];

            // Hampir tidak pernah berulang; batas percobaan menjaga dari loop tak berujung.
            for ($attempt = 0; count($codes) < $count && $attempt < 10; $attempt++) {
                $candidates = [];

                while (count($candidates) < $count - count($codes)) {
                    $candidates[$prefix . $this->randomCode()] = true;
                }

                $candidates = array_keys($candidates);

                $taken = Coupon::whereIn('code', $candidates)->pluck('code')->all();

                foreach (array_diff($candidates, $taken) as $code) {
                    $codes[] = $code;
                }
            }

            if (count($codes) < $count) {
                throw ValidationException::withMessages([
                    'count' => ['Kode unik tidak cukup. Coba awalan lain.'],
                ]);
            }

            foreach ($codes as $code) {
                Coupon::create($data + ['code' => $code]);
            }

            return $codes;
        });

        return response()->json([
            'success' => true,
            'message' => count($codes) . ' kupon dibuat.',
            'data' => ['codes' => $codes],
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

    private function randomCode(int $length = 8): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * @param  bool  $bulk  tanpa field kode (kode dibuat server)
     */
    private function validated(Request $request, ?Coupon $coupon = null, bool $bulk = false): array
    {
        $rules = [
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'min_purchase' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_discount' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ];

        if (! $bulk) {
            $request->merge([
                'code' => Coupon::normalize((string) $request->input('code', '')),
            ]);

            $rules['code'] = [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ];
        }

        $data = $request->validate($rules, [
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

        // Kode massal dibagikan satu per satu, jadi tidak pernah dipajang publik.
        if ($bulk) {
            $data['is_public'] = false;
        }

        return $data;
    }
}
