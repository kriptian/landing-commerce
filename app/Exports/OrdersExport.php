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
        $query = Auth::user()->store->orders()->with(['items', 'items.product', 'items.variant']);
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $appTz = config('app.timezone', 'UTC');
            $startLocal = \Carbon\Carbon::parse($this->filters['start_date'], $appTz)->startOfDay();
            $endLocal = \Carbon\Carbon::parse($this->filters['end_date'], $appTz)->endOfDay();
            // Aseguramos comparación por fecha local
            $query->whereRaw(
                "DATE(CONVERT_TZ(created_at, 'UTC', ?)) BETWEEN ? AND ?",
                [$appTz, $startLocal->toDateString(), $endLocal->toDateString()]
            );
        }
        $orders = $query->orderByDesc('sequence_number')->orderByDesc('id')->get();

        $rows = collect();
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $name = $item->product_name ?? optional($item->product)->name;
                $options = $item->variant_options ?? optional($item->variant)->options;
                $optionsText = '';
                if (is_array($options) || $options instanceof \ArrayAccess) {
                    $optionsText = collect($options)->map(fn($val, $key) => "$key: $val")->implode(', ');
                }
                $fullName = trim($name . ($optionsText ? " (".$optionsText.")" : ''));

                $rows->push([
                    $order->sequence_number ?? $order->id,
                    $order->customer_name,
                    $order->customer_phone,
                    $order->customer_email,
                    $order->customer_address,
                    $order->created_at->timezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i'),
                    $order->status,
                    $order->total_price,
                    $item->quantity,
                    $fullName,
                    $item->quantity,
                    $item->unit_price,
                ]);
            }
        }
        return $rows;
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
            'Total Orden',
            '# Items',
            'Producto',
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