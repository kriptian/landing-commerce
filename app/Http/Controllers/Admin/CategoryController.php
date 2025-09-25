<?php

// 1. LO MOVIMOS AL NAMESPACE CORRECTO
namespace App\Http\Controllers\Admin; 

use App\Http\Controllers\Controller;
use App\Models\Category;
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
                Rule::unique('categories')->where('store_id', $storeId)->whereNull('parent_id')
            ],
            'subcategories' => 'nullable|array',
            'subcategories.*.name' => [
                'required_with:subcategories', 'string', 'max:255',
                Rule::unique('categories', 'name')->where('store_id', $storeId)
            ],
        ]);

        $store = $request->user()->store;

        $parentCategory = $store->categories()->create([
            'name' => $validated['name'],
            'parent_id' => null,
        ]);

        if (!empty($validated['subcategories'])) {
            foreach ($validated['subcategories'] as $subcategory) {
                if (!empty($subcategory['name'])) {
                    $store->categories()->create([
                        'name' => $subcategory['name'],
                        'parent_id' => $parentCategory->id,
                    ]);
                }
            }
        }

        return redirect()->route('admin.categories.index');
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
        
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories')->where('store_id', $storeId)->where('parent_id', $parentCategory->id)
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
                Rule::unique('categories')->ignore($category->id)->where('store_id', $storeId)
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

        // 3. AÑADIMOS LA VALIDACIÓN DE PRODUCTOS
        if ($category->products()->count() > 0) {
            return back()->withErrors(['delete' => 'No se puede eliminar una categoría que tiene productos asociados.']);
        }

        if ($category->children()->count() > 0) {
            return back()->withErrors(['delete' => 'No se puede eliminar una categoría que tiene subcategorías.']);
        }
        
        $parentId = $category->parent_id;
        $category->delete();
        
        if ($parentId) {
            return redirect()->route('admin.categories.edit', $parentId);
        }

        return redirect()->route('admin.categories.index');
    }
}