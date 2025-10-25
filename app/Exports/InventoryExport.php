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
            ->orderBy('name')
            ->get(['id','name','price','purchase_price','quantity']);

        $rows = collect();
        foreach ($products as $product) {
            $buy = (float) ($product->purchase_price ?? 0);
            $sell = (float) ($product->price ?? 0);
            $pct = ($buy > 0 && $sell > 0) ? round((($sell - $buy) / $buy) * 100, 2) : null;
            $profit = ($buy > 0 && $sell > 0) ? round($sell - $buy, 2) : null;
            $rows->push([
                $product->name,
                (int) ($product->quantity ?? 0),
                $buy,
                $sell,
                $pct,
                $profit,
            ]);
        }
        // Evitar archivo sin filas: si no hay datos, devolvemos al menos una fila vacía
        return $rows->isEmpty() ? collect([['', '', '', '', '', '']]) : $rows;
    }

    public function headings(): array
    {
        return [
            'PRODUCTO / VARIANTE',
            'STOCK ACTUAL',
            '$ COMPRA',
            '$ VENTA',
            '% GAN.',
            '$ GAN.',
        ];
    }
}


