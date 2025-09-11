<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        // Hacemos el mismo filtro explícito aquí
        $categories = $request->user()->store->categories()
                                ->whereNull('parent_id')
                                ->with('children')
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
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'subcategories' => 'nullable|array',
            'subcategories.*.name' => 'required_with:subcategories|string|max:255|unique:categories,name',
        ]);

        $store = $request->user()->store;
        if (!$store) {
            return back()->withErrors(['store' => 'No se encontró una tienda para este usuario.']);
        }

        $parentCategory = $store->categories()->create([
            'name' => $validated['name'],
            'parent_id' => null,
        ]);

        if (!empty($validated['subcategories'])) {
            foreach ($validated['subcategories'] as $subcategory) {
                if (!empty($subcategory['name'])) { // Doble chequeo por si llega un campo vacío
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
        // Siempre cargamos las relaciones para que la vista tenga toda la información
        $category->load('children', 'parent');
        
        return Inertia::render('Categories/Edit', [
            'category' => $category,
        ]);
    }
    
    public function storeSubcategory(Request $request, Category $parentCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        $store = $request->user()->store;
        if (!$store || $store->id !== $parentCategory->store_id) {
            abort(403, 'Acción no autorizada.');
        }

        $store->categories()->create([
            'name' => $validated['name'],
            'parent_id' => $parentCategory->id,
        ]);

        return back(); // Redirige a la misma página de edición
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,'.$category->id,
        ]);

        $category->update($validated);
        
        // Redirigimos a la página de edición de su padre si es una subcategoría,
        // o al índice si es una categoría principal.
        if ($category->parent_id) {
            return redirect()->route('admin.categories.edit', $category->parent_id);
        }

        return redirect()->route('admin.categories.index');
    }

    public function destroy(Category $category)
    {
        if ($category->store_id !== auth()->user()->store->id) {
            abort(403, 'Acción no autorizada.');
        }

        if ($category->children()->count() > 0) {
            return back()->withErrors(['delete' => 'No se puede eliminar una categoría que tiene subcategorías.']);
        }
        
        $parentId = $category->parent_id;
        $category->delete();
        
        // Si era una subcategoría, volvemos a la página de edición de su padre.
        if ($parentId) {
            return redirect()->route('admin.categories.edit', $parentId);
        }

        // Si era una categoría principal, volvemos al índice.
        return redirect()->route('admin.categories.index');
    }
}