<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant; // <-- Asegurate de importar el modelo de variantes
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = $request->user()->store->products()->with('category')->latest()->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => auth()->user()->store->categories()->whereNull('parent_id')->with('children')->get(),
        ]);
    }

    /**
     * Esta es la función STORE (v3.0 - La "Avispada")
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0', 
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string|max:500',
            'long_description' => 'nullable|string',
            'specifications' => 'nullable|string',
            'gallery_files' => 'nullable|array',
            'gallery_files.*' => 'image|max:2048',
            'variants' => 'nullable|array',
            'variants.*.options_text' => 'required_with:variants|string',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'required_with:variants|integer|min:0',
        ]);

        if (!empty($validated['specifications'])) {
            $specArray = array_map('trim', explode(',', $validated['specifications']));
            $validated['specifications'] = json_encode($specArray);
        }

        $productData = collect($validated)->except(['gallery_files', 'variants'])->toArray(); 
        $hasVariants = $request->has('variants') && count($request->variants) > 0;

        if ($hasVariants) {
            $productData['quantity'] = collect($request->variants)->sum('stock');
        }
        
        $product = $request->user()->store->products()->create($productData);

        if ($hasVariants) {
            foreach ($request->variants as $variantData) {
                
                // ===== ESTE ES EL PARSER ARREGLADO =====
                $optionsArray = [];
                $pairs = explode(',', $variantData['options_text']);
                
                foreach ($pairs as $pair) {
                    $parts = explode(':', $pair, 2); 
                    if (count($parts) == 2) {
                        // Si viene "Clave:Valor", lo guarda
                        $optionsArray[trim($parts[0])] = trim($parts[1]);
                    } elseif (count($parts) == 1 && !empty(trim($parts[0]))) {
                        // Si viene solo "Valor", le pone una clave por defecto
                        $optionsArray['Opción'] = trim($parts[0]);
                    }
                }
                // ======================================

                if (count($optionsArray) > 0) {
                    $product->variants()->create([
                        'options' => $optionsArray, 
                        'price' => $variantData['price'] ?? null, 
                        'stock' => $variantData['stock'],
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
        return redirect()->route('admin.products.index');
    }

    /**
     * Esta es la función EDIT (v2.0) - Carga las variantes correctas
     */
    public function edit(Product $product)
    {
        if ($product->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        $product->load('images', 'category.parent', 'variants'); 

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => auth()->user()->store->categories()->whereNull('parent_id')->with('children')->get(),
        ]);
    }

    /**
     * Esta es la función UPDATE (¡Terminada!)
     */
    public function update(Request $request, Product $product)
    {
        // 1. VALIDACIÓN COMPLETA
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0', 
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
            'variants.*.stock' => 'required_with:variants|integer|min:0',
            'variants_to_delete' => 'nullable|array', 
            'variants_to_delete.*' => 'integer|exists:product_variants,id',
        ]);

        // 2. LÓGICA DE IMÁGENES (Sigue igual)
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
        
        // 3. ACTUALIZAR PRODUCTO PRINCIPAL
        $productData = $request->except(['_method', 'new_gallery_files', 'images_to_delete', 'variants', 'variants_to_delete']);

        if (!empty($productData['specifications']) && is_string($productData['specifications'])) {
            $specArray = array_map('trim', explode(',', $productData['specifications']));
            $productData['specifications'] = json_encode($specArray);
        }

        $hasVariants = $request->has('variants') && count($request->variants) > 0;
        if ($hasVariants) {
            $productData['quantity'] = collect($request->variants)->sum('stock');
        }
        // Si no, usa la quantity que mandó el form (que se habilita si no hay variantes)
        
        $product->update($productData);

        // 4. SINCRONIZAR VARIANTES (Borrar, Actualizar y Crear)

        // A. Borrar las variantes que marcaste para "Quitar"
        if ($request->has('variants_to_delete')) {
            ProductVariant::whereIn('id', $request->variants_to_delete)
                ->where('product_id', $product->id) // Seguridad
                ->delete();
        }

        // B. Actualizar las que existen y Crear las nuevas
        if ($hasVariants) {
            foreach ($request->variants as $variantData) {
                
                // Mismo parser "avispado" de la función 'store'
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
                    $product->variants()->updateOrCreate(
                        [
                            'id' => $variantData['id'] ?? null // Busca por ID
                        ],
                        [ 
                            'options' => $optionsArray,
                            'price'   => $variantData['price'] ?? null,
                            'stock'   => $variantData['stock'],
                        ]
                    );
                }
            }
        }
        
        return redirect()->route('admin.products.index');
    }

    /**
     * Esta es la función DESTROY (sigue igual).
     */
    public function destroy(Product $product)
    {
        foreach($product->images as $image) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $image->path));
        }
        
        $product->delete();
        
        return redirect()->route('admin.products.index');
    }
}