<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listar clientes de la tienda
     */
    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        // Validar filtros
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = $store->customers()->withCount('orders');

        // Búsqueda
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->with(['defaultAddress'])
            ->latest()
            ->paginate(20);

        // Estadísticas generales
        $stats = [
            'total_customers' => $store->customers()->count(),
            'total_orders' => $store->orders()->whereNotNull('customer_id')->count(),
            'total_revenue' => $store->orders()
                ->whereNotNull('customer_id')
                ->sum('total_price'),
        ];

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'stats' => $stats,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Mostrar detalles del cliente
     */
    public function show(Request $request, Customer $customer): Response
    {
        // Verificar que el cliente pertenece a la tienda del usuario
        if ($customer->store_id !== $request->user()->store_id) {
            abort(403);
        }

        // Cargar datos del cliente
        $customer->load(['addresses', 'orders.items.product', 'orders.items.variant']);

        // Estadísticas del cliente
        $stats = [
            'total_orders' => $customer->orders()->count(),
            'total_spent' => $customer->orders()->sum('total_price'),
            'average_order_value' => $customer->orders()->avg('total_price'),
            'last_order_date' => $customer->orders()->latest()->first()?->created_at,
        ];

        // Órdenes recientes
        $recentOrders = $customer->orders()
            ->with(['items.product', 'items.variant', 'address', 'coupon'])
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
        ]);
    }
}

