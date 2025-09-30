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
            // Solo mostrar bajo stock cuando existe ALERTA (>0) y stock <= alerta
            $productsQuery->where(function ($query) {
                $query->where(function ($q) {
                    // Productos sin variantes: requiere columna 'alert' > 0 y quantity <= alert
                    $q->whereDoesntHave('variants')
                        ->where('quantity', '>', 0)
                        ->whereNotNull('alert')
                        ->where('alert', '>', 0)
                        ->whereColumn('quantity', '<=', 'alert');
                })
                ->orWhereHas('variants', function ($q) {
                    // Variantes: alerta > 0 y stock <= alerta
                    $q->where('stock', '>', 0)
                      ->whereNotNull('alert')
                      ->where('alert', '>', 0)
                      ->whereColumn('stock', '<=', 'alert');
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