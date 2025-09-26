<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Muestra el catálogo de productos de UNA tienda específica.
     */
    public function index(Request $request, Store $store)
    {
        // 1. Para los botones, traemos solo las categorías PRINCIPALES de la tienda
        $categories = $store->categories()
                            ->whereNull('parent_id')
                            ->get();

        // Empezamos la consulta de productos (incluimos variantes para calcular bajo stock en frontend)
        $productsQuery = $store->products()->with([
            'images',
            'variants:id,product_id,stock,minimum_stock,alert'
        ]);

        // --- LÓGICA DE FILTRADO CORREGIDA Y FINAL ---
        
        if ($request->filled('category')) {
            // Buscamos la categoría DENTRO de la tienda actual
            $selectedCategory = $store->categories()->with('children')->find($request->category);

            if ($selectedCategory) {
                // Sacamos los IDs de todas sus subcategorías Y AÑADIMOS EL ID DEL PADRE
                $categoryIds = $selectedCategory->children->pluck('id')->push($selectedCategory->id);
                
                // Buscamos productos que estén en CUALQUIERA de esas categorías
                $productsQuery->whereIn('category_id', $categoryIds);
            }
        }

        if ($request->filled('search')) {
            $productsQuery->where('name', 'like', '%' . $request->search . '%');
        }
        
        // --- FIN DE LA LÓGICA ---

        return Inertia::render('Public/ProductList', [
            'products' => $productsQuery->latest()->paginate(10)->withQueryString(),
            'store' => $store,
            'categories' => $categories, // Mandamos solo las categorías principales para los botones
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    /**
     * Muestra un producto específico de UNA tienda.
     */
    public function show(Store $store, Product $product)
    {
        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load('images', 'category', 'variants', 'store');
        
        return inertia('Public/ProductPage', [
            'product' => $product,
            'store' => $store,
        ]);
    }
}