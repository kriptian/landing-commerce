<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia; // <-- Importamos Inertia

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ver inventario');
    }
    /**
     * Muestra la página de gestión de inventario.
     */
    public function index(Request $request)
    {
        // Filtros: búsqueda por nombre y estado de inventario
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString(); // '', 'out_of_stock', 'low_stock'

        $productsQuery = $request->user()->store->products()
            ->with('variants')
            ->latest();

        // Búsqueda por nombre del producto
        if (!empty($search)) {
            $productsQuery->where('name', 'like', "%{$search}%");
        }

        // Filtro por estado de inventario
        if ($status === 'out_of_stock') {
            $productsQuery->where(function ($query) {
                $query->where(function ($q) {
                    // Productos sin variantes agotados
                    $q->whereDoesntHave('variants')
                        ->where('quantity', '<=', 0);
                })
                // O productos con alguna variante agotada
                ->orWhereHas('variants', function ($q) {
                    $q->where('stock', '<=', 0);
                });
            });
        } elseif ($status === 'low_stock') {
            $productsQuery->where(function ($query) {
                $query->where(function ($q) {
                    // Productos sin variantes: bajo stock = (quantity > 0) y quantity <= minimum_stock
                    $q->whereDoesntHave('variants')
                        ->where('quantity', '>', 0)
                        ->whereColumn('quantity', '<=', 'minimum_stock');
                })
                // O productos con al menos una variante con bajo stock
                ->orWhereHas('variants', function ($q) {
                    // Comparar contra COALESCE(alert, minimum_stock)
                    $q->where('stock', '>', 0)
                      ->whereRaw('`stock` <= COALESCE(`alert`, `minimum_stock`)');
                });
            });
        }

        $products = $productsQuery->paginate(20)->withQueryString();

        return Inertia::render('Admin/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}