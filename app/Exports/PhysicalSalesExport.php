<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Auth;

use Maatwebsite\Excel\Concerns\WithTitle;

class PhysicalSalesExport implements FromCollection, WithHeadings, WithTitle
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $query = Auth::user()->store->physicalSales()->with(['user', 'items', 'items.product', 'items.variant']);
        
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $appTz = config('app.timezone', 'America/Bogota');
            $startLocal = \Carbon\Carbon::parse($this->filters['start_date'], $appTz)->startOfDay();
            $endLocal = \Carbon\Carbon::parse($this->filters['end_date'], $appTz)->endOfDay();
            
            $query->whereRaw(
                "DATE(CONVERT_TZ(created_at, 'UTC', ?)) BETWEEN ? AND ?",
                [$appTz, $startLocal->toDateString(), $endLocal->toDateString()]
            );
        }
        
        $sales = $query->orderByDesc('created_at')->get();

        $rows = collect();
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $name = $item->product_name ?? optional($item->product)->name;
                $options = $item->variant_options ?? optional($item->variant)->options;
                $optionsText = '';
                if (is_array($options) || $options instanceof \ArrayAccess) {
                    $optionsText = collect($options)->map(fn($val, $key) => "$key: $val")->implode(', ');
                }
                $fullName = trim($name . ($optionsText ? " (".$optionsText.")" : ''));

                // Calcular Ganancia
                $cost = 0;
                $profitText = '-'; // Por defecto guión si no hay costo
                
                // Intentar obtener costo
                if ($item->variant && $item->variant->purchase_price > 0) {
                    $cost = $item->variant->purchase_price;
                } elseif ($item->product && $item->product->purchase_price > 0) {
                    $cost = $item->product->purchase_price;
                }

                if ($cost > 0) {
                    $profitVal = ($item->unit_price - $cost) * $item->quantity;
                    $profitText = $profitVal; 
                }

                // Calcular Descuento del Item
                $itemDiscountText = '-';
                if ($item->original_price > $item->unit_price) {
                    $discountVal = ($item->original_price - $item->unit_price) * $item->quantity;
                    $itemDiscountText = $discountVal;
                }

                $rows->push([
                    $sale->sale_number,
                    $sale->user->name ?? 'N/A',
                    $sale->created_at->timezone(config('app.timezone', 'America/Bogota'))->format('Y-m-d H:i'),
                    $sale->payment_method,
                    $sale->subtotal,
                    $sale->tax,
                    $sale->discount,
                    $sale->total,
                    $fullName,
                    $item->quantity,
                    $item->unit_price,
                    $item->subtotal,
                    $itemDiscountText, // Columna Descuento Item
                    $profitText, // Columna Ganancia
                    $sale->notes ?? '',
                ]);
            }
        }
        
        return $rows->isEmpty() ? collect([[null,null,null,null,null,null,null,null,null,null,null,null,null,null]]) : $rows;
    }

    public function headings(): array
    {
        return [
            'Número de Venta',
            'Vendedor',
            'Fecha',
            'Método de Pago',
            'Subtotal',
            'Impuesto',
            'Descuento',
            'Total',
            'Producto',
            'Cantidad',
            'Precio Unitario',
            'Subtotal Item',
            'Descuento Item',
            'Ganancia',
            'Notas',
        ];
    }
    public function title(): string
    {
        return 'Ventas Físicas';
    }
}

