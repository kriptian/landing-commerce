<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public const MAX_DEPTH = 3;

    /** @return array<int, array<string, mixed>> */
    public function tree(Store $store): array
    {
        $categories = $store->categories()
            ->withCount(['children', 'products'])
            ->orderBy('name')
            ->get();
        $byParent = $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        return $this->buildLevel($byParent, 0, 1, []);
    }

    /** @param array<int, array<string, mixed>> $nodes */
    public function createTree(Store $store, string $name, array $nodes = []): Category
    {
        return DB::transaction(function () use ($store, $name, $nodes) {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $root = $this->createLocked($store, $name);

            foreach ($nodes as $index => $node) {
                $child = $this->createLocked($store, (string) ($node['name'] ?? ''), $root, "subcategories.{$index}.name");
                foreach (($node['children'] ?? []) as $childIndex => $grandchild) {
                    $this->createLocked(
                        $store,
                        (string) ($grandchild['name'] ?? ''),
                        $child,
                        "subcategories.{$index}.children.{$childIndex}.name"
                    );
                }
            }

            return $root;
        });
    }

    public function create(Store $store, string $name, ?Category $parent = null): Category
    {
        return DB::transaction(function () use ($store, $name, $parent) {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($parent) {
                $parent = $store->categories()->lockForUpdate()->findOrFail($parent->id);
            }

            return $this->createLocked($store, $name, $parent);
        });
    }

    public function update(Store $store, Category $category, string $name): Category
    {
        return DB::transaction(function () use ($store, $category, $name) {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $category = $store->categories()->lockForUpdate()->findOrFail($category->id);
            $name = $this->normalizeName($name);
            $this->ensureUniqueSibling($store, $name, $category->parent_id, $category->id);
            $category->update(['name' => $name]);

            return $category;
        });
    }

    /** @return array{descendant_count: int, product_count: int} */
    public function delete(Store $store, Category $category): array
    {
        return DB::transaction(function () use ($store, $category) {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $category = $store->categories()->lockForUpdate()->findOrFail($category->id);
            $ids = $this->descendantIds($store, $category);
            $productCount = $store->products()->whereIn('category_id', $ids)->lockForUpdate()->count();

            if ($productCount > 0) {
                throw ValidationException::withMessages([
                    'delete' => "No puedes eliminar esta categoria porque {$productCount} producto(s) dependen de ella o de sus subcategorias.",
                ]);
            }

            $category->delete();

            return [
                'descendant_count' => count($ids) - 1,
                'product_count' => 0,
            ];
        });
    }

    public function depth(Store $store, Category $category): int
    {
        $depth = 1;
        $parentId = $category->parent_id;
        $visited = [$category->id];

        while ($parentId) {
            if (in_array($parentId, $visited, true)) {
                throw ValidationException::withMessages(['name' => 'La estructura de categorias contiene un ciclo invalido.']);
            }
            $visited[] = $parentId;
            $parent = $store->categories()->findOrFail($parentId);
            $parentId = $parent->parent_id;
            $depth++;
        }

        return $depth;
    }

    /** @param \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Category>> $byParent */
    private function buildLevel($byParent, int $parentId, int $depth, array $path): array
    {
        return $byParent->get($parentId, collect())->map(function (Category $category) use ($byParent, $depth, $path) {
            $currentPath = [...$path, $category->name];
            $children = $this->buildLevel($byParent, $category->id, $depth + 1, $currentPath);
            $subtreeProducts = (int) $category->products_count
                + collect($children)->sum('subtree_products_count');
            $descendants = count($children) + collect($children)->sum('descendants_count');

            return [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'depth' => $depth,
                'path' => implode(' > ', $currentPath),
                'children_count' => count($children),
                'descendants_count' => $descendants,
                'direct_products_count' => (int) $category->products_count,
                'subtree_products_count' => $subtreeProducts,
                'can_have_children' => $depth < self::MAX_DEPTH,
                'is_selectable' => count($children) === 0,
                'children' => $children,
            ];
        })->values()->all();
    }

    private function createLocked(Store $store, string $name, ?Category $parent = null, string $errorKey = 'name'): Category
    {
        $name = $this->normalizeName($name);
        if ($name === '') {
            throw ValidationException::withMessages([$errorKey => 'El nombre de la categoria es obligatorio.']);
        }
        if ($parent && $this->depth($store, $parent) >= self::MAX_DEPTH) {
            throw ValidationException::withMessages([$errorKey => 'Puedes organizar categorias en un maximo de 3 niveles.']);
        }

        $this->ensureUniqueSibling($store, $name, $parent?->id, null, $errorKey);

        return $store->categories()->create([
            'name' => $name,
            'parent_id' => $parent?->id,
        ]);
    }

    private function ensureUniqueSibling(
        Store $store,
        string $name,
        ?int $parentId,
        ?int $ignoreId = null,
        string $errorKey = 'name'
    ): void {
        $query = $store->categories()
            ->where('name', $name)
            ->when($parentId, fn ($builder) => $builder->where('parent_id', $parentId))
            ->when(! $parentId, fn ($builder) => $builder->whereNull('parent_id'))
            ->when($ignoreId, fn ($builder) => $builder->whereKeyNot($ignoreId));

        if ($query->exists()) {
            throw ValidationException::withMessages([
                $errorKey => 'Ya existe una categoria con este nombre dentro de la misma categoria superior.',
            ]);
        }
    }

    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    /** @return array<int, int> */
    private function descendantIds(Store $store, Category $category): array
    {
        $ids = [$category->id];
        $queue = [$category->id];

        while ($queue) {
            $children = $store->categories()->whereIn('parent_id', $queue)->pluck('id')->all();
            $queue = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$queue];
        }

        return $ids;
    }
}
