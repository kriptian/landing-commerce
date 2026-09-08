<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ver inventario')->only(['index', 'show']);
        $this->middleware('can:crear productos')->only('store');
        $this->middleware('can:editar productos')->only('update');
        $this->middleware('can:eliminar productos')->only('destroy');
    }

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

    public function show(Request $request, Product $product)
    {
        return $request->user()->store->products()->findOrFail($product->id);
    }

    public function update(Request $request, Product $product)
    {
        $product = $request->user()->store->products()->findOrFail($product->id);

        $product->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'alert' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json($product);
    }

    public function destroy(Request $request, Product $product)
    {
        $product = $request->user()->store->products()->findOrFail($product->id);
        $product->delete();

        return response()->json(null, 204);
    }
}
