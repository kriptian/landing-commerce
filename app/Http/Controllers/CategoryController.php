<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Store;

class CategoryController extends Controller
{
    public function index()
    {
        // Busca todas las categorías y las pasa a la nueva vista de Vue
        return Inertia::render('Categories/Index', [
            'categories' => Category::all(),
        ]);
    }

    public function create()
    {
        // Muestra la página de Vue con el formulario de creación
        return Inertia::render('Categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Busca la tienda del usuario logueado y crea la categoría
        $store = $request->user()->store;
        if (!$store) {
            // Manejar el caso de que el usuario no tenga tienda
            return back()->withErrors(['store' => 'No se encontró una tienda para este usuario.']);
        }

        $store->categories()->create($validated);

        return redirect()->route('admin.categories.index');
    }

    public function edit(Category $category)
    {
        // Muestra la página de Vue con el formulario de edición,
        // pasándole la categoría que se va a editar.
        return Inertia::render('Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update($validated);

        return redirect()->route('admin.categories.index');
    }

    public function destroy(Category $category)
    {
        // Se asegura que la categoría pertenezca a la tienda del usuario logueado
        if ($category->store_id !== auth()->user()->store->id) {
            abort(403, 'Acción no autorizada.');
        }

        $category->delete();
        
        return redirect()->route('admin.categories.index');
    }

}