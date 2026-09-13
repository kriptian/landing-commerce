<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InventoryExport;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ver inventario')->only(['index', 'search', 'export']);
        $this->middleware('can:editar inventario')->only('quickUpdate');
    }

    /**
     * Muestra la página de gestión de inventario con filtros.
     */
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString(); // '', 'out_of_stock', 'low_stock'

        $productsQuery = $request->user()->store->products()
            ->with(['store', 'images', 'variants', 'variantOptions.children'])
            ->latest();

        if (! empty($search)) {
            $this->applySearch($productsQuery, $search);
        }

        if ($status === 'out_of_stock') {
            $productsQuery->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereDoesntHave('variants')
                        ->where('quantity', '<=', 0);
                })
                    ->orWhereHas('variants', function ($q) {
                        $q->where('stock', '<=', 0);
                    });
            });
        } elseif ($status === 'low_stock') {
            $productsQuery->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereDoesntHave('variants')
                        ->where('quantity', '>', 0)
                        ->whereNotNull('alert')
                        ->where('alert', '>', 0)
                        ->whereColumn('quantity', '<=', 'alert');
                })
                    ->orWhereHas('variants', function ($q) {
                        $q->where('stock', '>', 0)
                            ->whereNotNull('alert')
                            ->where('alert', '>', 0)
                            ->whereColumn('stock', '<=', 'alert');
                    });
            });
        }

        $products = $productsQuery->paginate(20)->withQueryString();
        $products->getCollection()->each(fn (Product $product) => $this->addThumbnail($product));
        $inventoryWarnings = $request->user()->store->products()
            ->where('track_inventory', true)
            ->where('quantity', '>', 0)
            ->whereHas('variants')
            ->whereDoesntHave('variants', fn ($query) => $query->where('stock', '>', 0))
            ->with(['variants:id,product_id,options'])
            ->get(['id', 'name', 'quantity'])
            ->filter(fn ($product) => $product->variants->contains(
                fn ($variant) => ! empty($variant->options)
            ))
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'global_quantity' => $product->quantity,
                'variants_count' => $product->variants->count(),
            ])
            ->values();

        return Inertia::render('Admin/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'status']),
            'inventoryWarnings' => $inventoryWarnings,
        ]);
    }

    /**
     * Exporta el inventario de la tienda a Excel.
     */
    public function export(Request $request)
    {
        $fileName = 'inventario-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new InventoryExport($request->user()->store->id), $fileName);
    }

    /**
     * Actualiza stock y precios de forma rápida desde el modal.
     */
    public function quickUpdate(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer', // ID del producto o variante
            'type' => 'required|string|in:product,variant', // Tipo de entidad
            'quantity_add' => 'nullable|integer|min:1', // Stock a sumar
            'purchase_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        if (! isset($data['quantity_add']) && ! isset($data['purchase_price']) && ! isset($data['price'])) {
            throw ValidationException::withMessages([
                'quantity_add' => 'Ingresa unidades o modifica al menos un precio.',
            ]);
        }

        $model = null;

        if ($data['type'] === 'variant') {
            $model = ProductVariant::where('id', $data['id'])
                ->whereHas('product', function ($q) use ($request) {
                    $q->where('store_id', $request->user()->store_id);
                })
                ->firstOrFail();
        } else {
            $model = $request->user()->store->products()
                ->where('id', $data['id'])
                ->firstOrFail();
        }

        // 1. Agregar Stock (Incrementar)
        if (! empty($data['quantity_add']) && $data['quantity_add'] > 0) {
            // Usar columna 'stock' para variante, 'quantity' para producto
            $colInfo = $data['type'] === 'variant' ? 'stock' : 'quantity';

            // Si es producto simple, incrementar 'quantity'
            // Si es variante, incrementar 'stock'
            $model->increment($colInfo, $data['quantity_add']);

            /**
             * --- SINCRONIZACIÓN CATÁLOGO PÚBLICO (SPLIT BRAIN FIX v2) ---
             * Problema: Si ya existe un desfase (Admin 16 vs Catalogo 11), hacer increment solo mantiene el error.
             * Solución: Usar el stock TOTAL del ProductVariant como "Source of Truth" y sobrescribir el del catálogo.
             */
            if ($data['type'] === 'variant' && ! empty($model->options)) {
                // Obtener el nuevo stock total real de la variante
                $newStock = $model->refresh()->stock;
                $optionsMap = is_string($model->options) ? json_decode($model->options, true) : $model->options;

                if (is_array($optionsMap)) {
                    if (is_array($optionsMap)) {
                        // ESTRATEGIA ROBUSTA (Legacy Data):
                        // 1. No confiar en que los hijos tengan 'product_id' seteado.
                        // 2. Buscar primero las opciones PADRE del producto.
                        // 3. Buscar los HIJOS de esos padres.

                        $parentOptionIds = \App\Models\VariantOption::where('product_id', $model->product_id)
                            ->whereNull('parent_id')
                            ->pluck('id');

                        $allChildOptions = \App\Models\VariantOption::whereIn('parent_id', $parentOptionIds)
                            ->get();

                        foreach ($optionsMap as $optName => $optValue) {
                            $targetValue = trim(strtolower(strval($optValue)));

                            foreach ($allChildOptions as $childInfo) {
                                $dbName = trim(strtolower($childInfo->name));

                                // Match exacto normalizado
                                if ($dbName === $targetValue) {
                                    // Forzar actualización directa a la DB para evitar cualquier cache de modelo
                                    DB::table('variant_options')
                                        ->where('id', $childInfo->id)
                                        ->update(['stock' => $newStock]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Actualizar Precios (Si se enviaron)
        if (isset($data['purchase_price']) && $data['purchase_price'] !== '') {
            $model->purchase_price = $data['purchase_price'];
        }
        if (isset($data['price']) && $data['price'] !== '') {
            $model->price = $data['price'];
        }

        // Guardar cambios de precio si hubo
        if ($model->isDirty(['purchase_price', 'price'])) {
            $model->save();
        }

        return redirect()->back()->with('success', 'Inventario actualizado correctamente.');
    }

    /**
     * Búsqueda AJAX para el modal de Entrada Rápida.
     */
    public function search(Request $request)
    {
        $term = trim((string) $request->get('q'));
        if (empty($term)) {
            return response()->json([]);
        }

        $products = $request->user()->store->products()
            ->with(['store', 'images', 'variants', 'variantOptions.children'])
            ->where(fn ($query) => $this->applySearch($query, $term))
            ->take(10)
            ->get();

        $products->each(fn (Product $product) => $this->addThumbnail($product));

        return response()->json($products);
    }

    private function applySearch($query, string $term): void
    {
        $query->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhereHas('variants', fn ($variant) => $variant
                    ->where('sku', 'like', "%{$term}%")
                    ->orWhere('options', 'like', "%{$term}%"))
                ->orWhereHas('variantOptions', fn ($option) => $option
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhereHas('children', fn ($child) => $child
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%")));
        });
    }

    private function addThumbnail(Product $product): void
    {
        $thumbnail = $product->main_image_url;

        if ($thumbnail && ! str_starts_with($thumbnail, 'http://') && ! str_starts_with($thumbnail, 'https://') && ! str_starts_with($thumbnail, '/')) {
            $thumbnail = '/storage/'.ltrim($thumbnail, '/');
        }

        $product->setAttribute('thumbnail', $thumbnail ?: asset('img/product-placeholder.svg'));
    }
}
