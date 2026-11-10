<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Sitemap XML untuk mesin pencari: halaman publik dan semua template
     * yang terbit. Alamatnya mengarah ke frontend (FRONTEND_URL).
     */
    public function index(): Response
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        $urls = [
            ['loc' => $base.'/', 'priority' => '1.0'],
            ['loc' => $base.'/products', 'priority' => '0.9'],
            ['loc' => $base.'/contact', 'priority' => '0.4'],
            ['loc' => $base.'/terms', 'priority' => '0.3'],
            ['loc' => $base.'/refund', 'priority' => '0.3'],
            ['loc' => $base.'/privacy', 'priority' => '0.3'],
        ];

        // Produk yang dihapus (soft delete) otomatis tidak ikut.
        Product::query()
            ->where('status', 'published')
            ->select('id', 'slug', 'updated_at')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$urls, $base) {
                foreach ($products as $product) {
                    $urls[] = [
                        'loc' => $base.'/products/'.rawurlencode($product->slug),
                        'lastmod' => $product->updated_at?->toAtomString(),
                        'priority' => '0.8',
                    ];
                }
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc>\n";

            if (! empty($url['lastmod'])) {
                $xml .= '    <lastmod>'.$url['lastmod']."</lastmod>\n";
            }

            $xml .= '    <priority>'.$url['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
