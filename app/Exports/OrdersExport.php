<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Auth;

class OrdersExport implements FromCollection, WithHeadings
{
    protected $filters;

    // El constructor recibe los filtros que le mandamos desde el controlador
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $query = Auth::user()->store->orders()->with(['items', 'items.product', 'items.variant', 'coupon']);
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $appTz = config('app.timezone', 'America/Bogota');
            $startLocal = \Carbon\Carbon::parse($this->filters['start_date'], $appTz)->startOfDay();
            $endLocal = \Carbon\Carbon::parse($this->filters['end_date'], $appTz)->endOfDay();
            
            $startUtc = $startLocal->copy()->timezone('UTC');
            $endExclusiveUtc = $endLocal->copy()->addDay()->startOfDay()->timezone('UTC');

             $query->where(function ($q) use ($startUtc, $endExclusiveUtc, $startLocal, $endLocal, $appTz) {
                // Caso A: BD guarda en UTC (rango exclusivo del final)
                $q->where(function ($qq) use ($startUtc, $endExclusiveUtc) {
                    $qq->where('created_at', '>=', $startUtc)
                       ->where('created_at', '<', $endExclusiveUtc);
                })
                // Caso B: BD guarda en hora local
                ->orWhereRaw('DATE(created_at) BETWEEN ? AND ?', [
                    $startLocal->toDateString(), $endLocal->toDateString()
                ])
                // Caso C: MySQL con tablas TZ
                ->orWhereRaw(
                    "DATE(CONVERT_TZ(created_at, 'UTC', ?)) BETWEEN ? AND ?",
                    [$appTz, $startLocal->toDateString(), $endLocal->toDateString()]
                );
            });
        }
        $orders = $query->orderByDesc('sequence_number')->orderByDesc('id')->get();

        $rows = collect();
        foreach ($orders as $order) {
            $couponCode = $order->coupon ? $order->coupon->code : null;
            // Calcular subtotal sumando total + descuento (si hay)
            $discount = $order->discount_amount ?? 0;
            $subtotal = $order->total_price + $discount;

            foreach ($order->items as $item) {
                $name = $item->product_name ?? optional($item->product)->name;
                $options = $item->variant_options ?? optional($item->variant)->options;
                $optionsText = '';
                if (is_array($options) || $options instanceof \ArrayAccess) {
                    $optionsText = collect($options)->map(fn($val, $key) => "$key: $val")->implode(', ');
                }

                $rows->push([
                    $order->sequence_number ?? $order->id,
                    $order->customer_name,
                    $order->customer_phone,
                    $order->customer_email,
                    $order->customer_address,
                    $order->created_at->timezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i'),
                    $order->status,
                    $subtotal,
                    $discount > 0 ? $discount : 0,
                    $couponCode,
                    $order->total_price,
                    $name,
                    $optionsText,
                    $item->quantity,
                    $item->unit_price,
                ]);
            }
        }
        // Si no hay ítems (p.ej., una sola orden sin ítems o filtro vacío), devolvemos una fila vacía
        return $rows->isEmpty() ? collect([[null,null,null,null,null,null,null,null,null,null,null,null,null,null,null]]) : $rows;
    }

    /**
    * Define los encabezados de las columnas en el Excel
    */
    public function headings(): array
    {
        return [
            'Orden #',
            'Cliente',
            'Teléfono',
            'Email',
            'Dirección',
            'Fecha',
            'Estado',
            'Subtotal',
            'Descuento',
            'Cupón',
            'Total Final',
            'Producto',
            'Variante',
            'Cantidad',
            'Precio Unitario',
        ];
    }

    /**
    * Mapea los datos de cada orden a las columnas del Excel
    */
    public function map($order): array
    {
        // Not used with FromCollection
        return [];
    }
}