<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ver inventario')->only(['index']);
        $this->middleware('can:crear productos')->only(['create','store']);
        $this->middleware('can:editar productos')->only(['edit','update']);
        $this->middleware('can:eliminar productos')->only(['destroy']);
    }
    public function index(Request $request)
    {
        $store = $request->user()->store;
        $query = $store->products()->with('category')->latest();

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $products = $query->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $store->categories()->orderBy('name')->get(['id','name']),
            'store' => $store->only(['promo_active','promo_discount_percent']),
            'filters' => $request->only(['category','search','status']),
        ]);
    }

    public function updateStorePromo(Request $request)
    {
        $data = $request->validate([
            'promo_active' => 'required|boolean',
            'promo_discount_percent' => 'nullable|integer|min:1|max:90',
        ]);

        $store = $request->user()->store;
        $store->update($data);

        return back();
    }

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => auth()->user()->store->categories()->whereNull('parent_id')->orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(Request $request)
    {
        // ===== 1. VALIDACIÓN: inventario + nuevos campos de precios =====
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            // precios retirados de UI, pero mantenemos validación por compatibilidad antigua
            'wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'retail_price' => 'sometimes|nullable|numeric|min:0',
            'track_inventory' => 'sometimes|boolean',
            'quantity' => 'nullable|integer|min:0', 
            'alert' => 'nullable|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string|max:500',
            'long_description' => 'nullable|string',
            'specifications' => 'nullable|string',
            'gallery_files' => 'nullable|array',
            'gallery_files.*' => 'image|max:2048',
            'variants' => 'nullable|array',
            'variants.*.options_text' => 'required_with:variants|string',
            'variants.*.price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.purchase_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.retail_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.stock' => 'required_if:track_inventory,1|integer|min:0',
            'variants.*.alert' => 'nullable|integer|min:0',
            'variants.*.alert' => 'nullable|integer|min:0',
        ]);
        // =========================================================

        // Validar que la categoría seleccionada no tenga hijos (debe ser una hoja)
        $category = \App\Models\Category::find($validated['category_id']);
        if ($category) {
            $hasChildren = \App\Models\Category::where('parent_id', $category->id)
                ->where('store_id', auth()->user()->store_id)
                ->exists();
            
            if ($hasChildren) {
                return redirect()->back()->withErrors([
                    'category_id' => 'Debés seleccionar una subcategoría. La categoría principal tiene subcategorías disponibles y es obligatorio elegir una de ellas.'
                ])->withInput();
            }
        }

        if (!empty($validated['specifications'])) {
            $specArray = array_map('trim', explode(',', $validated['specifications']));
            $validated['specifications'] = json_encode($specArray);
        }

        $productData = collect($validated)->except(['gallery_files', 'variants', 'variant_attributes'])->toArray(); 
        // Persistimos definición de atributos/depencias si llega
        if ($request->has('variant_attributes')) {
            $productData['variant_attributes'] = $request->input('variant_attributes');
        }
        // Si no se envía la bandera, considerar true por defecto
        if (!array_key_exists('track_inventory', $productData)) {
            $productData['track_inventory'] = true;
        }
        // Si no se controla inventario, normalizamos cantidades nulas
        if (!$productData['track_inventory']) {
            $productData['quantity'] = 0;
            $productData['alert'] = null;
        }
        $hasVariants = $request->has('variants') && count($request->variants) > 0;

        if ($hasVariants) {
            $sum = collect($request->variants)->sum(fn($v) => (int) ($v['stock'] ?? 0));
            // Si no se especificó stock por variante, conservar el inventario total ingresado
            $productData['quantity'] = $sum > 0 ? $sum : (int) ($request->input('quantity', 0));
            // Mantener alerta ingresada si la envían, de lo contrario null
            if (!isset($productData['alert'])) {
                $productData['alert'] = null;
            }
        }
        
        $product = $request->user()->store->products()->create($productData);

        if ($hasVariants) {
            foreach ($request->variants as $variantData) {
                
                $optionsArray = [];
                $pairs = explode(',', $variantData['options_text']);
                foreach ($pairs as $pair) {
                    $parts = explode(':', $pair, 2); 
                    if (count($parts) == 2) {
                        $optionsArray[trim($parts[0])] = trim($parts[1]);
                    } elseif (count($parts) == 1 && !empty(trim($parts[0]))) {
                        $optionsArray['Opción'] = trim($parts[0]);
                    }
                }

                if (count($optionsArray) > 0) {
                    $product->variants()->create([
                        'options' => $optionsArray, 
                        'price' => $variantData['price'] ?? null, 
                        'purchase_price' => (isset($variantData['purchase_price']) && $variantData['purchase_price'] !== '') ? $variantData['purchase_price'] : null,
                        'wholesale_price' => (isset($variantData['wholesale_price']) && $variantData['wholesale_price'] !== '') ? $variantData['wholesale_price'] : null,
                        'retail_price' => (isset($variantData['retail_price']) && $variantData['retail_price'] !== '') ? $variantData['retail_price'] : null,
                        'stock' => $variantData['stock'],
                        'alert' => $variantData['alert'] ?? null,
                    ]);
                }
            }
        }

        if ($request->hasFile('gallery_files')) {
            foreach ($request->file('gallery_files') as $file) {
                $path = $file->store('products', 'public');
                $product->images()->create(['path' => '/storage/' . $path]);
            }
        }
        // Redirigimos al listado para que se vea el popup allí
        return redirect()
            ->route('admin.products.index')
            ->with('success', '¡Producto creado con éxito!');
    }

    public function edit(Product $product)
    {
        if ($product->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        $product->load('images', 'category.parent', 'variants'); 

        // Construimos la ruta completa de categorías desde la raíz hasta la seleccionada
        $selectedCategoryPath = [];
        $current = $product->category; 
        while ($current) {
            $selectedCategoryPath[] = [
                'id' => $current->id,
                'name' => $current->name,
                'parent_id' => $current->parent_id,
            ];
            $current = $current->parent;
        }
        $selectedCategoryPath = array_reverse($selectedCategoryPath);

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => auth()->user()->store->categories()->whereNull('parent_id')->orderBy('name')->get(['id','name']),
            'selectedCategoryPath' => $selectedCategoryPath,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        // Atajo: actualización rápida desde el listado (promos o activación)
        if ($request->hasAny(['promo_active', 'promo_discount_percent', 'is_active']) && !$request->has('name')) {
            $data = $request->validate([
                'promo_active' => 'sometimes|boolean',
                'promo_discount_percent' => 'sometimes|nullable|integer|min:1|max:90',
                'is_active' => 'sometimes|boolean',
            ]);
            $product->update($data);
            return back();
        }

        // ===== 3. VALIDACIÓN EN UPDATE: inventario + nuevos precios =====
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'purchase_price' => 'sometimes|nullable|numeric|min:0',
            'wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'retail_price' => 'sometimes|nullable|numeric|min:0',
            'track_inventory' => 'sometimes|boolean',
            'quantity' => 'nullable|integer|min:0', 
            'alert' => 'nullable|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string|max:500',
            'long_description' => 'nullable|string',
            'specifications' => 'nullable|string',
            'new_gallery_files' => 'nullable|array',
            'new_gallery_files.*' => 'image|max:2048',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'integer|exists:product_images,id',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer|exists:product_variants,id',
            'variants.*.options_text' => 'required_with:variants|string',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.purchase_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.retail_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.stock' => 'required_if:track_inventory,1|integer|min:0',
            'variants_to_delete' => 'nullable|array', 
            'variants_to_delete.*' => 'integer|exists:product_variants,id',
            // Campos de promoción (opcionales)
            'promo_active' => 'sometimes|boolean',
            'promo_discount_percent' => 'sometimes|nullable|integer|min:1|max:90',
        ]);
        // =================================================================

        // Validar que la categoría seleccionada no tenga hijos (debe ser una hoja)
        $category = \App\Models\Category::find($validated['category_id']);
        if ($category) {
            $hasChildren = \App\Models\Category::where('parent_id', $category->id)
                ->where('store_id', auth()->user()->store_id)
                ->exists();
            
            if ($hasChildren) {
                return redirect()->back()->withErrors([
                    'category_id' => 'Debés seleccionar una subcategoría. La categoría principal tiene subcategorías disponibles y es obligatorio elegir una de ellas.'
                ])->withInput();
            }
        }

        if (!empty($validated['images_to_delete'])) {
            $imagesToDelete = ProductImage::whereIn('id', $validated['images_to_delete'])->get();
            foreach ($imagesToDelete as $image) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $image->path));
                $image->delete();
            }
        }
        if ($request->hasFile('new_gallery_files')) {
            foreach ($request->file('new_gallery_files') as $file) {
                $path = $file->store('products', 'public');
                $product->images()->create(['path' => '/storage/' . $path]);
            }
        }
        
        $productData = $request->except(['_method', 'new_gallery_files', 'images_to_delete', 'variants', 'variants_to_delete', 'variant_attributes']);
        // Actualizar variant_attributes también cuando venga vacío explícitamente (para limpiar)
        if ($request->has('variant_attributes')) {
            $va = $request->input('variant_attributes');
            if (is_array($va)) {
                $productData['variant_attributes'] = $va; // puede ser [] para eliminar definición
            }
        }
        if (!array_key_exists('track_inventory', $productData)) {
            $productData['track_inventory'] = $product->track_inventory ?? true;
        }

        if (!empty($productData['specifications']) && is_string($productData['specifications'])) {
            $specArray = array_map('trim', explode(',', $productData['specifications']));
            $productData['specifications'] = json_encode($specArray);
        }

        $hasVariants = $request->has('variants') && count($request->variants) > 0;
        if ($hasVariants) {
            $sum = collect($request->variants)->sum(fn($v) => (int) ($v['stock'] ?? 0));
            $productData['quantity'] = $sum > 0 ? $sum : (int) ($request->input('quantity', $product->quantity));
            if (!isset($productData['alert'])) {
                $productData['alert'] = null;
            }
        }
        // Si NO se controla inventario, normalizamos cantidades
        if (!$productData['track_inventory']) {
            $productData['quantity'] = 0;
            $productData['alert'] = null;
        }
        
        $product->update($productData);

        if ($request->has('variants_to_delete')) {
            ProductVariant::whereIn('id', $request->variants_to_delete)
                ->where('product_id', $product->id)
                ->delete();
        }

        if ($hasVariants) {
            // Reemplazo total: eliminamos las variantes actuales y creamos las nuevas combinaciones enviadas
            $product->variants()->delete();
            foreach ($request->variants as $variantData) {
                $optionsArray = [];
                $pairs = explode(',', $variantData['options_text']);
                foreach ($pairs as $pair) {
                    $parts = explode(':', $pair, 2);
                    if (count($parts) == 2) {
                        $optionsArray[trim($parts[0])] = trim($parts[1]);
                    } elseif (count($parts) == 1 && !empty(trim($parts[0]))) {
                        $optionsArray['Opción'] = trim($parts[0]);
                    }
                }
                if (count($optionsArray) > 0) {
                    $product->variants()->create([
                        'options' => $optionsArray,
                        'price'   => $variantData['price'] ?? null,
                        'purchase_price' => (isset($variantData['purchase_price']) && $variantData['purchase_price'] !== '') ? $variantData['purchase_price'] : null,
                        'stock'   => $variantData['stock'] ?? 0,
                        'alert' => $variantData['alert'] ?? null,
                    ]);
                }
            }
        } else {
            // Si no hay variantes enviadas, eliminamos todas (caso limpiar atributos)
            $product->variants()->delete();
        }
        
        // Redirigimos al listado para mostrar el modal de éxito
        return redirect()
            ->route('admin.products.index')
            ->with('success', '¡Producto actualizado con éxito!');
    }

    public function destroy(Product $product)
    {
        foreach($product->images as $image) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $image->path));
        }
        
        $product->delete();
        
        return redirect()->route('admin.products.index');
    }
}