<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Auth;

class OrdersExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    // El constructor recibe los filtros que le mandamos desde el controlador
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    /**
    * Prepara la consulta a la base de datos
    */
    public function query()
    {
        $query = Auth::user()->store->orders()->with('items');

        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $startDate = \Carbon\Carbon::parse($this->filters['start_date'])->startOfDay();
            $endDate = \Carbon\Carbon::parse($this->filters['end_date'])->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->latest();
    }

    /**
    * Define los encabezados de las columnas en el Excel
    */
    public function headings(): array
    {
        return [
            'ID Orden',
            'Cliente',
            'Teléfono',
            'Email',
            'Dirección',
            'Fecha',
            'Estado',
            'Total',
            '# Items',
        ];
    }

    /**
    * Mapea los datos de cada orden a las columnas del Excel
    */
    public function map($order): array
    {
        return [
            $order->id,
            $order->customer_name,
            $order->customer_phone,
            $order->customer_email,
            $order->customer_address,
            $order->created_at->format('Y-m-d H:i'),
            $order->status,
            $order->total_price,
            $order->items->sum('quantity'),
        ];
    }
}