<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories)
    {
        $this->middleware('can:gestionar categorias')->only(['index', 'edit', 'update', 'destroy']);
        $this->middleware(function (Request $request, $next) {
            if (! $request->user()->can('gestionar categorias') && ! $request->user()->can('crear categorias')) {
                abort(403, 'No tienes permiso para crear categorias.');
            }

            return $next($request);
        })->only(['create', 'store', 'storeSubcategory']);
        $this->middleware(function (Request $request, $next) {
            $permissions = ['gestionar categorias', 'crear categorias', 'crear productos', 'editar productos'];
            if (! collect($permissions)->contains(fn (string $permission) => $request->user()->can($permission))) {
                abort(403);
            }

            return $next($request);
        })->only('children');
    }

    public function index(Request $request)
    {
        return Inertia::render('Categories/Index', [
            'categories' => $this->categories->tree($request->user()->store),
        ]);
    }

    public function create()
    {
        return Inertia::render('Categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subcategories' => 'nullable|array|max:50',
            'subcategories.*.name' => 'required|string|max:255',
            'subcategories.*.children' => 'nullable|array|max:50',
            'subcategories.*.children.*.name' => 'required|string|max:255',
        ]);

        $category = $this->categories->createTree(
            $request->user()->store,
            $validated['name'],
            $validated['subcategories'] ?? []
        );

        if ($request->expectsJson()) {
            return response()->json(['category' => $category], 201);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Categoria creada con exito.');
    }

    public function edit(Request $request, Category $category)
    {
        $category = $request->user()->store->categories()->findOrFail($category->id);
        $category->load('children', 'parent');

        return Inertia::render('Categories/Edit', ['category' => $category]);
    }

    public function storeSubcategory(Request $request, Category $parentCategory)
    {
        $store = $request->user()->store;
        $parentCategory = $store->categories()->findOrFail($parentCategory->id);
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $category = $this->categories->create($store, $validated['name'], $parentCategory);

        if ($request->expectsJson()) {
            return response()->json(['category' => $category], 201);
        }

        return back()->with('success', 'Categoria creada con exito.');
    }

    public function update(Request $request, Category $category)
    {
        $store = $request->user()->store;
        $category = $store->categories()->findOrFail($category->id);
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $category = $this->categories->update($store, $category, $validated['name']);

        if ($request->expectsJson()) {
            return response()->json(['category' => $category]);
        }

        return back()->with('success', 'Categoria actualizada con exito.');
    }

    public function destroy(Request $request, Category $category)
    {
        $store = $request->user()->store;
        $category = $store->categories()->findOrFail($category->id);
        $this->categories->delete($store, $category);

        return back()->with('success', 'Categoria eliminada con exito.');
    }

    public function children(Request $request, Category $category)
    {
        $store = $request->user()->store;
        $category = $store->categories()->findOrFail($category->id);
        $children = $category->children()
            ->withCount(['children', 'products'])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $child) => [
                'id' => $child->id,
                'name' => $child->name,
                'parent_id' => $child->parent_id,
                'children_count' => (int) $child->children_count,
                'products_count' => (int) $child->products_count,
                'depth' => $this->categories->depth($store, $child),
            ]);

        return response()->json(['data' => $children]);
    }
}
