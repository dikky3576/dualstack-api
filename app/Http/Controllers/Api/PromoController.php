<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;

class PromoController extends Controller
{
    /**
     * Kupon yang ditandai publik dan masih bisa dipakai, untuk banner beranda.
     */
    public function index(): JsonResponse
    {
        $promos = Coupon::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->latest()
            ->limit(3)
            ->get(['code', 'type', 'value', 'min_purchase', 'max_discount', 'expires_at']);

        return response()->json([
            'success' => true,
            'data' => $promos,
        ]);
    }
}
