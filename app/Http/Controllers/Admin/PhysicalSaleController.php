<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\PhysicalSale;
use App\Models\PhysicalSaleItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\PhysicalSalesExport;
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
            
            // Para otros usuarios, verificar permisos normalmente
            if ($request->routeIs('admin.physical-sales.index')) {
                if (!$user || !$user->can('ver inventario')) {
                    abort(403, 'No tienes permiso para acceder a esta sección.');
                }
            }
            
            if ($request->routeIs('admin.physical-sales.store')) {
                if (!$user || !$user->can('crear productos')) {
                    abort(403, 'No tienes permiso para realizar esta acción.');
                }
            }
            
            return $next($request);
        })->only(['index', 'store']);
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
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $salesQuery->where('sale_number', 'like', "%{$search}%");
        }
        
        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
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
        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
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
        (clone $statsQuery)->chunk(200, function($salesChunk) use (&$totalProfit) {
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
            ->map(function($product) {
                // Asegurar que main_image_url se calcule correctamente
                $mainImageUrl = null;
                $firstImage = $product->images()->first();
                if ($firstImage) {
                    $mainImageUrl = $firstImage->path;
                } else {
                    // Si no hay imagen del producto, buscar en variantes
                    foreach ($product->variantOptions ?? [] as $parentOption) {
                        foreach ($parentOption->children ?? [] as $child) {
                            if (!empty($child->image_path)) {
                                $imagePath = $child->image_path;
                                if (!str_starts_with($imagePath, 'http://') && !str_starts_with($imagePath, 'https://')) {
                                    if (!str_starts_with($imagePath, '/storage/')) {
                                        $imagePath = '/storage/' . ltrim($imagePath, '/');
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
                    'variant_options' => $product->variantOptions->map(function($option) {
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
            ->whereHas('products', function($q) {
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
                  ->orWhereHas('variants', function ($subQ) use ($query) {
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

        if (!$product) {
            // Intentar buscar por código de barras de variante
            $variantOption = \App\Models\VariantOption::where('barcode', $barcode)
                ->whereHas('product', function($q) use ($store) {
                    $q->where('store_id', $store->id)->where('is_active', true);
                })
                ->first();

            if ($variantOption) {
                $product = $variantOption->product;
                // Cargar todas las relaciones necesarias
                $product->load(['images', 'variants', 'category', 'variantOptions.children']);
                
                return response()->json([
                    'product' => $product,
                    'matched_variant_option' => $variantOption
                ]);
            }
        }

        if (!$product) {
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
                'message' => 'Comando de apertura de cajón enviado'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al intentar abrir el cajón: ' . $e->getMessage()
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
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.original_price' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:efectivo,tarjeta,transferencia,mixto',
            'notes' => 'nullable|string',
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
                    $nextNumber = (int)$matches[1] + 1;
                } else {
                    // Primera venta de esta tienda
                    $nextNumber = 1;
                }
                
                $saleNumber = 'V-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
                
                // Verificar que el número no exista en ESTA tienda (protección adicional contra condiciones de carrera)
                // El índice único compuesto (store_id, sale_number) garantiza que esto sea único por tienda
                $exists = DB::table('physical_sales')
                    ->where('store_id', $store->id) // CRÍTICO: Verificar solo en esta tienda
                    ->where('sale_number', $saleNumber)
                    ->exists();
                
                if (!$exists) {
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

            // Crear la venta
            $sale = $store->physicalSales()->create([
                'user_id' => $request->user()->id,
                'sale_number' => $saleNumber,
                'subtotal' => $validated['subtotal'],
                'tax' => $validated['tax'] ?? 0,
                'discount' => $validated['discount'] ?? 0,
                'total' => $validated['total'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Crear los items de la venta y descontar inventario
            foreach ($validated['items'] as $itemData) {
                $product = Product::find($itemData['product_id']);
                
                if (!$product) {
                    throw new \Exception("Producto no encontrado: {$itemData['product_id']}");
                }

                // Verificar y descontar inventario
                if ($itemData['variant_id']) {
                    $variant = ProductVariant::find($itemData['variant_id']);
                    if ($variant) {
                        // Verificar stock solo si la variante tiene stock definido Y el producto controla inventario
                        if ($product->track_inventory && $variant->stock !== null && $variant->stock < $itemData['quantity']) {
                            throw new \Exception("Stock insuficiente para la variante del producto {$product->name}. Stock disponible: {$variant->stock}, solicitado: {$itemData['quantity']}");
                        }
                        // Solo descontar si tiene stock definido Y el producto controla inventario
                        if ($product->track_inventory && $variant->stock !== null) {
                            $variant->decrement('stock', $itemData['quantity']);
                        }
                    }
                } else {
                    // Solo verificar stock si track_inventory está activado
                    if ($product->track_inventory) {
                        // Si quantity es null, considerar stock ilimitado
                        if ($product->quantity !== null && $product->quantity < $itemData['quantity']) {
                            throw new \Exception("Stock insuficiente para el producto {$product->name}. Stock disponible: {$product->quantity}, solicitado: {$itemData['quantity']}");
                        }
                        // Solo descontar si quantity no es null
                        if ($product->quantity !== null) {
                            $product->decrement('quantity', $itemData['quantity']);
                        }
                    }
                }

                // Obtener opciones de variante si existe y determinar costo (purchase_price)
                $variantOptions = null;
                $purchasePriceSnapshot = null;

                if ($itemData['variant_id']) {
                    // Reutilizamos la búsqueda de variante si ya se hizo arriba, o buscamos de nuevo si es necesario
                    // Nota: Arriba se busca $variant para stock, pero es local al bloque if.
                    // Buscamos aquí para asegurar disponibilidad
                    $variant = ProductVariant::find($itemData['variant_id']);
                    if ($variant) {
                        $variantOptions = $variant->options;
                        // Prioridad costo variante > costo producto
                        $purchasePriceSnapshot = $variant->purchase_price;
                        
                        // Si la variante no tiene costo, intentar usar el del producto padre como fallback
                        if (is_null($purchasePriceSnapshot) || $purchasePriceSnapshot === '') {
                             $purchasePriceSnapshot = $product->purchase_price;
                        }
                    }
                } else {
                    // Producto simple
                    $purchasePriceSnapshot = $product->purchase_price;
                }

                // Crear el item de la venta
                $sale->items()->create([
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['variant_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['quantity'] * $itemData['unit_price'],
                    'product_name' => $product->name,
                    'variant_options' => $variantOptions,
                    'original_price' => $itemData['original_price'] ?? null,
                    'discount_percent' => $itemData['discount_percent'] ?? 0,
                    'purchase_price' => $purchasePriceSnapshot, // Snapshot del costo
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
        $fileName = 'balanze-pos-' . now()->format('Y-m-d') . '.xlsx';
        
        return Excel::download(new \App\Exports\PhysicalReportExport($filters), $fileName);
    }
}

