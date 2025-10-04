<?php

// 1. LO MOVIMOS AL NAMESPACE CORRECTO
namespace App\Http\Controllers\Admin; 

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // <-- Importamos la regla para el 'unique' avanzado
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:gestionar categorias');
    }
    public function index(Request $request)
    {
        $categories = $request->user()->store->categories()
                                ->whereNull('parent_id')
                                ->with('children') // Cargamos las subcategorías de una vez
                                ->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Categories/Create');
    }

    public function store(Request $request)
    {
        $storeId = $request->user()->store_id;

        // 2. ARREGLAMOS LA VALIDACIÓN 'UNIQUE'
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories')->where(fn ($q) => $q
                    ->where('store_id', $storeId)
                    ->whereNull('parent_id')
                ),
            ],
            'subcategories' => 'nullable|array',
            'subcategories.*.name' => [
                'required_with:subcategories', 'string', 'max:255',
                Rule::unique('categories', 'name')->where(fn ($q) => $q
                    ->where('store_id', $storeId)
                ),
            ],
            'subcategories.*.children' => 'nullable|array',
            'subcategories.*.children.*.name' => [
                'required_with:subcategories.*.children', 'string', 'max:255',
                Rule::unique('categories', 'name')->where(fn ($q) => $q
                    ->where('store_id', $storeId)
                ),
            ],
        ]);

        $store = $request->user()->store;

        $parentCategory = $store->categories()->create([
            'name' => $validated['name'],
            'parent_id' => null,
        ]);

        if (!empty($validated['subcategories'])) {
            foreach ($validated['subcategories'] as $subcategory) {
                if (empty($subcategory['name'])) {
                    continue;
                }
                $child = $store->categories()->create([
                    'name' => $subcategory['name'],
                    'parent_id' => $parentCategory->id,
                ]);
                // Crear hijas del subnivel si vienen
                if (!empty($subcategory['children']) && is_array($subcategory['children'])) {
                    $this->createNestedChildren($store, $child->id, $subcategory['children']);
                }
            }
        }

        return redirect()->route('admin.categories.index')->with('success', '¡Categoría creada con éxito!');
    }

    private function createNestedChildren($store, int $parentId, array $children): void
    {
        foreach ($children as $child) {
            if (empty($child['name'])) {
                continue;
            }
            $created = $store->categories()->create([
                'name' => $child['name'],
                'parent_id' => $parentId,
            ]);
            if (!empty($child['children']) && is_array($child['children'])) {
                $this->createNestedChildren($store, $created->id, $child['children']);
            }
        }
    }

    public function edit(Category $category)
    {
        // Seguridad: que no pueda editar categorías de otra tienda
        if ($category->store_id !== auth()->user()->store_id) {
            abort(403);
        }
        
        $category->load('children', 'parent');
        
        return Inertia::render('Categories/Edit', [
            'category' => $category,
        ]);
    }
    
    public function storeSubcategory(Request $request, Category $parentCategory)
    {
        $storeId = $request->user()->store_id;
        
        // Limitar a 3 niveles (1: raíz, 2: hijo, 3: nieto)
        $depth = 1;
        $cursor = $parentCategory;
        while ($cursor && $cursor->parent_id) {
            $depth++;
            $cursor = $cursor->parent; // relación parent()
            if ($depth > 10) break; // seguridad
        }
        if ($depth >= 3) {
            return back()->withErrors(['name' => 'Se permiten máximo 3 niveles de categorías.']);
        }

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories')->where(fn ($q) => $q
                    ->where('store_id', $storeId)
                    ->where('parent_id', $parentCategory->id)
                ),
            ],
        ]);

        $store = $request->user()->store;
        if (!$store || $store->id !== $parentCategory->store_id) {
            abort(403, 'Acción no autorizada.');
        }

        $store->categories()->create([
            'name' => $validated['name'],
            'parent_id' => $parentCategory->id,
        ]);

        return back();
    }

    public function update(Request $request, Category $category)
    {
        // Seguridad
        if ($category->store_id !== auth()->user()->store_id) {
            abort(403);
        }
        
        $storeId = $request->user()->store_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories')->ignore($category->id)->where(fn ($q) => $q
                    ->where('store_id', $storeId)
                ),
            ],
        ]);

        $category->update($validated);
        
        if ($category->parent_id) {
            return redirect()->route('admin.categories.edit', $category->parent_id);
        }

        return redirect()->route('admin.categories.index');
    }

    public function destroy(Category $category)
    {
        // Seguridad
        if ($category->store_id !== auth()->user()->store->id) {
            abort(403, 'Acción no autorizada.');
        }

        // 3. Validación: no eliminar si la categoría o CUALQUIER descendiente tiene productos.
        $ids = [$category->id];
        $queue = [$category->id];
        while (!empty($queue)) {
            $pid = array_shift($queue);
            $children = Category::where('parent_id', $pid)->pluck('id')->all();
            foreach ($children as $cid) {
                if (!in_array($cid, $ids, true)) {
                    $ids[] = $cid;
                    $queue[] = $cid;
                }
            }
        }
        $productsCount = Product::whereIn('category_id', $ids)->count();
        if ($productsCount > 0) {
            return back()->withErrors(['delete' => 'No se puede eliminar: existen productos asociados en esta categoría o en sus subniveles.']);
        }
        
        $parentId = $category->parent_id;
        $category->delete();
        
        if ($parentId) {
            // Permanecer en la misma vista (evitamos navegar al padre)
            return back();
        }

        return redirect()->route('admin.categories.index');
    }

    public function children(Request $request, $category)
    {
        // Evitar 404 por binding: resolvemos manualmente
        $model = Category::find($category);
        if (!$model) {
            return response()->json(['data' => []]);
        }
        // Seguridad: solo categorías de la tienda del usuario
        if ($model->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $children = $model->children()
            ->select('id','name','parent_id')
            ->withCount('children')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $children,
        ]);
    }
}