<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class PurgeTrashedProducts extends Command
{
    protected $signature = 'products:purge-trashed {--dry-run : Hanya tampilkan yang akan dihapus}';

    protected $description = 'Hapus permanen produk yang sudah di-soft-delete dan belum pernah dipesan.';

    public function handle(): int
    {
        $trashed = Product::onlyTrashed()->get();

        if ($trashed->isEmpty()) {
            $this->info('Tidak ada produk yang sudah dihapus.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $removed = 0;
        $kept = 0;

        foreach ($trashed as $product) {
            if ($product->hasOrders()) {
                $kept++;
                $this->line("Disimpan (ada di riwayat pesanan): #{$product->id} {$product->name}");

                continue;
            }

            if ($dryRun) {
                $removed++;
                $this->line("Akan dihapus permanen: #{$product->id} {$product->name}");

                continue;
            }

            $product->purge();
            $removed++;
            $this->line("Dihapus permanen: #{$product->id} {$product->name}");
        }

        $this->newLine();
        $this->info(
            ($dryRun ? 'Simulasi: ' : '')
            ."{$removed} dihapus permanen, {$kept} disimpan karena ada di riwayat pesanan."
        );

        return self::SUCCESS;
    }
}
