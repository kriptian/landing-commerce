<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InventoryExport;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ver inventario')->only(['index', 'export']);
    }

    /**
     * Muestra la página de gestión de inventario con filtros.
     */
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString(); // '', 'out_of_stock', 'low_stock'

        $productsQuery = $request->user()->store->products()
            ->with(['variants', 'variantOptions']) // Cargar variantOptions para distinguir simples vs configurables
            ->latest();

        if (!empty($search)) {
            $productsQuery->where('name', 'like', "%{$search}%");
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

        return Inertia::render('Admin/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Exporta el inventario de la tienda a Excel.
     */
    public function export(Request $request)
    {
        $fileName = 'inventario-' . now()->format('Y-m-d') . '.xlsx';
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

        $model = null;

        if ($data['type'] === 'variant') {
            $model = \App\Models\ProductVariant::where('id', $data['id'])
                ->whereHas('product', function($q) use ($request) {
                    $q->where('store_id', $request->user()->store_id);
                })
                ->firstOrFail();
        } else {
            $model = $request->user()->store->products()
                ->where('id', $data['id'])
                ->firstOrFail();
        }

        // 1. Agregar Stock (Incrementar)
        if (!empty($data['quantity_add']) && $data['quantity_add'] > 0) {
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
            if ($data['type'] === 'variant' && !empty($model->options)) {
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
                                \DB::table('variant_options')
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
        $term = $request->get('q');
        if (empty($term)) return response()->json([]);

        $products = $request->user()->store->products()
            ->with(['variants']) // Cargar variantes
            ->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('barcode', 'like', "%{$term}%");
            })
            ->take(10)
            ->get();
            
        return response()->json($products);
    }
}