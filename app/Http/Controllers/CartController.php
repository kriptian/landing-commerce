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
        // Forzar recarga de store desde DB para obtener promociones actualizadas
        $store = $store->fresh();
        
        if (Auth::check()) {
            $cartItems = $request->user()->cart()
                            ->whereRelation('product', 'store_id', $store->id)
                            ->get();
            
            // Recargar productos y variantes directamente desde DB para obtener precios actualizados
            // IMPORTANTE: Forzamos que se seleccionen TODOS los campos de precio explícitamente
            foreach ($cartItems as $item) {
                if ($item->product_id) {
                    // Forzar consulta directa desde DB sin usar identity map
                    // Seleccionamos explícitamente todos los campos de precio para asegurar datos frescos
                    $freshProduct = Product::query()
                        ->where('id', $item->product_id)
                        ->select([
                            'id', 'name', 'price', 'retail_price', 'wholesale_price', 'purchase_price',
                            'promo_active', 'promo_discount_percent', 'track_inventory', 'quantity',
                            'alert', 'category_id', 'store_id', 'short_description', 'long_description',
                            'specifications', 'is_featured', 'is_active', 'variant_attributes'
                        ])
                        ->with('images')
                        ->first();
                    // Forzar recarga de atributos desde DB
                    if ($freshProduct) {
                        $freshProduct->refresh();
                    }
                    $item->setRelation('product', $freshProduct);
                } else {
                    $item->setRelation('product', null);
                }
                if ($item->product_variant_id) {
                    // Forzar consulta directa desde DB sin usar identity map
                    $freshVariant = ProductVariant::query()
                        ->where('id', $item->product_variant_id)
                        ->select([
                            'id', 'product_id', 'options', 'price', 'retail_price', 
                            'wholesale_price', 'purchase_price', 'stock', 'alert', 'sku'
                        ])
                        ->first();
                    // Forzar recarga de atributos desde DB
                    if ($freshVariant) {
                        $freshVariant->refresh();
                    }
                    $item->setRelation('variant', $freshVariant);
                } else {
                    $item->setRelation('variant', null);
                }
            }

            return Inertia::render('Public/CartPage', [
                'cartItems' => $cartItems,
                'store' => $store,
            ]);
        }

        // Invitado: leer carrito desde sesión
        $sessionCart = $request->session()->get('guest_cart', []); // [key => [product_id, product_variant_id, quantity, store_id?]]
        $items = [];
        foreach ($sessionCart as $key => $row) {
            // Si el item tiene store_id y no corresponde, lo omitimos sin tocar la sesión
            if (isset($row['store_id']) && (int) $row['store_id'] !== (int) $store->id) {
                continue;
            }
            // Obtener producto directamente desde DB usando select explícito para evitar identity map cache
            $product = Product::query()
                ->where('id', $row['product_id'])
                ->select([
                    'id', 'name', 'price', 'retail_price', 'wholesale_price', 'purchase_price',
                    'promo_active', 'promo_discount_percent', 'track_inventory', 'quantity',
                    'alert', 'category_id', 'store_id', 'short_description', 'long_description',
                    'specifications', 'is_featured', 'is_active', 'variant_attributes'
                ])
                ->with('images')
                ->first();
            if (! $product || (int) $product->store_id !== (int) $store->id) {
                continue;
            }
            // Forzar recarga desde DB
            $product->refresh();
            $variant = null;
            if (!empty($row['product_variant_id'])) {
                // Forzar consulta directa desde DB sin usar identity map
                $variant = ProductVariant::query()
                    ->where('id', $row['product_variant_id'])
                    ->select([
                        'id', 'product_id', 'options', 'price', 'retail_price', 
                        'wholesale_price', 'purchase_price', 'stock', 'alert', 'sku'
                    ])
                    ->first();
                // Forzar recarga desde DB
                if ($variant) {
                    $variant->refresh();
                }
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
            'store' => $store->fresh(),
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

        // 2. Revisar el stock disponible. Si el producto controla inventario por total (sin stock por variante),
        //    permitimos caer al inventario total cuando la variante no tiene stock definido (>0).
        $stockDisponible = 0;
        if ($product->track_inventory === false) {
            // Inventario desactivado: permitir libremente (con o sin variante)
            $stockDisponible = PHP_INT_MAX;
        } else if ($variant) {
            $stockVariante = (int) ($variant->stock ?? 0);
            $stockDisponible = $stockVariante > 0 ? $stockVariante : (int) ($product->quantity ?? 0);
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
                // Guardamos explícitamente la tienda para filtrar sin consultas adicionales
                'store_id' => (int) ($product->store_id ?? 0),
            ];
            $session->put('guest_cart', $cart);
        }

        // Redirigir a la página del carrito para rehidratar cantidades actualizadas sin conservar estado previo
        $storeSlug = $product->store->slug ?? null;
        if ($storeSlug) {
            return redirect()->route('cart.index', ['store' => $storeSlug]);
        }
        return redirect()->back();
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