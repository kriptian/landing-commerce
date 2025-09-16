<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller; // <-- ¡ESTA ES LA LÍNEA QUE FALTABA!
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CartController extends Controller
{
    /**
     * Muestra la página del carrito de compras.
     * ESTA ES LA FUNCIÓN INDEX (v2.0)
     */
    public function index(Request $request, \App\Models\Store $store)
    {
        $cartItems = $request->user()->cart()
                        ->whereRelation('product', 'store_id', $store->id)
                        // ===== ESTE ES EL CAMBIO =====
                        // Ahora cargamos el producto Y la variante asociada
                        ->with('product', 'variant') 
                        ->get();

        return Inertia::render('Public/CartPage', [
            'cartItems' => $cartItems,
            'storeSlug' => $store->slug
        ]);
    }

    /**
     * Guarda un item en el carrito.
     * ESTA ES LA FUNCIÓN STORE (v2.0 - LA BUENA)
     */
    public function store(Request $request)
    {
        // 1. Nueva Validación (ahora acepta el ID de la variante)
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id', // <-- Nuevo campo
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);
        $variant = $request->product_variant_id ? ProductVariant::find($request->product_variant_id) : null;

        // 2. Revisar el stock (ahora revisa la variante si existe)
        $stockDisponible = 0;
        if ($variant) {
            // Si es una variante, el stock es el de la variante
            $stockDisponible = $variant->stock;
        } else {
            // Si es un producto simple (sin variantes), el stock es el general
            $stockDisponible = $product->quantity;
        }

        if ($request->quantity > $stockDisponible) {
            return back()->withErrors(['quantity' => 'No hay suficiente stock para esa variante.']);
        }

        // 3. Lógica de guardado (LA PARTE CLAVE)
        
        // Construimos la consulta base para buscar el item
        $cartQuery = Auth::user()->cart()->where('product_id', $request->product_id);

        if ($variant) {
            // Si hay variante, buscamos el item con ESA VARIANTE específica
            $cartQuery->where('product_variant_id', $variant->id);
        } else {
            // Si es producto simple, buscamos el que NO tenga variante
            $cartQuery->whereNull('product_variant_id');
        }

        $cartItem = $cartQuery->first();

        if ($cartItem) {
            // Si ya lo tiene, solo actualizamos la cantidad
            $cartItem->quantity = $request->quantity; 
            $cartItem->save();
        } else {
            // Si es nuevo (ya sea producto simple o una variante nueva), lo creamos
            Auth::user()->cart()->create([
                'product_id' => $request->product_id,
                'product_variant_id' => $variant ? $variant->id : null, // <-- Guardamos el ID de la variante
                'quantity' => $request->quantity,
            ]);
        }

        return back();
    }


    /**
     * Elimina un item del carrito.
     * (Esta función está perfecta como la tenías)
     */
    public function destroy(Cart $cart)
    {
        if ($cart->user_id !== auth()->id()) {
            abort(403); 
        }
        $cart->delete();
        return back();
    }
}