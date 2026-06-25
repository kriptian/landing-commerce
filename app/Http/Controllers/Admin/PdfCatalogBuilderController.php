<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PdfCatalogBuilderController extends Controller
{
    public function __construct()
    {
        $this->middleware('plan:negociante,creador_pdf');
    }

    public function index(Request $request)
    {
        $store = $request->user()->store;

        $products = $store->products()
            ->with(['category', 'images', 'variantOptions.children'])
            ->where('is_active', true)
            ->latest()
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'description' => $product->short_description,
                'image_url' => $product->main_image_url,
                'category' => $product->category?->name,
            ]);

        return Inertia::render('Admin/PdfCatalogBuilder/Index', [
            'products' => $products,
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'logo_url' => $store->logo_url,
            ],
        ]);
    }
}
