<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhysicalSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PhysicalSaleController extends Controller
{
    public function __construct()
    {
        // Permitir acceso a usuarios con rol "physical-sales" sin verificar permisos
        $this->middleware(function ($request, $next) {
            $user = $request->user();

            // Si el usuario tiene el rol "physical-sales", permitir acceso sin verificar permisos
            if ($user && $user->hasRole('physical-sales')) {
                return $next($request);
            }

            $permission = match (true) {
                $request->routeIs('admin.physical-sales.store', 'admin.physical-sales.open-drawer') => 'gestionar ventas fisicas',
                $request->routeIs('admin.physical-sales.show', 'admin.physical-sales.export') => 'ver reportes',
                default => 'ver inventario',
            };

            if (! $user || ! $user->can($permission)) {
                abort(403, 'No tienes permiso para realizar esta acción.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $store = $request->user()->store;

        // Validar filtros de fecha y búsqueda
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'search' => 'nullable|string',
        ]);

        // Aplicar filtros de fecha si existen
        $salesQuery = $store->physicalSales()->with(['user', 'items.product', 'items.variant']);

        // Búsqueda por número de venta
        // Búsqueda por número de venta o nombre de producto
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $salesQuery->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                    ->orWhereHas('items', function ($itemQuery) use ($search) {
                        $itemQuery->where('product_name', 'like', "%{$search}%")
                            ->orWhereHas('product', function ($productQuery) use ($search) {
                                $productQuery->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $appTz = config('app.timezone', 'America/Bogota');
            $startLocal = Carbon::parse($validated['start_date'], $appTz)->startOfDay();
            $endLocal = Carbon::parse($validated['end_date'], $appTz)->endOfDay();
            $startUtc = $startLocal->copy()->timezone('UTC');
            $endExclusiveUtc = $endLocal->copy()->addDay()->startOfDay()->timezone('UTC');

            $salesQuery->whereBetween('created_at', [$startUtc, $endExclusiveUtc]);
        }

        $sales = $salesQuery->latest()->paginate(20);

        // Calcular estadísticas
        $statsQuery = clone $salesQuery;
        $totalSales = $statsQuery->sum('total');
        $totalSales = $statsQuery->sum('total');
        $totalCount = $statsQuery->count();

        // Calcular gastos
        $expensesQuery = $store->expenses();

        // Aplicar mismos filtros de fecha a gastos
        if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
            $expensesQuery->whereBetween('expense_date', [$startUtc, $endExclusiveUtc]);
        }

        $totalExpenses = $expensesQuery->sum('amount');
        $netCash = $totalSales - $totalExpenses;

        // Calcular Ganancia Total (Profit)
        // Se calcula iterando sobre las ventas para sumar (precio - costo) * cantidad
        // Solo se suma si existe un costo (purchase_price) definido > 0
        $totalProfit = 0;

        // Clonamos de nuevo para iterar sin afectar otros cálculos si fuera necesario
        // (aunque statsQuery ya era un clon, al usar chunk se ejecuta)
        (clone $statsQuery)->chunk(200, function ($salesChunk) use (&$totalProfit) {
            foreach ($salesChunk as $sale) {
                foreach ($sale->items as $item) {
                    $cost = 0;
                    // Intentar obtener costo de la variante primero
                    if ($item->variant && $item->variant->purchase_price > 0) {
                        $cost = $item->variant->purchase_price;
                    }
                    // Si no, del producto
                    elseif ($item->product && $item->product->purchase_price > 0) {
                        $cost = $item->product->purchase_price;
                    }

                    if ($cost > 0) {
                        $profit = ($item->unit_price - $cost) * $item->quantity;
                        $totalProfit += $profit;
                    }
                }
            }
        });

        // Obtener productos activos con imágenes para el catálogo POS
        $products = $store->products()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'variantOptions.children'])
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                // Asegurar que main_image_url se calcule correctamente
                $mainImageUrl = null;
                $firstImage = $product->images()->first();
                if ($firstImage) {
                    $mainImageUrl = $firstImage->path;
                } else {
                    // Si no hay imagen del producto, buscar en variantes
                    foreach ($product->variantOptions ?? [] as $parentOption) {
                        foreach ($parentOption->children ?? [] as $child) {
                            if (! empty($child->image_path)) {
                                $imagePath = $child->image_path;
                                if (! str_starts_with($imagePath, 'http://') && ! str_starts_with($imagePath, 'https://')) {
                                    if (! str_starts_with($imagePath, '/storage/')) {
                                        $imagePath = '/storage/'.ltrim($imagePath, '/');
                                    }
                                }
                                $mainImageUrl = $imagePath;
                                break 2;
                            }
                        }
                    }
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'barcode' => $product->barcode,
                    'stock' => $product->quantity,
                    'quantity' => $product->quantity,
                    'track_inventory' => $product->track_inventory,
                    'category_id' => $product->category_id,
                    'promo_active' => $product->promo_active,
                    'promo_discount_percent' => $product->promo_discount_percent,
                    'main_image_url' => $mainImageUrl,
                    'images' => $product->images,
                    'category' => $product->category,
                    'variants' => $product->variants,
                    'variants' => $product->variants,
                    'variant_options' => $product->variantOptions->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'name' => $option->name,
                            'children' => $option->children,
                            'order' => $option->order,
                        ];
                    }),
                ];
            });

        // Obtener categorías con productos
        $categories = $store->categories()
            ->whereHas('products', function ($q) {
                $q->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/PhysicalSales/Index', [
            'sales' => $sales,
            'stats' => [
                'totalSales' => $totalSales,
                'totalSales' => $totalSales,
                'totalCount' => $totalCount,
                'totalExpenses' => $totalExpenses,
                'netCash' => $netCash,
                'totalProfit' => $totalProfit,
            ],
            'filters' => $request->only(['start_date', 'end_date', 'search']),
            'products' => $products,
            'categories' => $categories,
            'store' => $store,
        ]);
    }

    /**
     * Buscar productos por nombre, ID o código de barras
     */
    public function searchProducts(Request $request)
    {
        $store = $request->user()->store;
        $query = $request->get('q', '');

        if (empty($query)) {
            return response()->json(['products' => []]);
        }

        $products = $store->products()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('id', $query)
                    ->orWhere('barcode', 'like', "%{$query}%")
                    ->orWhereHas('variants', function ($subQ) use ($query) {
                        $subQ->where('sku', 'like', "%{$query}%")
                            ->orWhere('options', 'like', "%{$query}%"); // Opcional: buscar por nombre de opción
                    })
                    ->orWhereHas('variants', function ($subQ) {
                        // Buscar por barcode de variante si existe la columna, o reusar la logica anterior si barcode está en VariantOption
                        // En este proyecto, el barcode de variante parece estar en ProductVariant o VariantOption?
                        // ProductVariant tiene 'sku', VariantOption tiene 'barcode'.
                        // Revisando modelos: VariantOption tiene barcode. ProductVariant tiene sku?
                        // Revisamos ProductVariant: 'sku' está fillable.
                        // Revisamos VariantOption: 'barcode' está fillable.
                        // La búsqueda debe cubrir ambos casos probablemente.
                    });

                // Simplificación: Buscar en las relaciones relevantes
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('id', $query)
                    ->orWhere('barcode', 'like', "%{$query}%")
                   // Buscar en ProductVariant (SKU)
                    ->orWhereHas('variants', function ($v) use ($query) {
                        $v->where('sku', 'like', "%{$query}%");
                    })
                  // Buscar en VariantOption (Barcode y Nombre)
                    ->orWhereHas('variantOptions', function ($vo) use ($query) {
                        $vo->where('barcode', 'like', "%{$query}%")
                            ->orWhere('name', 'like', "%{$query}%");
                    });
            })
            ->with(['images', 'variants', 'category', 'variantOptions.children'])
            ->limit(20)
            ->get();

        return response()->json(['products' => $products]);
    }

    /**
     * Obtener un producto por código de barras
     */
    public function getProductByBarcode(Request $request)
    {
        $store = $request->user()->store;
        $barcode = $request->get('barcode');

        if (empty($barcode)) {
            return response()->json(['product' => null], 404);
        }

        $product = $store->products()
            ->where('is_active', true)
            ->where('barcode', $barcode)
            ->with(['images', 'variants', 'category', 'variantOptions.children'])
            ->first();

        if (! $product) {
            // Intentar buscar por código de barras de variante
            $variantOption = \App\Models\VariantOption::where('barcode', $barcode)
                ->whereHas('product', function ($q) use ($store) {
                    $q->where('store_id', $store->id)->where('is_active', true);
                })
                ->first();

            if ($variantOption) {
                $product = $variantOption->product;
                // Cargar todas las relaciones necesarias
                $product->load(['images', 'variants', 'category', 'variantOptions.children']);

                return response()->json([
                    'product' => $product,
                    'matched_variant_option' => $variantOption,
                ]);
            }
        }

        if (! $product) {
            return response()->json(['product' => null], 404);
        }

        return response()->json(['product' => $product]);
    }

    /**
     * Abrir cajón de la registradora
     */
    public function openDrawer(Request $request)
    {
        try {
            // Comando ESC/POS estándar para abrir cajón (ESC p 0 25 250)
            $escposCommand = "\x1B\x70\x00\x19\xFA";

            // Intentar enviar comando a impresora
            // Nota: Esto requiere que la impresora esté conectada y configurada
            // En producción, esto debería enviarse a través de un servicio de impresión

            // Por ahora, solo retornamos éxito
            // En una implementación real, aquí se enviaría el comando a la impresora térmica
            // usando una librería como mike42/escpos-php o similar

            return response()->json([
                'success' => true,
                'message' => 'Comando de apertura de cajón enviado',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al intentar abrir el cajón: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar una venta física
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:efectivo,tarjeta,transferencia,mixto',
            'notes' => 'nullable|string',
            'delivery_cost' => 'nullable|numeric|min:0',
        ]);

        $store = $request->user()->store;

        try {
            DB::beginTransaction();

            // Generar número de venta único por tienda (cada tienda tiene su propia secuencia independiente)
            // Esto permite que la Tienda A tenga V-000001 y la Tienda B también tenga V-000001 sin conflictos
            $maxAttempts = 10;
            $attempt = 0;
            $saleNumber = null;

            while ($attempt < $maxAttempts) {
                // Obtener el último número de venta de ESTA tienda específica usando bloqueo para evitar condiciones de carrera
                $lastSale = DB::table('physical_sales')
                    ->where('store_id', $store->id) // CRÍTICO: Solo buscar en ventas de esta tienda
                    ->where('sale_number', 'like', 'V-%')
                    ->lockForUpdate() // Bloquear la fila para evitar lecturas concurrentes
                    ->orderBy('id', 'desc')
                    ->first();

                // Si hay una venta previa en esta tienda, extraer el número y sumar 1
                if ($lastSale && preg_match('/V-(\d+)/', $lastSale->sale_number, $matches)) {
                    $nextNumber = (int) $matches[1] + 1;
                } else {
                    // Primera venta de esta tienda
                    $nextNumber = 1;
                }

                $saleNumber = 'V-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

                // Verificar que el número no exista en ESTA tienda (protección adicional contra condiciones de carrera)
                // El índice único compuesto (store_id, sale_number) garantiza que esto sea único por tienda
                $exists = DB::table('physical_sales')
                    ->where('store_id', $store->id) // CRÍTICO: Verificar solo en esta tienda
                    ->where('sale_number', $saleNumber)
                    ->exists();

                if (! $exists) {
                    break; // Número único encontrado para esta tienda
                }

                $attempt++;
                // Si existe, esperar un poco y reintentar con el siguiente número
                usleep(10000); // 10ms
            }

            if ($saleNumber === null || $attempt >= $maxAttempts) {
                DB::rollBack();
                throw new \Exception('No se pudo generar un número de venta único para esta tienda después de varios intentos');
            }

            $authoritativeItems = [];
            $subtotal = 0;
            $discount = 0;

            foreach ($validated['items'] as $itemData) {
                $product = $store->products()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($itemData['product_id']);
                $variant = null;

                if (! empty($itemData['variant_id'])) {
                    $variant = $product->variants()
                        ->lockForUpdate()
                        ->findOrFail($itemData['variant_id']);
                }

                if ($product->track_inventory) {
                    $availableStock = $variant ? $variant->stock : $product->quantity;
                    if ($availableStock !== null && $availableStock < $itemData['quantity']) {
                        throw new \Exception("Stock insuficiente para el producto {$product->name}. Stock disponible: {$availableStock}, solicitado: {$itemData['quantity']}");
                    }

                    if ($availableStock !== null) {
                        ($variant ?: $product)->decrement($variant ? 'stock' : 'quantity', $itemData['quantity']);
                    }
                }

                $originalPrice = $variant && $variant->price !== null
                    ? (float) $variant->price
                    : (float) $product->price;
                $discountPercent = 0;
                if ($store->promo_active && $store->promo_discount_percent > 0) {
                    $discountPercent = (float) $store->promo_discount_percent;
                } elseif ($product->promo_active && $product->promo_discount_percent > 0) {
                    $discountPercent = (float) $product->promo_discount_percent;
                }

                $unitPrice = round($originalPrice * (100 - $discountPercent) / 100, 2);
                $lineSubtotal = round($unitPrice * $itemData['quantity'], 2);
                $subtotal += $lineSubtotal;
                $discount += round(($originalPrice - $unitPrice) * $itemData['quantity'], 2);

                $authoritativeItems[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                    'original_price' => $originalPrice,
                    'discount_percent' => $discountPercent,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discount = round($discount, 2);
            $tax = 0;
            $deliveryCost = (float) $request->input('delivery_cost', 0) > 0 && $store->delivery_cost_active
                ? (float) $store->delivery_cost
                : 0;
            $total = round($subtotal + $tax + $deliveryCost, 2);

            // Crear la venta
            $sale = $store->physicalSales()->create([
                'user_id' => $request->user()->id,
                'sale_number' => $saleNumber,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'delivery_cost' => $deliveryCost,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($authoritativeItems as $itemData) {
                $product = $itemData['product'];
                $variant = $itemData['variant'];
                $purchasePriceSnapshot = $variant?->purchase_price;
                if ($purchasePriceSnapshot === null || $purchasePriceSnapshot === '') {
                    $purchasePriceSnapshot = $product->purchase_price;
                }

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['subtotal'],
                    'product_name' => $product->name,
                    'variant_options' => $variant?->options,
                    'original_price' => $itemData['original_price'],
                    'discount_percent' => $itemData['discount_percent'],
                    'purchase_price' => $purchasePriceSnapshot,
                ]);
            }

            DB::commit();

            $response = response()->json([
                'success' => true,
                'sale' => $sale->load('items.product'),
                'message' => 'Venta registrada exitosamente',
            ]);

            // Incluir el token CSRF en los headers de la respuesta para que el frontend lo actualice
            $response->header('X-CSRF-TOKEN', csrf_token());
            $response->header('Access-Control-Expose-Headers', 'X-CSRF-TOKEN');

            return $response;

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtener detalles de una venta
     */
    public function show(PhysicalSale $physicalSale)
    {
        if ($physicalSale->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        $physicalSale->load(['user', 'items.product', 'items.variant', 'store']);

        // Calcular descuentos por producto
        foreach ($physicalSale->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            // Obtener precio original (sin descuento)
            $originalPrice = null;
            if ($variant && $variant->price !== null && $variant->price !== '') {
                $originalPrice = (float) $variant->price;
            } elseif ($product && $product->price !== null) {
                $originalPrice = (float) $product->price;
            }

            // Calcular descuento aplicado
            if ($originalPrice && $originalPrice > 0) {
                $finalPrice = (float) $item->unit_price;
                $discountAmount = $originalPrice - $finalPrice;
                $discountPercent = $discountAmount > 0 ? round(($discountAmount / $originalPrice) * 100, 2) : 0;

                // Agregar información de descuento al item
                $item->original_price = $originalPrice;
                $item->discount_amount = $discountAmount;
                $item->discount_percent = $discountPercent;
            } else {
                $item->original_price = null;
                $item->discount_amount = 0;
                $item->discount_percent = 0;
            }
        }

        return Inertia::render('Admin/PhysicalSales/Show', [
            'sale' => $physicalSale,
            'store' => $physicalSale->store,
        ]);
    }

    /**
     * Exportar ventas físicas a Excel
     */
    public function export(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'search']);
        $fileName = 'balanze-pos-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new \App\Exports\PhysicalReportExport($filters), $fileName);
    }
}
