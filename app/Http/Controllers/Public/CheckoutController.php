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

        // 4. Creamos la orden en la base de datos con un consecutivo por tienda
        $store->refresh();
        $nextSequence = ((int) ($store->order_sequence ?? 0)) + 1;
        $order = $store->orders()->create([
            'sequence_number' => $nextSequence,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'],
            'customer_address' => $validated['customer_address'],
            'total_price' => $totalPrice,
            'status' => 'recibido', // El primer estado
        ]);

        // Incrementamos el contador en la tienda
        $store->forceFill(['order_sequence' => $nextSequence])->save();

        // 5. Preparamos el mensaje para WhatsApp (emojis con codepoints para evitar problemas de codificación)
        // Usamos viñetas simples para máxima compatibilidad entre plataformas
        $E_PACKAGE = "\u{2022}"; // •
        $E_PERSON  = "\u{2022}"; // •
        $E_PHONE   = "\u{2022}"; // •
        $E_PIN     = "\u{2022}"; // •

        $whatsappMessage = "¡Hola! Quiero realizar el siguiente pedido:\n\n";
        $whatsappMessage .= "*Orden #{$order->sequence_number}*\n\n";

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
            $whatsappMessage .= $E_PACKAGE . " *{$item->product->name}*";
            if ($item->variant) {
                $optionsText = collect($item->variant->options)->map(fn($val, $key) => "{$key}: {$val}")->implode(', ');
                $whatsappMessage .= " ({$optionsText})";
            }
            $whatsappMessage .= "\n";
            $whatsappMessage .= "   - Cantidad: {$item->quantity}\n";
            $whatsappMessage .= "   - Precio unitario: $" . number_format($price, 0, ',', '.') . "\n\n";
        }

        $whatsappMessage .= "*Total del Pedido:* $" . number_format($totalPrice, 0, ',', '.') . "\n\n";
        $whatsappMessage .= "*Datos de Envío:*\n";
        $whatsappMessage .= $E_PERSON . " *Nombre:* {$validated['customer_name']}\n";
        $whatsappMessage .= $E_PHONE . " *Teléfono:* {$validated['customer_phone']}\n";
        $whatsappMessage .= $E_PIN . " *Dirección:* {$validated['customer_address']}\n\n";
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

        // Normalizamos y aseguramos UTF-8 para evitar caracteres "raros" en emojis
        if (class_exists(\Normalizer::class)) {
            $whatsappMessage = \Normalizer::normalize($whatsappMessage, \Normalizer::FORM_C);
        }
        $whatsappMessage = mb_convert_encoding($whatsappMessage, 'UTF-8', 'UTF-8');

        // Construimos la query de forma segura (RFC3986) para compatibilidad con WhatsApp
        $query = http_build_query(['text' => $whatsappMessage], '', '&', PHP_QUERY_RFC3986);
        $whatsappUrl = 'https://wa.me/' . $storePhoneNumber . '?' . $query;

        // 9. Redirigimos al usuario a WhatsApp
        return Inertia::location($whatsappUrl);
        // ===========================================
    }
}