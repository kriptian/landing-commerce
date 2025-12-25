<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
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
        $this->middleware('can:ver inventario')->only(['index']);
        $this->middleware('can:crear productos')->only(['store']);
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
        $totalCount = $statsQuery->count();

        return Inertia::render('Admin/PhysicalSales/Index', [
            'sales' => $sales,
            'stats' => [
                'totalSales' => $totalSales,
                'totalCount' => $totalCount,
            ],
            'filters' => $request->only(['start_date', 'end_date', 'search']),
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
                  ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->with(['images', 'variants', 'category'])
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
            ->with(['images', 'variants', 'category'])
            ->first();

        if (!$product) {
            return response()->json(['product' => null], 404);
        }

        return response()->json(['product' => $product]);
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
                    if ($variant && $variant->stock < $itemData['quantity']) {
                        throw new \Exception("Stock insuficiente para la variante del producto {$product->name}");
                    }
                    if ($variant) {
                        $variant->decrement('stock', $itemData['quantity']);
                    }
                } else {
                    if ($product->track_inventory && $product->quantity < $itemData['quantity']) {
                        throw new \Exception("Stock insuficiente para el producto {$product->name}");
                    }
                    if ($product->track_inventory) {
                        $product->decrement('quantity', $itemData['quantity']);
                    }
                }

                // Obtener opciones de variante si existe
                $variantOptions = null;
                if ($itemData['variant_id']) {
                    $variant = ProductVariant::find($itemData['variant_id']);
                    if ($variant) {
                        $variantOptions = $variant->options;
                    }
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
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'sale' => $sale->load('items.product'),
                'message' => 'Venta registrada exitosamente',
            ]);

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
        $filters = $request->only(['start_date', 'end_date']);
        $fileName = 'reporte-ventas-fisicas-' . now()->format('Y-m-d') . '.xlsx';
        
        return Excel::download(new PhysicalSalesExport($filters), $fileName);
    }
}

