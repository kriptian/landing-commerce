<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;

class LandingPageController extends Controller
{
    /**
     * Muestra la página del catálogo público.
     */
    public function showCatalog()
    {
        return Inertia::render('Products/Public/ProductList', [
            'products' => Product::with('category')->latest()->get(),
        ]);
    }

    /**
     * Muestra la página de detalle de un solo producto.
     */
    public function showLandingPage(Product $product)
    {
        return Inertia::render('Public/ProductPage', [
            'product' => $product
        ]);
    }
}