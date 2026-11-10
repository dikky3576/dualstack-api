<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'in:7,30,90'],
        ]);

        $days = (int) ($validated['days'] ?? 30);

        $today = now('Asia/Jakarta')->startOfDay();
        $since = $today->copy()->subDays($days - 1)->toDateString();

        // Kunjungan seluruh situs per hari.
        $site = DB::table('daily_views')
            ->where('type', 'site')
            ->where('date', '>=', $since)
            ->orderBy('date')
            ->pluck('views', 'date');

        // Hari tanpa kunjungan tetap muncul dengan nilai 0.
        $daily = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $today->copy()->subDays($days - 1 - $i)->toDateString();
            $daily[] = ['date' => $date, 'views' => (int) ($site[$date] ?? 0)];
        }

        $topProducts = DB::table('daily_views')
            ->join('products', 'products.id', '=', 'daily_views.product_id')
            ->where('daily_views.type', 'product')
            ->where('daily_views.date', '>=', $since)
            ->whereNull('products.deleted_at')
            ->groupBy('products.id', 'products.name', 'products.slug')
            ->select('products.id', 'products.name', 'products.slug')
            ->selectRaw('SUM(daily_views.views) as views')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'views' => (int) $row->views,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'total_views' => array_sum(array_column($daily, 'views')),
                'today_views' => end($daily)['views'] ?? 0,
                'daily' => $daily,
                'top_products' => $topProducts,
            ],
        ]);
    }
}
