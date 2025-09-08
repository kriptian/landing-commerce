<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->store) {
            return response()->json(['message' => 'El usuario no tiene una tienda asignada.'], 404);
        }
        return $request->user()->store->products()->with('category')->latest()->get();
    }

    public function store(Request $request)
    {
        // ...
    }

    public function show(Product $product)
    {
        return $product;
    }

    public function update(Request $request, Product $product)
    {
        // ...
    }

    public function destroy(Product $product)
    {
        // ...
    }
}