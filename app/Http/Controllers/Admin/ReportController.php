<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\OrdersExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PhysicalSale;

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
            'type' => 'nullable|string|in:digital,physical',
            'search' => 'nullable|string',
        ]);

        // Definimos el rango de fechas en la ZONA HORARIA DE LA APP (ej: America/Bogota)
        // y luego convertimos a UTC para consultar la BD (created_at se guarda en UTC)
        $appTz = config('app.timezone', 'America/Bogota');
        $nowLocal = now($appTz);
        $startLocal = !empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'], $appTz)->startOfDay()
            : $nowLocal->copy()->subDays(29)->startOfDay();
        $endLocal = !empty($validated['end_date'])
            ? Carbon::parse($validated['end_date'], $appTz)->endOfDay()
            : $nowLocal->copy()->endOfDay();

        // Empezamos la consulta base de órdenes de la tienda
        // Usamos el rango local convertido a UTC, con fin exclusivo, para evitar incluir el día siguiente
        $startUtc = $startLocal->copy()->timezone('UTC');
        $endExclusiveUtc = $endLocal->copy()->addDay()->startOfDay()->timezone('UTC');
        $ordersQuery = $request->user()->store->orders()
            ->where(function ($q) use ($startUtc, $endExclusiveUtc, $startLocal, $endLocal, $appTz) {
                // Caso A: BD guarda en UTC (rango exclusivo del final)
                $q->where(function ($qq) use ($startUtc, $endExclusiveUtc) {
                    $qq->where('created_at', '>=', $startUtc)
                       ->where('created_at', '<', $endExclusiveUtc);
                })
                // Caso B: BD guarda en hora local (comparación por DATE local)
                ->orWhereRaw('DATE(created_at) BETWEEN ? AND ?', [
                    $startLocal->toDateString(), $endLocal->toDateString()
                ])
                // Caso C: MySQL con tablas TZ (UTC -> TZ app)
                ->orWhereRaw(
                    "DATE(CONVERT_TZ(created_at, 'UTC', ?)) BETWEEN ? AND ?",
                    [$appTz, $startLocal->toDateString(), $endLocal->toDateString()]
                );
            });

        // Calculamos las estadísticas SOBRE la consulta ya filtrada
        $totalSales = (clone $ordersQuery)->sum('total_price');
        $totalOrders = (clone $ordersQuery)->count();
        
        // Traemos la lista de órdenes para la tabla
        $orders = (clone $ordersQuery)->with(['items','items.product','items.variant'])->withCount('items')->orderByDesc('sequence_number')->orderByDesc('id')->paginate(15)->withQueryString();

        // --- Datos del gráfico: agrupamos por día en zona horaria local ---
        $ordersForChart = (clone $ordersQuery)
            ->whereIn('status', ['entregado', 'cancelado'])
            ->get(['id','status','created_at']);

        $groupedByLocalDate = $ordersForChart->groupBy(function ($order) use ($appTz) {
            return Carbon::parse($order->created_at)->timezone($appTz)->toDateString();
        });

        $labels = $groupedByLocalDate->keys()->sort()->values()->all();
        $delivered = [];
        $cancelled = [];
        foreach ($labels as $date) {
            $dayGroup = $groupedByLocalDate->get($date, collect());
            $delivered[] = (int) $dayGroup->where('status', 'entregado')->count();
            $cancelled[] = (int) $dayGroup->where('status', 'cancelado')->count();
        }

        // Obtener datos de ventas físicas
        $store = $request->user()->store;
        $physicalSalesQuery = $store->physicalSales()->with(['user', 'items.product', 'items.variant']);
        
        // Búsqueda por número de venta
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $physicalSalesQuery->where('sale_number', 'like', "%{$search}%");
        }
        
        // Aplicar filtros de fecha si existen
        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $appTz = config('app.timezone', 'America/Bogota');
            $startLocal = Carbon::parse($validated['start_date'], $appTz)->startOfDay();
            $endLocal = Carbon::parse($validated['end_date'], $appTz)->endOfDay();
            $startUtc = $startLocal->copy()->timezone('UTC');
            $endExclusiveUtc = $endLocal->copy()->addDay()->startOfDay()->timezone('UTC');
            
            $physicalSalesQuery->whereBetween('created_at', [$startUtc, $endExclusiveUtc]);
        }
        
        $physicalSales = $physicalSalesQuery->latest()->paginate(20)->withQueryString();
        
        // Calcular estadísticas de ventas físicas
        $physicalSalesStatsQuery = clone $physicalSalesQuery;
        $physicalTotalSales = $physicalSalesStatsQuery->sum('total');
        $physicalTotalCount = $physicalSalesStatsQuery->count();

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
            'physicalSales' => $physicalSales,
            'physicalSalesStats' => [
                'totalSales' => $physicalTotalSales,
                'totalCount' => $physicalTotalCount,
            ],
            'physicalSalesFilters' => $request->only(['start_date', 'end_date', 'search']),
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