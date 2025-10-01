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
        if (Auth::check()) {
            $cartItems = $request->user()->cart()
                            ->whereRelation('product', 'store_id', $store->id)
                            ->with('product', 'variant') 
                            ->get();

            return Inertia::render('Public/CartPage', [
                'cartItems' => $cartItems,
                'store' => $store,
            ]);
        }

        // Invitado: leer carrito desde sesión
        $sessionCart = $request->session()->get('guest_cart', []); // [key => [product_id, product_variant_id, quantity]]
        $items = [];
        foreach ($sessionCart as $key => $row) {
            $product = Product::with('images')->find($row['product_id']);
            if (! $product || (int) $product->store_id !== (int) $store->id) {
                continue;
            }
            $variant = null;
            if (!empty($row['product_variant_id'])) {
                $variant = ProductVariant::find($row['product_variant_id']);
            }
            $items[] = [
                'id' => null,
                'session_key' => $key,
                'product' => $product,
                'variant' => $variant,
                'quantity' => (int) ($row['quantity'] ?? 1),
            ];
        }

        return Inertia::render('Public/CartPage', [
            'cartItems' => $items,
            'store' => $store,
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
            $stockDisponible = $variant->stock;
        } else {
            $stockDisponible = $product->quantity;
        }

        if ($request->quantity > $stockDisponible) {
            return back()->withErrors(['quantity' => 'No hay suficiente stock para esa variante.']);
        }

        // 3. Lógica de guardado (usuarios autenticados vs invitados)
        if (Auth::check()) {
            // Usuario autenticado: persistimos en DB
            $cartQuery = Auth::user()->cart()->where('product_id', $request->product_id);
            if ($variant) {
                $cartQuery->where('product_variant_id', $variant->id);
            } else {
                $cartQuery->whereNull('product_variant_id');
            }
            $cartItem = $cartQuery->first();
            if ($cartItem) {
                $cartItem->quantity = $request->quantity; 
                $cartItem->save();
            } else {
                Auth::user()->cart()->create([
                    'product_id' => $request->product_id,
                    'product_variant_id' => $variant ? $variant->id : null,
                    'quantity' => $request->quantity,
                ]);
            }
        } else {
            // Invitado: guardar en sesión
            $session = $request->session();
            $cart = $session->get('guest_cart', []);
            $key = 'p'.$request->product_id.'-v'.($variant ? $variant->id : '0');
            $cart[$key] = [
                'product_id' => (int) $request->product_id,
                'product_variant_id' => $variant ? (int) $variant->id : null,
                'quantity' => (int) $request->quantity,
            ];
            $session->put('guest_cart', $cart);
        }

        return back();
    }

    /**
     * Elimina un item del carrito.
     */
    public function destroy(Cart $cart)
    {
        if ($cart->user_id !== auth()->id()) {
            abort(403); 
        }
        $cart->delete();
        return back();
    }

    public function destroyGuest(Request $request, string $key)
    {
        $cart = $request->session()->get('guest_cart', []);
        if (array_key_exists($key, $cart)) {
            unset($cart[$key]);
            $request->session()->put('guest_cart', $cart);
        }
        return back();
    }
}