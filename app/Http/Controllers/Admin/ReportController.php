<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\OrdersExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ver reportes')->only(['index','export']);
    }
    public function index(Request $request)
    {
        // Validamos que las fechas que nos lleguen sean correctas
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Definimos el rango de fechas. Por defecto, los últimos 30 días.
        $startDate = !empty($validated['start_date']) ? \Carbon\Carbon::parse($validated['start_date'])->startOfDay() : now()->subDays(29)->startOfDay();
        $endDate = !empty($validated['end_date']) ? \Carbon\Carbon::parse($validated['end_date'])->endOfDay() : now()->endOfDay();

        // Empezamos la consulta base de órdenes de la tienda
        $ordersQuery = $request->user()->store->orders()
                            ->whereBetween('created_at', [$startDate, $endDate]);

        // Calculamos las estadísticas SOBRE la consulta ya filtrada
        $totalSales = (clone $ordersQuery)->sum('total_price');
        $totalOrders = (clone $ordersQuery)->count();
        
        // Traemos la lista de órdenes para la tabla
        $orders = (clone $ordersQuery)->with(['items','items.product','items.variant'])->withCount('items')->orderByDesc('sequence_number')->orderByDesc('id')->paginate(15)->withQueryString();

        // --- Datos del gráfico: conteo por estado (entregadas / canceladas) por día ---
        $countsByDayStatus = (clone $ordersQuery)
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as total')
            ->whereIn('status', ['entregado', 'cancelado'])
            ->groupBy('date', 'status')
            ->orderBy('date', 'asc')
            ->get();

        // Recolectamos solo días con datos (sin huecos)
        $grouped = $countsByDayStatus->groupBy('date');
        $labels = $grouped->keys()->sort()->values()->all();
        $delivered = [];
        $cancelled = [];
        foreach ($labels as $key) {
            $dayGroup = $grouped->get($key, collect());
            $delivered[] = (int) ($dayGroup->firstWhere('status', 'entregado')->total ?? 0);
            $cancelled[] = (int) ($dayGroup->firstWhere('status', 'cancelado')->total ?? 0);
        }

        return Inertia::render('Admin/Reports/Index', [
            'orders' => $orders,
            'stats' => [
                'totalSales' => $totalSales,
                'totalOrders' => $totalOrders,
            ],
            // Mandamos los datos del gráfico a la vista (conteo por estado)
            'chartData' => [
                'labels' => $labels,
                'delivered' => $delivered,
                'cancelled' => $cancelled,
            ],

            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    /**
     * Exporta las órdenes a un archivo de Excel.
     */
    public function export(Request $request)
    {
        // Agarramos los mismos filtros de fecha que usamos en la página
        $filters = $request->only(['start_date', 'end_date']);

        // Le ponemos un nombre dinámico al archivo
        $fileName = 'reporte-de-ordenes-' . now()->format('Y-m-d') . '.xlsx';

        // Le pasamos los filtros a nuestra clase OrdersExport y le decimos que descargue el archivo
        return Excel::download(new OrdersExport($filters), $fileName);
    }
}