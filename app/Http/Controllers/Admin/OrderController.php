<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct()
    {
        // Permisos estrictos por acción
        // Solo quienes tengan "ver ordenes" pueden listar y ver detalles
        $this->middleware('can:ver ordenes')->only(['index', 'show']);
        // Solo quienes tengan "gestionar ordenes" pueden cambiar estado/confirmar
        $this->middleware('can:gestionar ordenes')->only('update');
    }

    /**
     * Muestra una lista de todas las órdenes de la tienda.
     */
    public function index(Request $request)
    {
        // Validación básica de filtros
        $request->validate([
            'status' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['recibido', 'en_preparacion', 'despachado', 'entregado', 'cancelado'])],
            'q' => ['nullable', 'string'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        // Empezamos la consulta, pero todavía no la ejecutamos
        $query = $request->user()->store->orders()->withCount('items');

        // Si en la URL viene un parámetro 'status' (ej: /admin/orders?status=recibido)...
        if ($request->filled('status')) {
            // ...lo agregamos a la consulta.
            $query->where('status', $request->status);
        }

        // Filtro de búsqueda (número, nombre, teléfono)
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($qq) use ($q) {
                $qq->where('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%");
            });
        }

        // Rango de fechas (creación)
        if ($request->filled('start') || $request->filled('end')) {
            $start = $request->filled('start') ? \Carbon\Carbon::parse($request->start)->startOfDay() : null;
            $end = $request->filled('end') ? \Carbon\Carbon::parse($request->end)->endOfDay() : null;
            $query->when($start, fn ($qq) => $qq->where('created_at', '>=', $start))
                ->when($end, fn ($qq) => $qq->where('created_at', '<=', $end));
        }

        // Ahora sí ejecutamos la consulta y paginamos.
        // withQueryString() hace que los links de la paginación mantengan el filtro.
        $orders = $query->orderByDesc('sequence_number')->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            // Mandamos los filtros actuales a la vista para que sepa qué botón resaltar
            'filters' => $request->only(['status', 'q', 'start', 'end']),
        ]);
    }

    /**
     * Muestra el detalle de una orden específica.
     */
    public function show(\App\Models\Order $order)
    {
        // Medida de seguridad: nos aseguramos que la orden sí pertenezca a la tienda del usuario
        if ($order->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        // Cargamos los items de la orden y, de cada item, cargamos el producto, la variante y el cupón
        $order->load('items.product', 'items.variant', 'coupon');

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'store' => auth()->user()->store,
        ]);
    }

    /**
     * Actualiza el estado de una orden.
     */
    public function update(Request $request, Order $order)
    {
        $storeId = $request->user()->store_id;
        $newStatus = $request->validate([
            'status' => ['required', 'string', Rule::in(['recibido', 'en_preparacion', 'en_proceso', 'despachado', 'en_camino', 'entregado', 'cancelado'])],
        ])['status'];

        try {
            DB::transaction(function () use ($order, $storeId, $newStatus) {
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $previousStatus = $lockedOrder->status;
                $inventoryStatuses = ['despachado', 'entregado'];
                $shouldSubtract = ! in_array($previousStatus, $inventoryStatuses, true)
                    && in_array($newStatus, $inventoryStatuses, true);
                $shouldReturn = in_array($previousStatus, $inventoryStatuses, true)
                    && ! in_array($newStatus, $inventoryStatuses, true);

                if ($shouldSubtract || $shouldReturn) {
                    $lockedOrder->load('items');

                    foreach ($lockedOrder->items as $item) {
                        $product = Product::query()
                            ->whereKey($item->product_id)
                            ->where('store_id', $storeId)
                            ->lockForUpdate()
                            ->first();

                        if (! $product || ! $product->track_inventory) {
                            continue;
                        }

                        $stockModel = $product;
                        $stockColumn = 'quantity';
                        if ($item->product_variant_id) {
                            $stockModel = ProductVariant::query()
                                ->whereKey($item->product_variant_id)
                                ->where('product_id', $product->id)
                                ->lockForUpdate()
                                ->first();
                            $stockColumn = 'stock';

                            if (! $stockModel) {
                                throw new \RuntimeException('La variante del pedido ya no está disponible.');
                            }
                        }

                        if ($shouldSubtract) {
                            if ((int) $stockModel->{$stockColumn} < (int) $item->quantity) {
                                throw new \RuntimeException("No hay suficiente stock para {$item->product_name}.");
                            }
                            $stockModel->decrement($stockColumn, $item->quantity);
                        } else {
                            $stockModel->increment($stockColumn, $item->quantity);
                        }
                    }
                }

                $lockedOrder->update(['status' => $newStatus]);

                if ($lockedOrder->customer_id && $previousStatus !== $newStatus) {
                    $statusMessages = [
                        'recibido' => 'Tu pedido ha sido recibido por la tienda',
                        'en_preparacion' => 'Tu pedido está en preparación',
                        'en_proceso' => 'Tu pedido está en preparación',
                        'despachado' => 'Tu pedido ha sido despachado',
                        'en_camino' => 'Tu pedido está en camino a tu dirección',
                        'entregado' => 'Tu pedido ha sido entregado',
                        'cancelado' => 'Tu pedido ha sido cancelado',
                    ];

                    $title = 'Actualización de pedido #'.$lockedOrder->sequence_number;
                    $message = $statusMessages[$newStatus] ?? 'El estado de tu pedido ha cambiado';

                    CustomerNotification::create([
                        'customer_id' => $lockedOrder->customer_id,
                        'order_id' => $lockedOrder->id,
                        'type' => 'order_status',
                        'title' => $title,
                        'message' => $message,
                    ]);
                }
            }, 3);
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back();
    }
}
