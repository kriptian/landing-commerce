<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage; // <-- Se necesita este import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // <-- Y este también
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Muestra la lista de todos los productos.
     */
    public function index()
    {
        return Inertia::render('Products/Index', [
            'products' => Product::with('category')->latest()->get(),
        ]);
    }

    /**
     * Muestra el formulario para crear un nuevo producto.
     */
    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::whereNull('parent_id')->with('children')->get(),
        ]);
    }

    /**
     * Guarda un nuevo producto en la base de datos.
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
        ]);

        if (!empty($validated['specifications'])) {
            $specArray = array_map('trim', explode(',', $validated['specifications']));
            $validated['specifications'] = json_encode($specArray);
        }

        $productData = collect($validated)->except('gallery_files')->toArray();
        $product = $request->user()->store->products()->create($productData);

        if ($request->hasFile('gallery_files')) {
            foreach ($request->file('gallery_files') as $file) {
                $path = $file->store('products', 'public');
                $product->images()->create(['path' => '/storage/' . $path]);
            }
        }
        return redirect()->route('admin.products.index');
    }

    /**
     * Muestra el formulario para editar un producto existente.
     */
    public function edit(Product $product)
    {
        $product->load('images', 'category.parent'); // Cargamos el producto con sus relaciones
        return Inertia::render('Products/Edit', [
            'product' => $product,
            // Hacemos lo mismo aquí
            'categories' => Category::whereNull('parent_id')->with('children')->get(),
        ]);
    }

    /**
     * Actualiza el producto en la base de datos (VERSIÓN MEJORADA).
     */
    public function update(Request $request, Product $product)
    {
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
        ]);

        // 1. Borramos las imágenes marcadas
        if (!empty($validated['images_to_delete'])) {
            $imagesToDelete = ProductImage::whereIn('id', $validated['images_to_delete'])->get();
            foreach ($imagesToDelete as $image) {
                // Borramos el archivo físico del disco
                Storage::disk('public')->delete(str_replace('/storage/', '', $image->path));
                // Borramos el registro de la base de datos
                $image->delete();
            }
        }

        // 2. Subimos las imágenes nuevas
        if ($request->hasFile('new_gallery_files')) {
            foreach ($request->file('new_gallery_files') as $file) {
                $path = $file->store('products', 'public');
                $product->images()->create(['path' => '/storage/' . $path]);
            }
        }

        // 3. Actualizamos los datos de texto del producto
        $productData = $request->except(['new_gallery_files', 'images_to_delete', '_method']);

        if (!empty($productData['specifications']) && is_string($productData['specifications'])) {
            $specArray = array_map('trim', explode(',', $productData['specifications']));
            $productData['specifications'] = json_encode($specArray);
        }
        
        $product->update($productData);
        
        return redirect()->route('admin.products.index');
    }

    /**
     * Elimina el producto de la base de datos.
     */
    public function destroy(Product $product)
    {
        // Borramos las imágenes asociadas del disco
        foreach($product->images as $image) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $image->path));
        }
        
        $product->delete();
        
        return redirect()->route('admin.products.index');
    }
}