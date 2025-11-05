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
        // DEBUG: Verificar TODOS los archivos que llegan en la petición
        \Log::info('🔍 STORE - Todos los archivos en la petición', [
            'allFiles' => $request->allFiles(),
            'has_gallery_files' => $request->hasFile('gallery_files'),
            'request_keys' => array_keys($request->all()),
            'file_keys' => array_filter(array_keys($request->allFiles()), fn($k) => str_contains($k, 'variant_option')),
        ]);
        
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

        $productData = collect($validated)->except(['gallery_files', 'variants', 'variant_attributes', 'variant_options'])->toArray(); 
        // Persistimos definición de atributos/depencias si llega (retrocompatibilidad)
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

        // Guardar variant_options (nuevo sistema jerárquico)
        $variantOptionsData = [];
        if ($request->has('variant_options') && is_array($request->variant_options)) {
            foreach ($request->variant_options as $parentIndex => $parentOptionData) {
                if (empty($parentOptionData['name'])) {
                    continue;
                }
                
                // Crear la variante padre
                $parentOption = $product->allVariantOptions()->create([
                    'name' => $parentOptionData['name'],
                    'parent_id' => null,
                    'price' => null,
                    'image_path' => null,
                    'order' => $parentOptionData['order'] ?? $parentIndex,
                ]);
                
                $childrenData = [];
                // Crear las opciones hijas
                if (isset($parentOptionData['children']) && is_array($parentOptionData['children'])) {
                    foreach ($parentOptionData['children'] as $childIndex => $childData) {
                        if (empty($childData['name'])) {
                            continue;
                        }
                        
                        $imagePath = null;
                        // Buscar la imagen usando la clave generada en el frontend
                        $imageKey = "variant_option_{$parentIndex}_{$childIndex}";
                        if ($request->hasFile($imageKey)) {
                            $imageFile = $request->file($imageKey);
                            if ($imageFile && $imageFile->isValid()) {
                                $path = $imageFile->store('variant-options', 'public');
                                $imagePath = '/storage/' . $path;
                            }
                        }
                        
                        // DEBUG: Log temporal para verificar guardado
                        \Log::info('🔍 STORE - Guardando variant option child', [
                            'name' => $childData['name'],
                            'image_path' => $imagePath,
                            'imageKey' => $imageKey,
                            'hasFile' => $request->hasFile($imageKey),
                        ]);
                        
                        $childOption = $product->allVariantOptions()->create([
                            'name' => $childData['name'],
                            'parent_id' => $parentOption->id,
                            'price' => isset($childData['price']) && $childData['price'] !== '' && $childData['price'] !== null ? $childData['price'] : null,
                            'image_path' => $imagePath,
                            'order' => $childData['order'] ?? $childIndex,
                        ]);
                        
                        // DEBUG: Verificar que se guardó correctamente
                        \Log::info('🔍 STORE - Variant option guardado', [
                            'id' => $childOption->id,
                            'image_path_saved' => $childOption->image_path,
                        ]);
                        
                        $childrenData[] = [
                            'name' => $childData['name'],
                            'price' => isset($childData['price']) && $childData['price'] !== '' && $childData['price'] !== null ? $childData['price'] : null,
                            'image_path' => $imagePath,
                        ];
                    }
                }
                
                $variantOptionsData[] = [
                    'name' => $parentOptionData['name'],
                    'children' => $childrenData,
                ];
            }
            
            // Generar variantes antiguas automáticamente desde variant_options para compatibilidad con carrito
            $this->generateVariantsFromOptions($product, $variantOptionsData, $request->input('variant_attributes', []));
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

        $product->load('images', 'category.parent', 'variants', 'variantOptions.children');
        
        // Asegurar que variant_options se serialicen correctamente
        $productArray = $product->toArray();
        if ($product->variantOptions && $product->variantOptions->count() > 0) {
            $variantOptionsArray = [];
            foreach ($product->variantOptions as $parentOption) {
                $parentData = [
                    'id' => $parentOption->id,
                    'name' => $parentOption->name,
                    'parent_id' => $parentOption->parent_id,
                    'price' => $parentOption->price,
                    'image_path' => $parentOption->image_path,
                    'order' => $parentOption->order,
                    'children' => [],
                ];
                
                if ($parentOption->children && $parentOption->children->count() > 0) {
                    foreach ($parentOption->children as $child) {
                        $imagePath = $child->image_path ?: null;
                        
                        // DEBUG: Log de imagen desde BD
                        \Log::info('🔍 EDIT - Cargando child desde BD', [
                            'id' => $child->id,
                            'name' => $child->name,
                            'image_path_from_db' => $imagePath,
                            'image_path_type' => gettype($imagePath),
                        ]);
                        
                        // Asegurar que image_path esté en el formato correcto
                        if (!empty($imagePath) && !str_starts_with($imagePath, 'http')) {
                            if (!str_starts_with($imagePath, '/storage/')) {
                                $imagePath = '/storage/' . ltrim($imagePath, '/');
                            }
                        }
                        
                        $childData = [
                            'id' => $child->id,
                            'name' => $child->name,
                            'parent_id' => $child->parent_id,
                            'price' => $child->price,
                            'image_path' => $imagePath,
                            'order' => $child->order,
                        ];
                        
                        // DEBUG: Log de imagen después de normalizar
                        \Log::info('🔍 EDIT - Child normalizado para enviar', [
                            'id' => $childData['id'],
                            'name' => $childData['name'],
                            'image_path_normalized' => $childData['image_path'],
                        ]);
                        
                        $parentData['children'][] = $childData;
                    }
                }
                
                $variantOptionsArray[] = $parentData;
            }
            
            $productArray['variant_options'] = $variantOptionsArray;
        } 

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
            'product' => $productArray, // Usar el array serializado con variant_options correctamente formateado
            'categories' => auth()->user()->store->categories()->whereNull('parent_id')->orderBy('name')->get(['id','name']),
            'selectedCategoryPath' => $selectedCategoryPath,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        // DEBUG: Verificar TODOS los archivos que llegan en la petición
        \Log::info('🔍 UPDATE - Todos los archivos en la petición', [
            'allFiles' => $request->allFiles(),
            'has_gallery_files' => $request->hasFile('gallery_files'),
            'has_new_gallery_files' => $request->hasFile('new_gallery_files'),
            'request_keys' => array_keys($request->all()),
            'file_keys' => array_filter(array_keys($request->allFiles()), fn($k) => str_contains($k, 'variant_option')),
        ]);
        
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
        
        $productData = $request->except(['_method', 'new_gallery_files', 'images_to_delete', 'variants', 'variants_to_delete', 'variant_attributes', 'variant_options']);
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
        
        // Actualizar variant_options (nuevo sistema jerárquico)
        $variantOptionsData = [];
        if ($request->has('variant_options') && is_array($request->variant_options)) {
            // Obtener IDs de variant_options que se están enviando
            $sentParentIds = [];
            $sentChildIds = [];
            
            foreach ($request->variant_options as $parentIndex => $parentOptionData) {
                if (empty($parentOptionData['name'])) {
                    continue;
                }
                
                $parentId = $parentOptionData['id'] ?? null;
                $childrenData = [];
                
                // Actualizar o crear el padre
                if ($parentId) {
                    $parentOption = \App\Models\VariantOption::find($parentId);
                    if ($parentOption && $parentOption->product_id === $product->id) {
                        $parentOption->update([
                            'name' => $parentOptionData['name'],
                            'order' => $parentOptionData['order'] ?? $parentIndex,
                        ]);
                        $sentParentIds[] = $parentId;
                    }
                } else {
                    $parentOption = $product->allVariantOptions()->create([
                        'name' => $parentOptionData['name'],
                        'parent_id' => null,
                        'price' => null,
                        'image_path' => null,
                        'order' => $parentOptionData['order'] ?? $parentIndex,
                    ]);
                    $sentParentIds[] = $parentOption->id;
                }
                
                // Procesar hijos
                if (isset($parentOptionData['children']) && is_array($parentOptionData['children'])) {
                    foreach ($parentOptionData['children'] as $childIndex => $childData) {
                        if (empty($childData['name'])) {
                            continue;
                        }
                        
                        $childId = $childData['id'] ?? null;
                        $imagePath = null;
                        
                        // Buscar nueva imagen si se envió
                        $imageKey = "variant_option_{$parentIndex}_{$childIndex}";
                        if ($request->hasFile($imageKey)) {
                            $imageFile = $request->file($imageKey);
                            if ($imageFile && $imageFile->isValid()) {
                                // Eliminar imagen anterior si existe
                                if ($childId) {
                                    $oldChild = \App\Models\VariantOption::find($childId);
                                    if ($oldChild && $oldChild->image_path) {
                                        Storage::disk('public')->delete(str_replace('/storage/', '', $oldChild->image_path));
                                    }
                                }
                                $path = $imageFile->store('variant-options', 'public');
                                $imagePath = '/storage/' . $path;
                            }
                        } else {
                            // Si no hay archivo nuevo, usar la ruta enviada o mantener la existente
                            $receivedImagePath = $childData['image_path'] ?? null;
                            
                            // Verificar que receivedImagePath no sea el string "null" o vacío
                            if ($receivedImagePath && is_string($receivedImagePath)) {
                                $receivedImagePath = trim($receivedImagePath);
                                if ($receivedImagePath === '' || $receivedImagePath === 'null' || $receivedImagePath === 'undefined') {
                                    $receivedImagePath = null;
                                }
                            }
                            
                            if ($receivedImagePath && !empty($receivedImagePath)) {
                                // Se envió una ruta válida (imagen existente que no se cambió)
                                $imagePath = $receivedImagePath;
                            } elseif ($childId) {
                                // No se envió ruta válida ni archivo nuevo, pero existe en BD: mantener la existente
                                $existingChild = \App\Models\VariantOption::find($childId);
                                if ($existingChild && $existingChild->image_path) {
                                    $imagePath = $existingChild->image_path;
                                }
                            }
                        }
                        
                        // DEBUG: Log temporal para verificar actualización
                        \Log::info('🔍 UPDATE - Procesando variant option child', [
                            'childId' => $childId,
                            'name' => $childData['name'],
                            'image_path_received_raw' => $childData['image_path'] ?? null,
                            'image_path_received_type' => gettype($childData['image_path'] ?? null),
                            'imagePath_final' => $imagePath,
                            'imageKey' => $imageKey,
                            'hasFile' => $request->hasFile($imageKey),
                        ]);
                        
                        if ($childId) {
                            $childOption = \App\Models\VariantOption::find($childId);
                            if ($childOption && $childOption->product_id === $product->id) {
                                // DEBUG: Verificar imagen existente antes de actualizar
                                \Log::info('🔍 UPDATE - Imagen existente en BD', [
                                    'existing_image_path' => $childOption->image_path,
                                ]);
                                
                                $childOption->update([
                                    'name' => $childData['name'],
                                    'parent_id' => $parentOption->id,
                                    'price' => isset($childData['price']) && $childData['price'] !== '' && $childData['price'] !== null ? $childData['price'] : null,
                                    'image_path' => $imagePath,
                                    'order' => $childData['order'] ?? $childIndex,
                                ]);
                                
                                // DEBUG: Verificar que se actualizó correctamente
                                $childOption->refresh();
                                \Log::info('🔍 UPDATE - Variant option actualizado', [
                                    'id' => $childOption->id,
                                    'image_path_after_update' => $childOption->image_path,
                                ]);
                                
                                $sentChildIds[] = $childId;
                            }
                        } else {
                            $newChild = $product->allVariantOptions()->create([
                                'name' => $childData['name'],
                                'parent_id' => $parentOption->id,
                                'price' => isset($childData['price']) && $childData['price'] !== '' && $childData['price'] !== null ? $childData['price'] : null,
                                'image_path' => $imagePath,
                                'order' => $childData['order'] ?? $childIndex,
                            ]);
                            $sentChildIds[] = $newChild->id;
                        }
                        
                        $childrenData[] = [
                            'name' => $childData['name'],
                            'price' => isset($childData['price']) && $childData['price'] !== '' && $childData['price'] !== null ? $childData['price'] : null,
                            'image_path' => $imagePath,
                        ];
                    }
                }
                
                $variantOptionsData[] = [
                    'name' => $parentOptionData['name'],
                    'children' => $childrenData,
                ];
            }
            
            // Eliminar variant_options que no están en la lista enviada
            $allExistingIds = $product->allVariantOptions()->pluck('id')->toArray();
            $idsToDelete = array_diff($allExistingIds, array_merge($sentParentIds, $sentChildIds));
            
            if (!empty($idsToDelete)) {
                $optionsToDelete = \App\Models\VariantOption::whereIn('id', $idsToDelete)
                    ->where('product_id', $product->id)
                    ->get();
                
                foreach ($optionsToDelete as $option) {
                    // Eliminar imagen si existe
                    if ($option->image_path) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $option->image_path));
                    }
                    $option->delete();
                }
            }
            
            // Eliminar variantes antiguas y regenerarlas desde variant_options
            $product->variants()->delete();
            $this->generateVariantsFromOptions($product, $variantOptionsData, $request->input('variant_attributes', []));
        } else {
            // Si no se envían variant_options, eliminar todas las existentes
            $optionsToDelete = $product->allVariantOptions()->get();
            foreach ($optionsToDelete as $option) {
                if ($option->image_path) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $option->image_path));
                }
                $option->delete();
            }
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

    /**
     * Generar variantes antiguas (ProductVariant) desde variant_options para compatibilidad con carrito
     * Respetando dependencias de variant_attributes
     */
    private function generateVariantsFromOptions(Product $product, array $variantOptionsData, array $variantAttributes = [])
    {
        if (empty($variantOptionsData)) {
            return;
        }

        // Construir mapa de dependencias desde variant_attributes
        $dependencyMap = [];
        if (!empty($variantAttributes) && is_array($variantAttributes)) {
            foreach ($variantAttributes as $attr) {
                if (!empty($attr['name']) && !empty($attr['dependsOn'])) {
                    $dependencyMap[$attr['name']] = [
                        'dependsOn' => $attr['dependsOn'],
                        'rulesSelected' => $attr['rulesSelected'] ?? [],
                        'rules' => $attr['rules'] ?? [],
                    ];
                }
            }
        }

        // Construir arrays de valores por atributo
        $attributeValues = [];
        foreach ($variantOptionsData as $parent) {
            $attributeValues[$parent['name']] = array_column($parent['children'] ?? [], 'name');
        }

        // Ordenar atributos por dependencias (padres primero)
        $orderedAttributes = [];
        $visited = [];
        
        function visitAttribute($attrName, &$orderedAttributes, &$visited, $dependencyMap, $attributeValues) {
            if (in_array($attrName, $visited)) return;
            if (isset($dependencyMap[$attrName]['dependsOn'])) {
                $parent = $dependencyMap[$attrName]['dependsOn'];
                if (isset($attributeValues[$parent])) {
                    visitAttribute($parent, $orderedAttributes, $visited, $dependencyMap, $attributeValues);
                }
            }
            $visited[] = $attrName;
            if (isset($attributeValues[$attrName])) {
                $orderedAttributes[] = $attrName;
            }
        }

        foreach (array_keys($attributeValues) as $attrName) {
            visitAttribute($attrName, $orderedAttributes, $visited, $dependencyMap, $attributeValues);
        }

        // Generar todas las combinaciones posibles respetando dependencias
        $combinations = [];
        
        function generateCombinations($index, $current, $orderedAttributes, $attributeValues, $dependencyMap, &$combinations) {
            if ($index >= count($orderedAttributes)) {
                $combinations[] = $current;
                return;
            }

            $attrName = $orderedAttributes[$index];
            $values = $attributeValues[$attrName] ?? [];

            // Verificar dependencias
            if (isset($dependencyMap[$attrName])) {
                $dependsOn = $dependencyMap[$attrName]['dependsOn'];
                $parentValue = $current[$dependsOn] ?? null;
                
                if ($parentValue !== null) {
                    $rules = $dependencyMap[$attrName]['rulesSelected'][$parentValue] ?? $dependencyMap[$attrName]['rules'][$parentValue] ?? [];
                    $ruleValues = is_array($rules) ? $rules : (is_string($rules) ? array_map('trim', explode(',', $rules)) : []);
                    
                    if (!empty($ruleValues)) {
                        $values = array_intersect($values, $ruleValues);
                    }
                }
            }

            foreach ($values as $value) {
                $newCurrent = $current;
                $newCurrent[$attrName] = $value;
                generateCombinations($index + 1, $newCurrent, $orderedAttributes, $attributeValues, $dependencyMap, $combinations);
            }
        }

        generateCombinations(0, [], $orderedAttributes, $attributeValues, $dependencyMap, $combinations);

        // Crear variantes desde las combinaciones
        // IMPORTANTE: Solo UNA variante principal puede tener precios
        // Buscar la variante principal que tiene precios
        $pricedVariantParent = null;
        foreach ($variantOptionsData as $parent) {
            if (!empty($parent['children'])) {
                foreach ($parent['children'] as $child) {
                    if (isset($child['price']) && $child['price'] !== null && $child['price'] !== '') {
                        $pricedVariantParent = $parent['name'];
                        break 2; // Salir de ambos loops cuando encontramos la primera variante con precios
                    }
                }
            }
        }
        
        foreach ($combinations as $combination) {
            // Si hay una variante principal con precios, buscar el precio de esa variante en la combinación
            // Si no hay variante con precios o la opción no tiene precio, usar null (se usará el precio principal)
            $variantPrice = null;
            
            if ($pricedVariantParent) {
                $value = $combination[$pricedVariantParent] ?? null;
                if ($value) {
                    foreach ($variantOptionsData as $parent) {
                        if ($parent['name'] === $pricedVariantParent) {
                            foreach ($parent['children'] ?? [] as $child) {
                                if ($child['name'] === $value && isset($child['price']) && $child['price'] !== null && $child['price'] !== '') {
                                    $variantPrice = (float) $child['price'];
                                    break 2; // Salir cuando encontramos el precio
                                }
                            }
                        }
                    }
                }
            }

            $product->variants()->create([
                'options' => $combination,
                'price' => $variantPrice, // null si no hay precio en la variante con precios (se usará precio principal)
                'stock' => 0, // Se manejará por el inventario total
                'alert' => null,
            ]);
        }
    }
}