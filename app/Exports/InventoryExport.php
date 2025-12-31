<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryExport implements FromCollection, WithHeadings
{
    private int $storeId;

    public function __construct(int $storeId)
    {
        $this->storeId = $storeId;
    }

    public function collection(): Collection
    {
        $products = Product::where('store_id', $this->storeId)
            ->with('variants')
            ->orderBy('name')
            ->get();

        $rows = collect();

        foreach ($products as $product) {
            $hasVariants = $product->variants && $product->variants->count() > 0;

            if ($hasVariants) {
                // --- FILAS DE VARIANTES SOLAMENTE (Flattened) ---
                foreach ($product->variants as $variant) {
                    // Precios con Fallback (Herencia)
                    $buy = (float) (($variant->purchase_price > 0 ? $variant->purchase_price : $product->purchase_price) ?? 0);
                    $sell = (float) (($variant->price > 0 ? $variant->price : $product->price) ?? 0);
                    
                    // Cálculos
                    $profit = ($buy > 0 && $sell > 0) ? round($sell - $buy, 2) : null;
                    $pct = ($buy > 0 && $sell > 0) ? round((($sell - $buy) / $buy) * 100, 2) : null;

                    // Formatear nombre de variante: "Producto - Opción: Valor"
                    $variantName = $product->name . " - " . $this->formatVariantName($variant->options ?? []);
                    if ($variant->sku) $variantName .= " (SKU: {$variant->sku})";

                    $rows->push([
                        $variantName,
                        (int) ($variant->stock ?? 0),
                        $buy,
                        $sell,
                        $pct !== null ? $pct . '%' : null,
                        $profit,
                    ]);
                }

            } else {
                // --- PRODUCTO SIMPLE (Lógica Original) ---
                $buy = (float) ($product->purchase_price ?? 0);
                $sell = (float) ($product->price ?? 0);
                $pct = ($buy > 0 && $sell > 0) ? round((($sell - $buy) / $buy) * 100, 2) : null;
                $profit = ($buy > 0 && $sell > 0) ? round($sell - $buy, 2) : null;

                $rows->push([
                    $product->name,
                    (int) ($product->quantity ?? 0),
                    $buy,
                    $sell,
                    $pct !== null ? $pct . '%' : null,
                    $profit,
                ]);
            }
        }

        // Evitar archivo sin filas
        return $rows->isEmpty() ? collect([['', '', '', '', '', '']]) : $rows;
    }

    private function formatVariantName($options): string
    {
        if (empty($options) || !is_array($options)) return 'Variante';
        return implode(', ', array_map(
            fn($k, $v) => "{$k}: {$v}",
            array_keys($options),
            $options
        ));
    }

    public function headings(): array
    {
        return [
            'PRODUCTO / VARIANTE',
            'STOCK ACTUAL',
            '$ COMPRA (Unitario/Total)',
            '$ VENTA',
            '% GAN.',
            '$ GAN.',
        ];
    }
}


