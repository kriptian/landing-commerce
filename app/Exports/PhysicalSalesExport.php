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
        
        // Aplicar filtro de búsqueda por número de venta o nombre de producto
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                  ->orWhereHas('items', function ($itemQuery) use ($search) {
                      $itemQuery->where('product_name', 'like', "%{$search}%")
                                 ->orWhereHas('product', function ($productQuery) use ($search) {
                                     $productQuery->where('name', 'like', "%{$search}%");
                                 });
                  });
            });
        }
        
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $appTz = config('app.timezone', 'America/Bogota');
            $startLocal = \Carbon\Carbon::parse($this->filters['start_date'], $appTz)->startOfDay();
            $endLocal = \Carbon\Carbon::parse($this->filters['end_date'], $appTz)->endOfDay();
            
            // Convertir a UTC para consultar la base de datos
            $startUtc = $startLocal->copy()->timezone('UTC');
            $endExclusiveUtc = $endLocal->copy()->addDay()->startOfDay()->timezone('UTC');

            $query->whereBetween('created_at', [$startUtc, $endExclusiveUtc]);
        }
        
        $sales = $query->orderByDesc('created_at')->get();

        $rows = collect();
        foreach ($sales as $sale) {
            // Si la venta no tiene items, exportarla igual con datos básicos
            if ($sale->items->isEmpty()) {
                // Verificar si el filtro de búsqueda aplica a la venta vacía (solo por número)
                if (!empty($this->filters['search'])) {
                    $search = strtolower($this->filters['search']);
                    if (!str_contains(strtolower($sale->sale_number), $search)) {
                        continue;
                    }
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
                    'Sin productos',
                    0,
                    0,
                    0,
                    '-',
                    '-', // Sin ganancia porque no hay items
                    $sale->notes ?? '',
                ]);
                continue;
            }
            
            foreach ($sale->items as $item) {
                // --- FILTRADO ESTRICTO DE ITEMS ---
                // Si hay un término de búsqueda, mostrar la fila SOLO si:
                // 1. El item coincide con la búsqueda
                // 2. O el número de venta coincide con la búsqueda (en cuyo caso mostramos todos los items de esa venta)
                if (!empty($this->filters['search'])) {
                    $search = strtolower($this->filters['search']);
                    $itemMatches = false;
                    
                    // Chequear nombre del producto en el item
                    if ($item->product_name && str_contains(strtolower($item->product_name), $search)) {
                        $itemMatches = true;
                    } 
                    // Chequear nombre del producto relacion
                    elseif ($item->product && str_contains(strtolower($item->product->name), $search)) {
                        $itemMatches = true;
                    }
                    
                    // Chequear número de venta
                    $saleMatches = str_contains(strtolower($sale->sale_number), $search);

                    // Si no coincide ni el item ni la venta, saltar este item
                    if (!$itemMatches && !$saleMatches) {
                        continue;
                    }
                }
                // ---------------------------------

                $name = $item->product_name ?? optional($item->product)->name;
                $options = $item->variant_options ?? optional($item->variant)->options;
                $optionsText = '';
                if (is_array($options) || $options instanceof \ArrayAccess) {
                    $optionsText = collect($options)->map(fn($val, $key) => "$key: $val")->implode(', ');
                }
                $fullName = trim($name . ($optionsText ? " (".$optionsText.")" : ''));
                
                // Calcular Ganancia - CRÍTICO: Exportar TODAS las ventas sin importar si tienen ganancia o no
                $cost = 0;
                $profitText = 0; // Cambiar a 0 en lugar de '-' para que siempre se exporte
                
                // Prioridad 1: Usar el snapshot del costo guardado en el item al momento de la venta
                if (!is_null($item->purchase_price) && $item->purchase_price !== '' && $item->purchase_price > 0) {
                    $cost = (float) $item->purchase_price;
                }
                // Prioridad 2: Intentar obtener costo de la variante actual (fallback)
                elseif ($item->variant && $item->variant->purchase_price > 0) {
                    $cost = (float) $item->variant->purchase_price;
                }
                // Prioridad 3: Intentar obtener costo del producto actual (fallback)
                elseif ($item->product && $item->product->purchase_price > 0) {
                    $cost = (float) $item->product->purchase_price;
                }

                // CRÍTICO: Calcular ganancia SIEMPRE, incluso si es 0 o negativa
                // Esto asegura que TODAS las ventas se exporten
                if ($item->unit_price > 0) {
                    $profitVal = (($item->unit_price - $cost) * $item->quantity);
                    $profitText = round($profitVal, 2); // Redondear a 2 decimales
                } else {
                    // Si no hay precio unitario, ganancia es 0
                    $profitText = 0;
                }

                // Calcular Descuento del Item
                $itemDiscountText = '-';
                if ($item->original_price && $item->original_price > $item->unit_price) {
                    $discountVal = ($item->original_price - $item->unit_price) * $item->quantity;
                    $itemDiscountText = round($discountVal, 2);
                }

                // CRÍTICO: Agregar TODAS las filas sin importar el valor de ganancia
                $rows->push([
                    $sale->sale_number,
                    $sale->user->name ?? 'N/A',
                    $sale->created_at->timezone(config('app.timezone', 'America/Bogota'))->format('Y-m-d H:i'),
                    $sale->payment_method,
                    $sale->subtotal,
                    $sale->tax,
                    $sale->delivery_cost ?? 0,
                    $sale->discount,
                    $sale->total,
                    $fullName,
                    $item->quantity,
                    $item->unit_price,
                    $item->subtotal,
                    $itemDiscountText, // Columna Descuento Item
                    $profitText, // Columna Ganancia (siempre se exporta, incluso si es 0)
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
            'Costo de Envío',
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

