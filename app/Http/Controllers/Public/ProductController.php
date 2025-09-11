<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store; // <-- Añadimos el import de Store
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Muestra el catálogo de productos de UNA tienda específica.
     */
    public function index(Store $store)
    {
        return Inertia::render('Public/ProductList', [
            // Buscamos solo los productos que pertenecen a esa tienda
            'products' => $store->products()->with('images')->latest()->get(),
            'store' => $store, // Pasamos la info de la tienda a la vista
        ]);
    }

    /**
     * Muestra un producto específico de UNA tienda.
     */
    public function show(Store $store, Product $product)
    {
        // Medida de seguridad: nos aseguramos que el producto sí pertenezca a la tienda de la URL
        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load('images', 'category');
        
        return inertia('Public/ProductPage', [
            'product' => $product,
            'store' => $store, // Pasamos la info de la tienda a la vista
        ]);
    }
}