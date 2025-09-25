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

    public function store(Request $request)
    {
        // ===== 1. AÑADIMOS VALIDACIÓN PARA 'minimum_stock' =====
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0', 
            'minimum_stock' => 'required|integer|min:0', // <-- Nuevo
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
            'variants.*.minimum_stock' => 'required_with:variants|integer|min:0', // <-- Nuevo para variantes
            'variants.*.alert' => 'nullable|integer|min:0',
            'variants.*.alert' => 'nullable|integer|min:0',
        ]);
        // =========================================================

        if (!empty($validated['specifications'])) {
            $specArray = array_map('trim', explode(',', $validated['specifications']));
            $validated['specifications'] = json_encode($specArray);
        }

        $productData = collect($validated)->except(['gallery_files', 'variants'])->toArray(); 
        $hasVariants = $request->has('variants') && count($request->variants) > 0;

        if ($hasVariants) {
            $productData['quantity'] = collect($request->variants)->sum('stock');
            // También sumamos el inventario mínimo
            $productData['minimum_stock'] = collect($request->variants)->sum('minimum_stock');
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
                    // ===== 2. GUARDAMOS EL 'minimum_stock' DE LA VARIANTE =====
                    $product->variants()->create([
                        'options' => $optionsArray, 
                        'price' => $variantData['price'] ?? null, 
                        'stock' => $variantData['stock'],
                        'minimum_stock' => $variantData['minimum_stock'], // <-- Nuevo
                        'alert' => $variantData['alert'] ?? null,
                    ]);
                    // ========================================================
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

    public function update(Request $request, Product $product)
    {
        // ===== 3. AÑADIMOS VALIDACIÓN PARA 'minimum_stock' EN UPDATE =====
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0', 
            'minimum_stock' => 'required|integer|min:0', // <-- Nuevo
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
            'variants.*.minimum_stock' => 'required_with:variants|integer|min:0', // <-- Nuevo para variantes
            'variants_to_delete' => 'nullable|array', 
            'variants_to_delete.*' => 'integer|exists:product_variants,id',
        ]);
        // =================================================================

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
        
        $productData = $request->except(['_method', 'new_gallery_files', 'images_to_delete', 'variants', 'variants_to_delete']);

        if (!empty($productData['specifications']) && is_string($productData['specifications'])) {
            $specArray = array_map('trim', explode(',', $productData['specifications']));
            $productData['specifications'] = json_encode($specArray);
        }

        $hasVariants = $request->has('variants') && count($request->variants) > 0;
        if ($hasVariants) {
            $productData['quantity'] = collect($request->variants)->sum('stock');
            $productData['minimum_stock'] = collect($request->variants)->sum('minimum_stock');
        }
        
        $product->update($productData);

        if ($request->has('variants_to_delete')) {
            ProductVariant::whereIn('id', $request->variants_to_delete)
                ->where('product_id', $product->id)
                ->delete();
        }

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
                    // ===== 4. GUARDAMOS EL 'minimum_stock' AL ACTUALIZAR =====
                    $product->variants()->updateOrCreate(
                        ['id' => $variantData['id'] ?? null],
                        [ 
                            'options' => $optionsArray,
                            'price'   => $variantData['price'] ?? null,
                            'stock'   => $variantData['stock'],
                            'minimum_stock' => $variantData['minimum_stock'], // <-- Nuevo
                            'alert' => $variantData['alert'] ?? null,
                        ]
                    );
                    // ========================================================
                }
            }
        }
        
        return redirect()->route('admin.products.index');
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