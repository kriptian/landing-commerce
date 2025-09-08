<?php

namespace App\Http\Controllers\Public; 

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Muestra el catálogo de productos (página principal pública).
     */
    public function index()
    {
        return Inertia::render('Public/ProductList', [
            'products' => Product::with(['category', 'images'])->latest()->get(),
        ]);
    }

    /**
     * ESTA ES LA NUEVA FUNCIÓN QUE DEBES AGREGAR
     * Muestra un solo producto en detalle.
     */
    public function show(Product $product)
    {
        // Le cargamos las relaciones que la vista necesita (imágenes, categoría, etc.)
        $product->load('images', 'category');

        return inertia('Public/ProductPage', [
            'product' => $product,
        ]);
    }
}