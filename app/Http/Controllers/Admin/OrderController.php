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
        // (Opcional pero recomendado) Validamos que si nos mandan un estado, sea uno válido.
        $request->validate([
            'status' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['recibido', 'en_preparacion', 'despachado', 'entregado', 'cancelado'])]
        ]);

        // Empezamos la consulta, pero todavía no la ejecutamos
        $query = $request->user()->store->orders()->withCount('items');

        // Si en la URL viene un parámetro 'status' (ej: /admin/orders?status=recibido)...
        if ($request->filled('status')) {
            // ...lo agregamos a la consulta.
            $query->where('status', $request->status);
        }

        // Ahora sí ejecutamos la consulta y paginamos.
        // withQueryString() hace que los links de la paginación mantengan el filtro.
        $orders = $query->orderByDesc('sequence_number')->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            // Mandamos los filtros actuales a la vista para que sepa qué botón resaltar
            'filters' => $request->only(['status']),
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
            'status' => ['required', 'string', Rule::in(['recibido', 'en_preparacion', 'despachado', 'entregado', 'cancelado'])],
        ]);

        // Actualizamos la orden con el nuevo estado
        $order->update([
            'status' => $request->status,
        ]);

        // Regresamos a la página anterior
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

        try {
            // Usamos una transacción para que, si algo falla, no se descuente nada.
            DB::transaction(function () use ($order) {
                
                $order->load('items.variant', 'items.product');

                foreach ($order->items as $item) {
                    if ($item->variant) {
                        // Si el item es una variante, descontamos el stock de la variante
                        $variant = $item->variant;
                        if ($variant->stock < $item->quantity) {
                            // ===== ESTE ES EL CAMBIO =====
                            // En vez de explotar, lanzamos un error que podemos atrapar
                            throw new \Exception("No hay suficiente stock para la variante: {$item->variant->options['Opción']}.");
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