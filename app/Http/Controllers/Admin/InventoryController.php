<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InventoryExport;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ver inventario')->only(['index', 'export']);
    }

    /**
     * Muestra la página de gestión de inventario con filtros.
     */
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString(); // '', 'out_of_stock', 'low_stock'

        $productsQuery = $request->user()->store->products()
            ->with('variants')
            ->latest();

        if (!empty($search)) {
            $productsQuery->where('name', 'like', "%{$search}%");
        }

        if ($status === 'out_of_stock') {
            $productsQuery->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereDoesntHave('variants')
                        ->where('quantity', '<=', 0);
                })
                ->orWhereHas('variants', function ($q) {
                    $q->where('stock', '<=', 0);
                });
            });
        } elseif ($status === 'low_stock') {
            $productsQuery->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereDoesntHave('variants')
                        ->where('quantity', '>', 0)
                        ->whereNotNull('alert')
                        ->where('alert', '>', 0)
                        ->whereColumn('quantity', '<=', 'alert');
                })
                ->orWhereHas('variants', function ($q) {
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

    /**
     * Exporta el inventario de la tienda a Excel.
     */
    public function export(Request $request)
    {
        $fileName = 'inventario-' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new InventoryExport($request->user()->store->id), $fileName);
    }
}