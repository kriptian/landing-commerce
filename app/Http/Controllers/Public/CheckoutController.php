<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function index(Request $request, Store $store)
    {
        $store = $store->fresh();
        $cartItems = $this->cartItems($request, $store);

        if ($cartItems->isEmpty()) {
            return redirect()->route('catalogo.index', ['store' => $store->slug]);
        }

        $customer = $request->user('customer');
        if (! $customer || (int) $customer->store_id !== (int) $store->id) {
            $customer = null;
        }

        $addresses = $customer
            ? $customer->addresses()->get()->map(fn ($address) => [
                'id' => $address->id,
                'label' => $address->label,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                'is_default' => $address->is_default,
                'full_address' => $address->full_address,
            ])
            : collect();

        return Inertia::render('Public/CheckoutPage', [
            'cartItems' => $cartItems,
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'logo_url' => $store->logo_url,
                'promo_active' => $store->promo_active,
                'promo_discount_percent' => $store->promo_discount_percent,
                'delivery_cost_active' => $store->delivery_cost_active,
                'delivery_cost' => $store->delivery_cost,
                'catalog_use_default' => $store->catalog_use_default ?? true,
                'catalog_body_bg_color' => $store->catalog_body_bg_color ?? '#FFFFFF',
                'catalog_body_text_color' => $store->catalog_body_text_color ?? '#1F2937',
                'catalog_input_bg_color' => $store->catalog_input_bg_color ?? '#FFFFFF',
                'catalog_input_text_color' => $store->catalog_input_text_color ?? '#1F2937',
                'catalog_button_bg_color' => $store->catalog_button_bg_color ?? '#2563EB',
                'catalog_button_text_color' => $store->catalog_button_text_color ?? '#FFFFFF',
            ],
            'idempotencyKey' => (string) Str::uuid(),
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ] : null,
            'addresses' => $addresses,
        ]);
    }

    public function validateCoupon(Request $request, Store $store)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $coupon = Coupon::query()
            ->where('store_id', $store->id)
            ->where('code', $validated['code'])
            ->first();

        if (! $coupon) {
            return response()->json(['valid' => false, 'message' => 'Cupón no válido']);
        }

        $customer = $request->user('customer');
        if (! $customer || (int) $customer->store_id !== (int) $store->id) {
            return response()->json([
                'valid' => false,
                'message' => 'Debes iniciar sesión para usar este cupón',
                'code' => 'LOGIN_REQUIRED',
            ]);
        }

        $cartItems = $this->cartItems($request, $store);
        $cartTotal = $this->subtotal($cartItems, $store);
        if (! $coupon->isValid($customer->id, $cartTotal)) {
            return response()->json(['valid' => false, 'message' => 'Este cupón no es válido o ha expirado']);
        }

        $eligibleSubtotal = $this->couponEligibleSubtotal($coupon, $cartItems, $store);
        if ($eligibleSubtotal <= 0) {
            return response()->json(['valid' => false, 'message' => 'Este cupón no aplica a los productos del carrito']);
        }

        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'max_discount' => $coupon->max_discount,
                'min_purchase' => $coupon->min_purchase,
            ],
            'discount_amount' => $coupon->calculateDiscount($eligibleSubtotal),
        ]);
    }

    public function calculateCartTotal(Request $request, Store $store)
    {
        return $this->subtotal($this->cartItems($request, $store), $store);
    }

    public function store(Request $request, Store $store)
    {
        mb_internal_encoding('UTF-8');

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $digits = preg_replace('/\D+/', '', (string) $value);
                    if (! preg_match('/^[1-9]\d{6,14}$/', $digits)) {
                        $fail('El teléfono del cliente no es válido.');
                    }
                },
            ],
            'customer_email' => 'required|email|max:255',
            'customer_address' => 'required|string|max:1000',
            'address_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:50',
            'idempotency_key' => 'required|uuid',
        ]);

        $this->validatedWhatsappPhone($store);

        $customer = $request->user('customer');
        if (! $customer || (int) $customer->store_id !== (int) $store->id) {
            $customer = null;
        }

        $address = null;
        if (! empty($validated['address_id'])) {
            if (! $customer) {
                throw ValidationException::withMessages([
                    'address_id' => 'La dirección seleccionada no pertenece al cliente autenticado.',
                ]);
            }

            $address = Address::query()
                ->whereKey($validated['address_id'])
                ->where('customer_id', $customer->id)
                ->whereHas('customer', fn ($query) => $query->where('store_id', $store->id))
                ->first();

            if (! $address) {
                throw ValidationException::withMessages([
                    'address_id' => 'La dirección seleccionada no pertenece al cliente autenticado.',
                ]);
            }
        }

        try {
            $result = DB::transaction(function () use ($request, $store, $validated, $customer, $address) {
                $lockedStore = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
                $storePhoneNumber = $this->validatedWhatsappPhone($lockedStore);
                $existingOrder = $lockedStore->orders()
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();

                if ($existingOrder) {
                    return [
                        'order' => $existingOrder,
                        'created' => false,
                        'storePhoneNumber' => $storePhoneNumber,
                    ];
                }

                $cartItems = $this->cartItems($request, $lockedStore, true);

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Tu carrito está vacío o contiene productos no disponibles.']);
                }

                $subtotal = $this->subtotal($cartItems, $lockedStore);
                $coupon = null;
                $discountAmount = 0;

                if (! empty($validated['coupon_code'])) {
                    if (! $customer) {
                        throw ValidationException::withMessages(['coupon_code' => 'Debes iniciar sesión para usar este cupón.']);
                    }

                    $coupon = Coupon::query()
                        ->where('store_id', $lockedStore->id)
                        ->where('code', $validated['coupon_code'])
                        ->lockForUpdate()
                        ->first();

                    if (! $coupon || ! $coupon->isValid($customer->id, $subtotal)) {
                        throw ValidationException::withMessages(['coupon_code' => 'Este cupón no es válido o ha expirado.']);
                    }

                    $eligibleSubtotal = $this->couponEligibleSubtotal($coupon, $cartItems, $lockedStore);
                    if ($eligibleSubtotal <= 0) {
                        throw ValidationException::withMessages(['coupon_code' => 'Este cupón no aplica a los productos del carrito.']);
                    }

                    $discountAmount = $coupon->calculateDiscount($eligibleSubtotal);
                }

                $deliveryCost = $lockedStore->delivery_cost_active && $lockedStore->delivery_cost > 0
                    ? (float) $lockedStore->delivery_cost
                    : 0;
                $nextSequence = ((int) $lockedStore->order_sequence) + 1;
                $order = $lockedStore->orders()->create([
                    'sequence_number' => $nextSequence,
                    'idempotency_key' => $validated['idempotency_key'],
                    'customer_id' => $customer?->id,
                    'address_id' => $address?->id,
                    'coupon_id' => $coupon?->id,
                    'customer_name' => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'],
                    'customer_email' => $validated['customer_email'],
                    'customer_address' => $validated['customer_address'],
                    'total_price' => max(0, $subtotal - $discountAmount) + $deliveryCost,
                    'discount_amount' => $discountAmount,
                    'delivery_cost' => $deliveryCost,
                    'status' => 'recibido',
                ]);
                $lockedStore->forceFill(['order_sequence' => $nextSequence])->save();

                if ($coupon && $discountAmount > 0) {
                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'customer_id' => $customer->id,
                        'order_id' => $order->id,
                        'discount_amount' => $discountAmount,
                        'used_at' => now(),
                    ]);
                }

                foreach ($cartItems as $item) {
                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $this->unitPrice($item, $lockedStore),
                        'product_name' => $item->product->name,
                        'variant_options' => $item->variant?->options,
                    ]);
                }

                if ($request->user('web')) {
                    $request->user('web')->cart()->whereIn('id', $cartItems->pluck('id'))->delete();
                }

                return [
                    'order' => $order,
                    'created' => true,
                    'storePhoneNumber' => $storePhoneNumber,
                ];
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateIdempotencyKey($exception)) {
                throw $exception;
            }

            $order = $store->orders()
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if (! $order) {
                throw $exception;
            }

            $result = [
                'order' => $order,
                'created' => false,
                'storePhoneNumber' => $this->validatedWhatsappPhone($store),
            ];
        }

        // La sesión no participa en la transacción; solo se modifica tras un commit exitoso.
        if ($result['created'] && ! $request->user('web')) {
            $this->clearGuestCartForStore($request, $store);
        }

        return $this->whatsappLocation($result['order'], $result['storePhoneNumber']);
    }

    private function whatsappLocation(Order $order, string $storePhoneNumber)
    {
        $order->loadMissing(['items', 'coupon']);
        $coupon = $order->coupon;
        $discountAmount = (float) $order->discount_amount;
        $deliveryCost = (float) $order->delivery_cost;

        $whatsappMessage = "¡Hola! Quiero realizar el siguiente pedido:\n\n";
        $whatsappMessage .= "*Orden #{$order->sequence_number}*\n\n";
        foreach ($order->items as $item) {
            $whatsappMessage .= "• *{$item->product_name}*\n";
            if ($item->variant_options) {
                $optionsText = collect($item->variant_options)->map(fn ($value, $key) => "{$key}: {$value}")->implode(', ');
                $whatsappMessage .= "   - {$optionsText}\n";
            }
            $whatsappMessage .= "   - Cantidad: {$item->quantity}\n";
            $whatsappMessage .= '   - Precio unitario: $'.number_format($item->unit_price, 0, ',', '.')."\n\n";
        }

        $subtotal = $order->items->sum(fn ($item) => $item->unit_price * $item->quantity);
        $whatsappMessage .= '*Subtotal:* $'.number_format($subtotal, 0, ',', '.')."\n";
        if ($coupon && $discountAmount > 0) {
            $discountType = $coupon->type === 'percentage'
                ? $coupon->value.'%'
                : '$'.number_format($coupon->value, 0, ',', '.');
            $whatsappMessage .= "*Descuento (Cupón: {$coupon->code} - {$discountType}):* -$".number_format($discountAmount, 0, ',', '.')."\n";
        }
        if ($deliveryCost > 0) {
            $whatsappMessage .= '*Costo de envío:* $'.number_format($deliveryCost, 0, ',', '.')."\n";
        }
        $whatsappMessage .= '*Total del Pedido:* $'.number_format($order->total_price, 0, ',', '.')."\n\n";
        $whatsappMessage .= "*Datos de Envío:*\n";
        $whatsappMessage .= "• *Nombre:* {$order->customer_name}\n";
        $whatsappMessage .= "• *Teléfono:* {$order->customer_phone}\n";
        $whatsappMessage .= "• *Dirección:* {$order->customer_address}\n\n";
        $whatsappMessage .= '¡Gracias!';

        if (class_exists(\Normalizer::class)) {
            $whatsappMessage = \Normalizer::normalize($whatsappMessage, \Normalizer::FORM_C);
        }
        $whatsappMessage = mb_convert_encoding($whatsappMessage, 'UTF-8', 'UTF-8');
        $query = http_build_query(['text' => $whatsappMessage], '', '&', PHP_QUERY_RFC3986);

        return Inertia::location('https://wa.me/'.$storePhoneNumber.'?'.$query);
    }

    private function isDuplicateIdempotencyKey(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'checkout_key');
    }

    private function clearGuestCartForStore(Request $request, Store $store): void
    {
        $cart = collect($request->session()->get('guest_cart', []));
        $storeProductIds = Product::query()
            ->where('store_id', $store->id)
            ->whereIn('id', $cart->pluck('product_id')->filter())
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true]);

        $remaining = $cart->reject(fn ($row) => (int) ($row['store_id'] ?? 0) === (int) $store->id
            || isset($storeProductIds[(int) ($row['product_id'] ?? 0)]));

        if ($remaining->isEmpty()) {
            $request->session()->forget('guest_cart');
        } else {
            $request->session()->put('guest_cart', $remaining->all());
        }
    }

    private function cartItems(Request $request, Store $store, bool $lock = false): Collection
    {
        if ($request->user('web')) {
            $query = $request->user('web')->cart()->whereRelation('product', 'store_id', $store->id);
            if ($lock) {
                $query->lockForUpdate();
            }

            $rows = $query->get();
        } else {
            $rows = collect($request->session()->get('guest_cart', []))->map(function ($row) {
                return (object) [
                    'id' => null,
                    'product_id' => $row['product_id'] ?? null,
                    'product_variant_id' => $row['product_variant_id'] ?? null,
                    'quantity' => $row['quantity'] ?? null,
                ];
            });
        }

        return $rows->map(function ($item) use ($store, $lock) {
            if (! filter_var($item->quantity, FILTER_VALIDATE_INT) || (int) $item->quantity < 1) {
                return null;
            }

            $productQuery = Product::query()
                ->whereKey($item->product_id)
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->select([
                    'id', 'name', 'price', 'retail_price', 'wholesale_price',
                    'promo_active', 'promo_discount_percent', 'track_inventory', 'quantity',
                    'alert', 'category_id', 'store_id', 'short_description', 'long_description',
                    'specifications', 'is_featured', 'is_active', 'variant_attributes',
                ])
                ->with('images');
            if ($lock) {
                $productQuery->lockForUpdate();
            }
            $product = $productQuery->first();

            if (! $product) {
                return null;
            }

            $variant = null;
            if ($item->product_variant_id) {
                $variantQuery = ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->where('product_id', $product->id)
                    ->select([
                        'id', 'product_id', 'options', 'price', 'retail_price',
                        'wholesale_price', 'stock', 'alert', 'sku',
                    ]);
                if ($lock) {
                    $variantQuery->lockForUpdate();
                }
                $variant = $variantQuery->first();
                if (! $variant) {
                    return null;
                }
                if ($variant && empty($variant->options)) {
                    $variant = null;
                }
            } else {
                $hasRealVariants = $product->variants()
                    ->get(['id', 'options'])
                    ->contains(fn ($productVariant) => ! empty($productVariant->options));
                if ($hasRealVariants) {
                    return null;
                }
            }

            if ($product->track_inventory) {
                $available = $variant ? (int) $variant->stock : (int) $product->quantity;
                if ((int) $item->quantity > $available) {
                    return null;
                }
            }

            $item->quantity = (int) $item->quantity;
            $item->product_id = $product->id;
            $item->product_variant_id = $variant?->id;
            if (method_exists($item, 'setRelation')) {
                $item->setRelation('product', $product);
                $item->setRelation('variant', $variant);
            } else {
                $item->product = $product;
                $item->variant = $variant;
            }

            return $item;
        })->filter()->values();
    }

    private function subtotal(Collection $cartItems, Store $store): float
    {
        return $cartItems->sum(fn ($item) => $this->unitPrice($item, $store) * $item->quantity);
    }

    private function couponEligibleSubtotal(Coupon $coupon, Collection $cartItems, Store $store): float
    {
        if (! $coupon->products()->exists()) {
            return $this->subtotal($cartItems, $store);
        }

        $eligibleProductIds = $coupon->products()
            ->where('products.store_id', $store->id)
            ->pluck('products.id');

        return $this->subtotal(
            $cartItems->filter(fn ($item) => $eligibleProductIds->contains($item->product_id)),
            $store,
        );
    }

    private function unitPrice(object $item, Store $store): int
    {
        $base = $item->variant && $item->variant->price !== null && $item->variant->price !== ''
            ? (float) $item->variant->price
            : (float) $item->product->price;
        $percent = 0;

        if ($store->promo_active && (int) $store->promo_discount_percent > 0) {
            $percent = (int) $store->promo_discount_percent;
        } elseif ($item->product->promo_active && (int) $item->product->promo_discount_percent > 0) {
            $percent = (int) $item->product->promo_discount_percent;
        }

        return $percent > 0
            ? (int) round($base * (100 - $percent) / 100)
            : (int) round($base);
    }

    private function validatedWhatsappPhone(Store $store): string
    {
        $phone = preg_replace('/\D+/', '', (string) $store->phone);
        if (! preg_match('/^[1-9]\d{7,14}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => 'La tienda no tiene un número de WhatsApp válido para recibir pedidos.',
            ]);
        }

        return $phone;
    }
}
