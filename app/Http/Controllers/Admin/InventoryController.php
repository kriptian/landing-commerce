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
                        ->whereColumn('quantity', '<', 'minimum_stock'); // ESTRICTO: menor al mínimo
                })
                // O productos con al menos una variante con bajo stock.
                // Regla: si 'alert' es NULL o <= 0, usamos 'minimum_stock' como umbral.
                ->orWhereHas('variants', function ($q) {
                    $q->where('stock', '>', 0)
                      ->whereRaw('`stock` < (CASE WHEN `alert` IS NOT NULL AND `alert` > 0 THEN `alert` ELSE `minimum_stock` END)'); // ESTRICTO: menor al umbral
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