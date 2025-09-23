<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Muestra una lista de todas las órdenes de la tienda.
     */
    public function index(Request $request)
    {
        // 1. Buscamos las órdenes que pertenecen ÚNICAMENTE a la tienda del usuario logueado.
        $orders = $request->user()->store->orders()
                        ->withCount('items') // Contamos cuántos items tiene cada orden
                        ->latest() // Las más nuevas primero
                        ->paginate(15); // Para no cargar todo de una

        // 2. Mandamos las órdenes a una nueva vista de Vue que crearemos
        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
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
    public function confirm(\App\Models\Order $order)
    {
        // Seguridad: que la orden sea de la tienda del usuario
        if ($order->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        // Usamos una transacción para que, si algo falla, no se descuente nada.
        DB::transaction(function () use ($order) {

            // Recargamos la orden con sus items y variantes para estar seguros
            $order->load('items.variant', 'items.product');

            foreach ($order->items as $item) {
                if ($item->variant) {
                    // Si el item es una variante, descontamos el stock de la variante
                    $variant = $item->variant;
                    if ($variant->stock < $item->quantity) {
                        // Si no hay stock suficiente, cancelamos toda la operación
                        throw new \Exception("No hay suficiente stock para el producto {$item->product_name} ({$item->variant->options['Opción']}).");
                    }
                    $variant->decrement('stock', $item->quantity);
                } else {
                    // Si es un producto simple, descontamos el stock del producto principal
                    $product = $item->product;
                    if ($product->quantity < $item->quantity) {
                        throw new \Exception("No hay suficiente stock para el producto {$item->product_name}.");
                    }
                    $product->decrement('quantity', $item->quantity);
                }
            }

            // (Opcional) Podemos cambiar el estado a 'completado' o 'entregado' si queremos
            // $order->status = 'entregado';
            // $order->save();
        });

        // Si todo sale bien, regresamos a la página anterior con un mensaje de éxito.
        return back()->with('success', '¡Venta confirmada e inventario actualizado!');
    }
}