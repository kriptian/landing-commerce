<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct()
    {
        // Permisos estrictos por acción
        // Solo quienes tengan "ver ordenes" pueden listar y ver detalles
        $this->middleware('can:ver ordenes')->only(['index', 'show']);
        // Solo quienes tengan "gestionar ordenes" pueden cambiar estado/confirmar
        $this->middleware('can:gestionar ordenes')->only(['update', 'confirm']);
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
            $query->when($start, fn($qq) => $qq->where('created_at', '>=', $start))
                  ->when($end, fn($qq) => $qq->where('created_at', '<=', $end));
        }

        // Ahora sí ejecutamos la consulta y paginamos.
        // withQueryString() hace que los links de la paginación mantengan el filtro.
        $orders = $query->orderByDesc('sequence_number')->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            // Mandamos los filtros actuales a la vista para que sepa qué botón resaltar
            'filters' => $request->only(['status','q','start','end']),
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

        // Cargamos los items de la orden y, de cada item, cargamos el producto y la variante
        $order->load('items.product', 'items.variant');

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
        ]);
    }
    /**
     * Actualiza el estado de una orden.
     */
    public function update(Request $request, \App\Models\Order $order)
    {
        // Seguridad: otra vez, que la orden sea de la tienda del usuario
        if ($order->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        // Validamos que el estado que nos mandan sea uno de los permitidos
        $request->validate([
            'status' => ['required', 'string', Rule::in(['recibido', 'en_preparacion', 'en_proceso', 'despachado', 'en_camino', 'entregado', 'cancelado'])],
        ]);

        // Estados
        $previousStatus = $order->status;
        $newStatus = $request->status;

        // Transiciones que DESCUENTAN inventario: pasar a 'despachado' o 'entregado'
        $shouldSubtract = !in_array($previousStatus, ['despachado', 'entregado']) && in_array($newStatus, ['despachado', 'entregado']);
        // Transiciones que DEVUELVEN inventario: salir de 'despachado' o 'entregado'
        $shouldReturn = in_array($previousStatus, ['despachado', 'entregado']) && !in_array($newStatus, ['despachado', 'entregado']);

        try {
            DB::transaction(function () use ($order, $previousStatus, $newStatus, $shouldSubtract, $shouldReturn) {
                $order->load('items.variant', 'items.product');

                if ($shouldSubtract) {
                    // Validamos y descontamos
                    foreach ($order->items as $item) {
                        $product = $item->product; // puede ser null si borrado
                        // Si la tienda NO controla inventario en este producto, no hacemos nada
                        if ($product && $product->track_inventory === false) {
                            continue;
                        }

                        if ($item->variant) {
                            $variant = $item->variant;
                            if ($variant->stock > 0) {
                                if ($variant->stock < $item->quantity) {
                                    throw new \Exception("No hay suficiente stock para la variante seleccionada.");
                                }
                                $variant->decrement('stock', $item->quantity);
                            } else {
                                // Variante sin stock propio: descontamos del inventario total del producto
                                if ($product) {
                                    if ($product->quantity < $item->quantity) {
                                        throw new \Exception("No hay suficiente stock para el producto {$item->product_name}.");
                                    }
                                    $product->decrement('quantity', $item->quantity);
                                }
                            }
                        } elseif ($product) {
                            if ($product->quantity < $item->quantity) {
                                throw new \Exception("No hay suficiente stock para el producto {$item->product_name}.");
                            }
                            $product->decrement('quantity', $item->quantity);
                        }
                    }
                } elseif ($shouldReturn) {
                    // Devolvemos inventario
                    foreach ($order->items as $item) {
                        $product = $item->product;
                        if ($product && $product->track_inventory === false) {
                            continue;
                        }
                        if ($item->variant) {
                            // Si la variante maneja stock (>0), incrementamos ahí; si no, devolvemos al total del producto
                            if ($item->variant->stock > 0) {
                                $item->variant->increment('stock', $item->quantity);
                            } elseif ($product) {
                                $product->increment('quantity', $item->quantity);
                            }
                        } elseif ($product) {
                            $product->increment('quantity', $item->quantity);
                        }
                    }
                }

                // Finalmente actualizamos el estado
                $order->update(['status' => $newStatus]);

                // Crear notificación para el cliente si existe
                if ($order->customer_id && $previousStatus !== $newStatus) {
                    $statusMessages = [
                        'recibido' => 'Tu pedido ha sido recibido por la tienda',
                        'en_preparacion' => 'Tu pedido está en preparación',
                        'en_proceso' => 'Tu pedido está en preparación',
                        'despachado' => 'Tu pedido ha sido despachado',
                        'en_camino' => 'Tu pedido está en camino a tu dirección',
                        'entregado' => 'Tu pedido ha sido entregado',
                        'cancelado' => 'Tu pedido ha sido cancelado',
                    ];

                    $title = 'Actualización de pedido #' . $order->sequence_number;
                    $message = $statusMessages[$newStatus] ?? 'El estado de tu pedido ha cambiado';

                    \App\Models\CustomerNotification::create([
                        'customer_id' => $order->customer_id,
                        'order_id' => $order->id,
                        'type' => 'order_status',
                        'title' => $title,
                        'message' => $message,
                    ]);
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back();
    }
    /**
     * Confirma una orden y descuenta el inventario.
     */
    /**
     * Confirma una orden y descuenta el inventario.
     */
    public function confirm(\App\Models\Order $order)
    {
        // Seguridad (sigue igual)
        if ($order->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        // Regla de negocio: permitir confirmar y descontar inventario
        // cuando la orden está en estado 'despachado' o 'entregado'.
        if (!in_array($order->status, ['despachado', 'entregado'])) {
            return back()->withErrors(['confirmation' => 'Solo podés confirmar y descontar inventario cuando la orden está DESPACHADA o ENTREGADA.']);
        }

        try {
            // Usamos una transacción para que, si algo falla, no se descuente nada.
            DB::transaction(function () use ($order) {
                
                $order->load('items.variant', 'items.product');

                foreach ($order->items as $item) {
                    if ($item->variant) {
                        // Si el item es una variante, descontamos el stock de la variante
                        $variant = $item->variant;
                        if ($variant->stock < $item->quantity) {
                            // Mensaje genérico (las claves de opciones pueden variar: color, talla, etc.)
                            throw new \Exception("No hay suficiente stock para la variante seleccionada.");
                        }
                        $variant->decrement('stock', $item->quantity);

                    } else if ($item->product) { // Chequeamos que el producto exista
                        // Si es un producto simple, descontamos el stock del producto principal
                        $product = $item->product;
                        if ($product->quantity < $item->quantity) {
                            throw new \Exception("No hay suficiente stock para el producto {$item->product_name}.");
                        }
                        $product->decrement('quantity', $item->quantity);
                    }
                }

                // (Opcional) Podemos cambiar el estado a 'confirmado' o 'entregado'
                // $order->status = 'entregado';
                // $order->save();
            });

        } catch (\Exception $e) {
            // ===== ESTE ES EL CAMBIO =====
            // Si la transacción falla por CUALQUIER razón (como la falta de stock),
            // atrapamos el error y volvemos con un mensaje claro.
            return back()->withErrors(['confirmation' => $e->getMessage()]);
        }


        // Si todo sale bien, regresamos a la página anterior con un mensaje de éxito.
        return back(); // La notificación de éxito ya la maneja el frontend en el 'onSuccess'
    }
}