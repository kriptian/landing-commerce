<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function index(Request $request, Store $store)
    {
        // Buscamos los items del carrito para mostrarlos en el resumen
        $cartItems = $request->user()->cart()
                            ->whereRelation('product', 'store_id', $store->id)
                            ->with('product', 'variant') 
                            ->get();

        // Si el carrito está vacío, no tiene sentido estar aquí, lo mandamos al catálogo
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
        // Esta línea para los emojis la dejamos por si las moscas
        mb_internal_encoding("UTF-8"); 
        
        // 1. Validamos los datos del formulario
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'required|email|max:255',
            'customer_address' => 'required|string|max:1000',
        ]);

        // 2. Volvemos a traer los items del carrito (por seguridad)
        $cartItems = $request->user()->cart()
                            ->whereRelation('product', 'store_id', $store->id)
                            ->with('product', 'variant')
                            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('catalogo.index', ['store' => $store->slug])->withErrors('Tu carrito está vacío.');
        }

        // 3. Calculamos el total en el backend (más seguro que confiar en el frontend)
        $totalPrice = $cartItems->reduce(function ($total, $item) {
            $price = $item->variant->price ?? $item->product->price;
            return $total + ($price * $item->quantity);
        }, 0);

        // 4. Creamos la orden en la base de datos
        $order = $store->orders()->create([
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'],
            'customer_address' => $validated['customer_address'],
            'total_price' => $totalPrice,
            'status' => 'recibido', // El primer estado
        ]);

        // 5. Preparamos el mensaje para WhatsApp
        $whatsappMessage = "¡Hola! Quiero realizar el siguiente pedido:\n\n";
        $whatsappMessage .= "*Orden #{$order->id}*\n\n";

        // 6. Creamos los items de la orden y los añadimos al mensaje
        foreach ($cartItems as $item) {
            $price = $item->variant->price ?? $item->product->price;

            // Guardamos el item en la base de datos
            $order->items()->create([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $price,
                'product_name' => $item->product->name,
                'variant_options' => $item->variant->options ?? null,
            ]);

            // Lo añadimos al texto de WhatsApp
            $whatsappMessage .= "📦 *{$item->product->name}*";
            if ($item->variant) {
                $optionsText = collect($item->variant->options)->map(fn($val, $key) => "{$key}: {$val}")->implode(', ');
                $whatsappMessage .= " ({$optionsText})";
            }
            $whatsappMessage .= "\n";
            $whatsappMessage .= "   - Cantidad: {$item->quantity}\n";
            $whatsappMessage .= "   - Precio: $" . number_format($price * $item->quantity, 0, ',', '.') . "\n\n";
        }

        $whatsappMessage .= "*Total del Pedido:* $" . number_format($totalPrice, 0, ',', '.') . "\n\n";
        $whatsappMessage .= "*Datos de Envío:*\n";
        $whatsappMessage .= "👤 *Nombre:* {$validated['customer_name']}\n";
        $whatsappMessage .= "📞 *Teléfono:* {$validated['customer_phone']}\n";
        $whatsappMessage .= "📍 *Dirección:* {$validated['customer_address']}\n\n";
        $whatsappMessage .= "¡Gracias!";

        // 7. Vaciamos el carrito del usuario
        $request->user()->cart()->whereIn('id', $cartItems->pluck('id'))->delete();


        // ===== ESTE ES EL BLOQUE QUE CAMBIAMOS =====
        // 8. Preparamos la URL de WhatsApp (¡Ahora es dinámico y seguro!)
        if (empty($store->phone)) {
            // Si la tienda no tiene teléfono, no podemos mandar el WhatsApp.
            // Devolvemos al usuario con un error claro.
            // OJO: Este error lo verías en el formulario si lo mostramos, por ahora lo dejamos así.
            return back()->withErrors(['phone' => 'Lo sentimos, esta tienda no tiene un número de WhatsApp configurado para recibir pedidos.']);
        }

        // Limpiamos el número para asegurarnos de que solo contenga dígitos (quita '+', espacios, etc.)
        $storePhoneNumber = preg_replace('/[^0-9]/', '', $store->phone);
        $whatsappUrl = 'https://wa.me/' . $storePhoneNumber . '?text=' . rawurlencode($whatsappMessage);

        // 9. Redirigimos al usuario a WhatsApp
        return Inertia::location($whatsappUrl);
        // ===========================================
    }
}