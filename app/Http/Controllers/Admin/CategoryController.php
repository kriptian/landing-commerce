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

        // Validación: Solo la categoría principal debe ser única en toda la tienda
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
                // NO validamos unique aquí porque las subcategorías pueden repetirse en diferentes categorías principales
            ],
            'subcategories.*.children' => 'nullable|array',
            'subcategories.*.children.*.name' => [
                'required_with:subcategories.*.children', 'string', 'max:255',
                // NO validamos unique aquí porque los subniveles pueden repetirse en diferentes padres
            ],
        ], [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.unique' => 'Ya existe una categoría principal con este nombre en tu tienda.',
            'subcategories.*.name.required_with' => 'El nombre de la subcategoría es obligatorio.',
            'subcategories.*.children.*.name.required_with' => 'El nombre del subnivel es obligatorio.',
        ]);

        $store = $request->user()->store;

        // Crear la categoría principal
        $parentCategory = $store->categories()->create([
            'name' => $validated['name'],
            'parent_id' => null,
        ]);

        // Crear subcategorías con validación de unicidad dentro del mismo padre
        if (!empty($validated['subcategories'])) {
            foreach ($validated['subcategories'] as $index => $subcategory) {
                if (empty($subcategory['name'])) {
                    continue;
                }
                
                // Validar que no exista otra subcategoría con el mismo nombre dentro de esta categoría principal
                $exists = Category::where('store_id', $storeId)
                    ->where('parent_id', $parentCategory->id)
                    ->where('name', $subcategory['name'])
                    ->exists();
                
                if ($exists) {
                    return back()->withErrors([
                        "subcategories.{$index}.name" => "Ya existe una subcategoría llamada '{$subcategory['name']}' en esta categoría principal."
                    ])->withInput();
                }
                
                $child = $store->categories()->create([
                    'name' => $subcategory['name'],
                    'parent_id' => $parentCategory->id,
                ]);
                
                // Crear hijas del subnivel si vienen
                if (!empty($subcategory['children']) && is_array($subcategory['children'])) {
                    $errors = $this->createNestedChildren($store, $child->id, $subcategory['children'], $index);
                    if ($errors) {
                        return back()->withErrors($errors)->withInput();
                    }
                }
            }
        }

        return redirect()->route('admin.categories.index')->with('success', '¡Categoría creada con éxito!');
    }

    private function createNestedChildren($store, int $parentId, array $children, int $subcategoryIndex = 0): ?array
    {
        $errors = [];
        foreach ($children as $childIndex => $child) {
            if (empty($child['name'])) {
                continue;
            }
            
            // Validar que no exista otra categoría con el mismo nombre dentro del mismo padre
            $exists = Category::where('store_id', $store->id)
                ->where('parent_id', $parentId)
                ->where('name', $child['name'])
                ->exists();
            
            if ($exists) {
                $errors["subcategories.{$subcategoryIndex}.children.{$childIndex}.name"] = 
                    "Ya existe un subnivel llamado '{$child['name']}' en esta subcategoría.";
                continue;
            }
            
            $created = $store->categories()->create([
                'name' => $child['name'],
                'parent_id' => $parentId,
            ]);
            if (!empty($child['children']) && is_array($child['children'])) {
                $nestedErrors = $this->createNestedChildren($store, $created->id, $child['children'], $subcategoryIndex);
                if ($nestedErrors) {
                    $errors = array_merge($errors, $nestedErrors);
                }
            }
        }
        
        return !empty($errors) ? $errors : null;
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