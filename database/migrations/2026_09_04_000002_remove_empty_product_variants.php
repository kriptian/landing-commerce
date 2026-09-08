<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_variants')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('variant_options')
                    ->whereColumn('variant_options.product_id', 'product_variants.product_id');
            })
            ->whereNull('price')
            ->whereNull('retail_price')
            ->whereNull('wholesale_price')
            ->whereNull('purchase_price')
            ->where(function ($query) {
                $query->whereNull('stock')->orWhere('stock', 0);
            })
            ->select(['id', 'options'])
            ->orderBy('id')
            ->chunkById(200, function ($variants) {
                $emptyIds = $variants
                    ->filter(fn ($variant) => json_decode($variant->options ?: '[]', true) === [])
                    ->pluck('id');

                if ($emptyIds->isNotEmpty()) {
                    DB::table('product_variants')->whereIn('id', $emptyIds)->delete();
                }
            });
    }

    public function down(): void
    {
        // Empty synthetic variants cannot be reconstructed meaningfully.
    }
};
