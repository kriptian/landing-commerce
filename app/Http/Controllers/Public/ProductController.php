<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Muestra el catálogo de productos de UNA tienda específica.
     */
    public function index(Request $request, Store $store)
    {
        // 1. Árbol: solo categorías con productos (propios o en descendientes), con contador de productos
        $rootCats = $store->categories()->whereNull('parent_id')->get(['id','name']);
        $categories = $rootCats->map(function($cat) use ($store) {
            $count = $this->productsCountForCategory($store, $cat->id);
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'products_count' => $count,
                'has_children_with_products' => $this->hasChildrenWithProducts($store, $cat->id),
            ];
        })->filter(fn($c) => $c['products_count'] > 0)->values();

        // Empezamos la consulta de productos (incluimos variantes para calcular bajo stock en frontend)
        $productsQuery = $store->products()->with([
            'images',
            'variants:id,product_id,stock,minimum_stock,alert'
        ]);

        // --- LÓGICA DE FILTRADO: múltiples categorías y descendientes ---
        $selectedIds = collect();
        if ($request->filled('categories')) {
            $raw = $request->input('categories');
            $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
            foreach ($ids as $id) {
                $id = (int) $id; if ($id <= 0) continue;
                $selectedIds->push($id);
                $stack = [$id];
                while (!empty($stack)) {
                    $currentId = array_pop($stack);
                    $children = $store->categories()->where('parent_id', $currentId)->pluck('id')->all();
                    foreach ($children as $childId) {
                        if (!$selectedIds->contains($childId)) {
                            $selectedIds->push($childId);
                            $stack[] = $childId;
                        }
                    }
                }
            }
        } elseif ($request->filled('category')) {
            $id = (int) $request->category;
            if ($id > 0) {
                $selectedIds->push($id);
                $stack = [$id];
                while (!empty($stack)) {
                    $currentId = array_pop($stack);
                    $children = $store->categories()->where('parent_id', $currentId)->pluck('id')->all();
                    foreach ($children as $childId) {
                        if (!$selectedIds->contains($childId)) {
                            $selectedIds->push($childId);
                            $stack[] = $childId;
                        }
                    }
                }
            }
        }
        if ($selectedIds->isNotEmpty()) {
            $productsQuery->whereIn('category_id', $selectedIds->unique()->values()->all());
        }

        if ($request->filled('search')) {
            $productsQuery->where('name', 'like', '%' . $request->search . '%');
        }
        
        // --- FIN DE LA LÓGICA ---

        return Inertia::render('Public/ProductList', [
            'products' => $productsQuery->latest()->paginate(10)->withQueryString(),
            'store' => $store,
            'categories' => $categories, // Mandamos solo las categorías principales para los botones
            'filters' => [
                'category' => $request->input('category'),
                'categories' => $request->input('categories'),
                'search' => $request->input('search'),
            ],
        ]);
    }

    /**
     * Muestra un producto específico de UNA tienda.
     */
    public function show(Store $store, Product $product)
    {
        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load('images', 'category', 'variants', 'store');
        
        return inertia('Public/ProductPage', [
            'product' => $product,
            'store' => $store,
        ]);
    }

    // Nuevo: hijos públicos para arbol en el catálogo
    public function children(Request $request, Store $store, Category $category)
    {
        if ($category->store_id !== $store->id) {
            abort(404);
        }
        $children = $category->children()->orderBy('name')->get(['id','name','parent_id']);
        $data = $children->map(function($cat) use ($store) {
            $count = $this->productsCountForCategory($store, $cat->id);
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'parent_id' => $cat->parent_id,
                'products_count' => $count,
                'has_children_with_products' => $this->hasChildrenWithProducts($store, $cat->id),
            ];
        })->filter(fn($c) => $c['products_count'] > 0)->values();
        return response()->json([
            'data' => $data,
        ]);
    }

    private function collectIdsIncludingDescendants(Store $store, int $categoryId): array
    {
        $ids = [$categoryId];
        $stack = [$categoryId];
        while (!empty($stack)) {
            $current = array_pop($stack);
            $children = $store->categories()->where('parent_id', $current)->pluck('id')->all();
            foreach ($children as $child) {
                if (!in_array($child, $ids, true)) {
                    $ids[] = $child;
                    $stack[] = $child;
                }
            }
        }
        return $ids;
    }

    private function productsCountForCategory(Store $store, int $categoryId): int
    {
        $ids = $this->collectIdsIncludingDescendants($store, $categoryId);
        return $store->products()->whereIn('category_id', $ids)->count();
    }

    private function hasChildrenWithProducts(Store $store, int $categoryId): bool
    {
        $childrenIds = $store->categories()->where('parent_id', $categoryId)->pluck('id')->all();
        foreach ($childrenIds as $childId) {
            if ($this->productsCountForCategory($store, $childId) > 0) {
                return true;
            }
        }
        return false;
    }
}