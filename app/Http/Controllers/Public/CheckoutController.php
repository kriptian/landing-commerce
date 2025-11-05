<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function index(Request $request, Store $store)
    {
        // Forzar recarga de store desde DB para obtener promociones actualizadas
        $store = $store->fresh();
        
        if ($request->user()) {
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
        } else {
            // Invitado: componer items desde sesión
            $sessionCart = $request->session()->get('guest_cart', []);
            $cartItems = collect();
            foreach ($sessionCart as $row) {
                // Obtener producto directamente desde DB usando select explícito para evitar identity map cache
                $product = Product::query()
                    ->where('id', $row['product_id'] ?? null)
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
                $cartItems->push((object) [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant ? $variant->id : null,
                    'quantity' => (int) ($row['quantity'] ?? 1),
                    'product' => $product,
                    'variant' => $variant,
                ]);
            }
        }

        if ($cartItems->isEmpty()) {
            return redirect()->route('catalogo.index', ['store' => $store->slug]);
        }

        return Inertia::render('Public/CheckoutPage', [
            'cartItems' => $cartItems,
            'store' => $store,
        ]);
    }

    public function store(Request $request, Store $store)
    {
        mb_internal_encoding("UTF-8"); 
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'required|email|max:255',
            'customer_address' => 'required|string|max:1000',
        ]);

        // Forzar recarga de store desde DB para obtener promociones actualizadas
        $store = $store->fresh();
        
        if ($request->user()) {
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
        } else {
            $sessionCart = $request->session()->get('guest_cart', []);
            $cartItems = collect();
            foreach ($sessionCart as $row) {
                // Obtener producto directamente desde DB usando select explícito para evitar identity map cache
                $product = Product::query()
                    ->where('id', $row['product_id'] ?? null)
                    ->select([
                        'id', 'name', 'price', 'retail_price', 'wholesale_price', 'purchase_price',
                        'promo_active', 'promo_discount_percent', 'track_inventory', 'quantity',
                        'alert', 'category_id', 'store_id', 'short_description', 'long_description',
                        'specifications', 'is_featured', 'is_active', 'variant_attributes'
                    ])
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
                $cartItems->push((object) [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant ? $variant->id : null,
                    'quantity' => (int) ($row['quantity'] ?? 1),
                    'product' => $product,
                    'variant' => $variant,
                ]);
            }
        }

        if ($cartItems->isEmpty()) {
            return redirect()->route('catalogo.index', ['store' => $store->slug])->withErrors('Tu carrito está vacío.');
        }

        // Lógica de precio: priorizar precio de variante si existe, sino usar precio principal del producto
        $computeBaseUnit = function ($item) {
            $variant = $item->variant ?? null;
            $product = $item->product ?? null;
            
            // Si hay variante seleccionada, usar precio de variante si existe
            // IMPORTANTE: Ahora usamos el precio de variante incluso si track_inventory está desactivado
            // porque las variant_options pueden tener precios independientes
            if ($variant && $product) {
                // Usar precio de variante si existe y no es null/vacío
                if ($variant->price !== null && $variant->price !== '') {
                    return (float) $variant->price;
                }
            }
            
            // Si no hay variante o la variante no tiene precio: usar precio principal del producto
            if ($product && $product->price !== null) {
                return (float) $product->price;
            }
            
            return 0.0;
        };

        $totalPrice = $cartItems->reduce(function ($total, $item) use ($store, $computeBaseUnit) {
            $base = $computeBaseUnit($item);
            $percent = 0;
            if ($store->promo_active && (int) $store->promo_discount_percent > 0) {
                $percent = (int) $store->promo_discount_percent;
            } elseif ($item->product->promo_active && (int) $item->product->promo_discount_percent > 0) {
                $percent = (int) $item->product->promo_discount_percent;
            }
            $unit = $percent > 0 ? (int) round($base * (100 - $percent) / 100) : (int) round($base);
            return $total + ($unit * $item->quantity);
        }, 0);

        // Store ya se recargó arriba con fresh(), no es necesario volver a recargarlo
        $nextSequence = ((int) ($store->order_sequence ?? 0)) + 1;
        $order = $store->orders()->create([
            'sequence_number' => $nextSequence,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'],
            'customer_address' => $validated['customer_address'],
            'total_price' => $totalPrice,
            'status' => 'recibido',
        ]);
        $store->forceFill(['order_sequence' => $nextSequence])->save();

        foreach ($cartItems as $item) {
            $base = $computeBaseUnit($item);
            $percent = 0;
            if ($store->promo_active && (int) $store->promo_discount_percent > 0) {
                $percent = (int) $store->promo_discount_percent;
            } elseif ($item->product->promo_active && (int) $item->product->promo_discount_percent > 0) {
                $percent = (int) $item->product->promo_discount_percent;
            }
            $price = $percent > 0 ? (int) round($base * (100 - $percent) / 100) : (int) round($base);

            $order->items()->create([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $price,
                'product_name' => $item->product->name,
                'variant_options' => $item->variant->options ?? null,
            ]);
        }

        // Limpiar carrito
        if ($request->user()) {
            $request->user()->cart()->whereIn('id', $cartItems->pluck('id'))->delete();
        } else {
            $request->session()->forget('guest_cart');
        }

        // Preparar WhatsApp
        if (empty($store->phone)) {
            return back()->withErrors(['phone' => 'Lo sentimos, esta tienda no tiene un número de WhatsApp configurado para recibir pedidos.']);
        }
        $storePhoneNumber = preg_replace('/[^0-9]/', '', $store->phone);

        $whatsappMessage = "¡Hola! Quiero realizar el siguiente pedido:\n\n";
        $whatsappMessage .= "*Orden #{$order->sequence_number}*\n\n";
        foreach ($order->items as $item) {
            $whatsappMessage .= "• *{$item->product_name}*\n";
            if ($item->variant_options) {
                $optionsText = collect($item->variant_options)->map(fn($val, $key) => "{$key}: {$val}")->implode(', ');
                $whatsappMessage .= "   - {$optionsText}\n";
            }
            $whatsappMessage .= "   - Cantidad: {$item->quantity}\n";
            $whatsappMessage .= "   - Precio unitario: $" . number_format($item->unit_price, 0, ',', '.') . "\n\n";
        }
        $whatsappMessage .= "*Total del Pedido:* $" . number_format($order->total_price, 0, ',', '.') . "\n\n";
        $whatsappMessage .= "*Datos de Envío:*\n";
        $whatsappMessage .= "• *Nombre:* {$validated['customer_name']}\n";
        $whatsappMessage .= "• *Teléfono:* {$validated['customer_phone']}\n";
        $whatsappMessage .= "• *Dirección:* {$validated['customer_address']}\n\n";
        $whatsappMessage .= "¡Gracias!";

        if (class_exists(\Normalizer::class)) {
            $whatsappMessage = \Normalizer::normalize($whatsappMessage, \Normalizer::FORM_C);
        }
        $whatsappMessage = mb_convert_encoding($whatsappMessage, 'UTF-8', 'UTF-8');
        $query = http_build_query(['text' => $whatsappMessage], '', '&', PHP_QUERY_RFC3986);
        $whatsappUrl = 'https://wa.me/' . $storePhoneNumber . '?' . $query;

        return Inertia::location($whatsappUrl);
    }
}