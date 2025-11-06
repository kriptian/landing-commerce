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
        // Cuando el enlace se comparte en redes (WhatsApp/FB/Twitter/etc.),
        // los crawlers no ejecutan JS. Servimos una vista Blade estática con OG dinámicos.
        if ($this->isCrawler($request)) {
            $title = $store->name;
            $description = 'Catálogo de ' . $store->name;
            $image = $this->absoluteUrl($store->logo_url) ?: $this->fallbackOgImage();
            $url = $request->fullUrl();

            return response()->view('og.store', [
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'url' => $url,
                'site_name' => $store->name,
                'icon' => $image,
            ]);
        }

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
        // También cargamos variantOptions para incluir sus imágenes en main_image_url
        $productsQuery = $store->products()->where('is_active', true)->with([
            'images',
            'variants:id,product_id,stock,minimum_stock,alert',
            'variantOptions.children' // Cargar variantOptions con children para incluir imágenes de variantes
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
        
        // Filtro: solo productos en promoción (global o individual)
        if ($request->boolean('promo')) {
            $storePromoOn = (int) ($store->promo_active ? 1 : 0) === 1 && (int) ($store->promo_discount_percent ?? 0) > 0;
            if (!$storePromoOn) {
                $productsQuery->where('promo_active', true)->where('promo_discount_percent', '>', 0);
            }
        }

        // --- FIN DE LA LÓGICA ---

        // Verificar si hay productos con promoción en toda la tienda (no solo en la página actual)
        $hasProductsWithPromo = \App\Models\Product::where('store_id', $store->id)
            ->where('promo_active', true)
            ->where('promo_discount_percent', '>', 0)
            ->exists();

        // Obtener el porcentaje máximo de promoción de todos los productos con promoción
        $maxProductPromoPercent = \App\Models\Product::where('store_id', $store->id)
            ->where('promo_active', true)
            ->where('promo_discount_percent', '>', 0)
            ->max('promo_discount_percent') ?? 0;

        return Inertia::render('Public/ProductList', [
            'products' => $productsQuery->latest()->paginate(36)->withQueryString(),
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'logo_url' => $store->logo_url,
                'slug' => $store->slug,
                'phone' => $store->phone,
                'facebook_url' => $store->facebook_url,
                'instagram_url' => $store->instagram_url,
                'tiktok_url' => $store->tiktok_url,
                'promo_active' => $store->promo_active,
                'promo_discount_percent' => $store->promo_discount_percent,
                'catalog_use_default' => $store->catalog_use_default ?? true,
                'catalog_product_template' => $store->catalog_product_template ?? 'default',
                'catalog_header_style' => $store->catalog_header_style ?? 'default',
                'catalog_button_color' => $store->catalog_button_color ?? '#1F2937',
                'catalog_promo_banner_color' => $store->catalog_promo_banner_color ?? '#DC2626',
                'catalog_promo_banner_text_color' => $store->catalog_promo_banner_text_color ?? '#FFFFFF',
                'catalog_variant_button_color' => $store->catalog_variant_button_color ?? '#2563EB',
                'catalog_purchase_button_color' => $store->catalog_purchase_button_color ?? '#2563EB',
                'catalog_cart_bubble_color' => $store->catalog_cart_bubble_color ?? '#2563EB',
                'catalog_social_button_color' => $store->catalog_social_button_color ?? '#2563EB',
                'catalog_logo_position' => $store->catalog_logo_position ?? 'center',
                'catalog_menu_type' => $store->catalog_menu_type ?? 'hamburger',
                'catalog_header_bg_color' => $store->catalog_header_bg_color ?? '#FFFFFF',
                'catalog_header_text_color' => $store->catalog_header_text_color ?? '#1F2937',
                'catalog_button_bg_color' => $store->catalog_button_bg_color ?? '#2563EB',
                'catalog_button_text_color' => $store->catalog_button_text_color ?? '#FFFFFF',
                'catalog_body_bg_color' => $store->catalog_body_bg_color ?? '#FFFFFF',
                'catalog_body_text_color' => $store->catalog_body_text_color ?? '#1F2937',
                'catalog_input_bg_color' => $store->catalog_input_bg_color ?? '#FFFFFF',
                'catalog_input_text_color' => $store->catalog_input_text_color ?? '#1F2937',
            ],
            'categories' => $categories, // Mandamos solo las categorías principales para los botones
            'hasProductsWithPromo' => $hasProductsWithPromo, // Información global sobre productos con promoción
            'maxProductPromoPercent' => (int) $maxProductPromoPercent, // Porcentaje máximo de promoción de productos
            'filters' => [
                'category' => $request->input('category'),
                'categories' => $request->input('categories'),
                'search' => $request->input('search'),
                'promo' => $request->boolean('promo'),
            ],
        ]);
    }

    /**
     * Muestra un producto específico de UNA tienda.
     */
    public function show(Store $store, Product $product)
    {
        if ($product->store_id !== $store->id || !$product->is_active) {
            abort(404);
        }

        $product->load('images', 'category', 'variants', 'variantOptions.children', 'store');
        // Productos relacionados: misma tienda, misma categoría si existe, activos, excluyendo el actual
        $related = $store->products()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, function ($q) use ($product) {
                $q->where('category_id', $product->category_id);
            })
            ->latest()
            ->take(12)
            ->get(['id','name','price','promo_active','promo_discount_percent','quantity','alert','track_inventory','main_image_url']);

        // Te puede interesar: otros productos de la tienda, preferiblemente de otra categoría y sin duplicar los relacionados
        $excludeIds = $related->pluck('id')->push($product->id)->all();
        $youMayLike = $store->products()
            ->where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->when($product->category_id, function ($q) use ($product) {
                $q->where('category_id', '!=', $product->category_id);
            })
            ->inRandomOrder()
            ->take(12)
            ->get(['id','name','price','promo_active','promo_discount_percent','quantity','alert','track_inventory','main_image_url']);
        // Crawler: OG por producto
        if ($this->isCrawler(request())) {
            $title = $product->name . ' · ' . $store->name;
            $description = $product->short_description ?: ('Compra ' . $product->name . ' en ' . $store->name);
            $image = $this->absoluteUrl($product->main_image_url) ?: ($this->absoluteUrl($store->logo_url) ?: $this->fallbackOgImage());
            $url = request()->fullUrl();

            return response()->view('og.product', [
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'url' => $url,
                'site_name' => $store->name,
                'icon' => $this->absoluteUrl($store->logo_url) ?: $this->fallbackOgImage(),
            ]);
        }
        
        // Asegurar que variant_options se serialicen correctamente con sus imágenes
        $product->loadMissing('variantOptions.children');
        
        // Convertir variant_options a array manualmente para asegurar que se serialicen correctamente
        $productArray = $product->toArray();
        
        // Asegurar que variant_options se serialicen correctamente
        if ($product->variantOptions && $product->variantOptions->count() > 0) {
            $variantOptionsArray = [];
            foreach ($product->variantOptions as $parentOption) {
                $parentData = [
                    'id' => $parentOption->id,
                    'name' => $parentOption->name,
                    'parent_id' => $parentOption->parent_id,
                    'price' => $parentOption->price,
                    'image_path' => $parentOption->image_path,
                    'order' => $parentOption->order,
                    'children' => [],
                ];
                
                // Agregar hijos
                if ($parentOption->children && $parentOption->children->count() > 0) {
                    foreach ($parentOption->children as $child) {
                        $imagePath = $child->image_path;
                        
                        // DEBUG: Log temporal para verificar qué se está cargando
                        \Log::info('🔍 SHOW - Cargando variant option child', [
                            'id' => $child->id,
                            'name' => $child->name,
                            'image_path_raw' => $imagePath,
                        ]);
                        
                        // Asegurar que image_path esté en el formato correcto
                        if (!empty($imagePath) && !str_starts_with($imagePath, 'http')) {
                            // Si no empieza con /storage/, agregarlo
                            if (!str_starts_with($imagePath, '/storage/')) {
                                $imagePath = '/storage/' . ltrim($imagePath, '/');
                            }
                        }
                        
                        $childData = [
                            'id' => $child->id,
                            'name' => $child->name,
                            'parent_id' => $child->parent_id,
                            'price' => $child->price,
                            'image_path' => $imagePath ?: null,
                            'order' => $child->order,
                        ];
                        
                        // DEBUG: Verificar qué se está enviando al frontend
                        \Log::info('🔍 SHOW - Enviando variant option child al frontend', [
                            'id' => $childData['id'],
                            'name' => $childData['name'],
                            'image_path_final' => $childData['image_path'],
                        ]);
                        
                        $parentData['children'][] = $childData;
                    }
                }
                
                $variantOptionsArray[] = $parentData;
            }
            
            $productArray['variant_options'] = $variantOptionsArray;
        }
        
        return inertia('Public/ProductPage', [
            'product' => $productArray,
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'logo_url' => $store->logo_url,
                'slug' => $store->slug,
                'phone' => $store->phone,
                'facebook_url' => $store->facebook_url,
                'instagram_url' => $store->instagram_url,
                'tiktok_url' => $store->tiktok_url,
                'promo_active' => $store->promo_active,
                'promo_discount_percent' => $store->promo_discount_percent,
                'catalog_use_default' => $store->catalog_use_default ?? true,
                'catalog_product_template' => $store->catalog_product_template ?? 'default',
                'catalog_header_style' => $store->catalog_header_style ?? 'default',
                'catalog_button_color' => $store->catalog_button_color ?? '#1F2937',
                'catalog_promo_banner_color' => $store->catalog_promo_banner_color ?? '#DC2626',
                'catalog_promo_banner_text_color' => $store->catalog_promo_banner_text_color ?? '#FFFFFF',
                'catalog_variant_button_color' => $store->catalog_variant_button_color ?? '#2563EB',
                'catalog_purchase_button_color' => $store->catalog_purchase_button_color ?? '#2563EB',
                'catalog_cart_bubble_color' => $store->catalog_cart_bubble_color ?? '#2563EB',
                'catalog_social_button_color' => $store->catalog_social_button_color ?? '#2563EB',
                'catalog_logo_position' => $store->catalog_logo_position ?? 'center',
                'catalog_menu_type' => $store->catalog_menu_type ?? 'hamburger',
                'catalog_header_bg_color' => $store->catalog_header_bg_color ?? '#FFFFFF',
                'catalog_header_text_color' => $store->catalog_header_text_color ?? '#1F2937',
                'catalog_button_bg_color' => $store->catalog_button_bg_color ?? '#2563EB',
                'catalog_button_text_color' => $store->catalog_button_text_color ?? '#FFFFFF',
                'catalog_body_bg_color' => $store->catalog_body_bg_color ?? '#FFFFFF',
                'catalog_body_text_color' => $store->catalog_body_text_color ?? '#1F2937',
                'catalog_input_bg_color' => $store->catalog_input_bg_color ?? '#FFFFFF',
                'catalog_input_text_color' => $store->catalog_input_text_color ?? '#1F2937',
            ],
            'related' => $related,
            'suggested' => $youMayLike,
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

    private function isCrawler(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') return false;
        $bots = [
            'facebookexternalhit', 'facebot', 'whatsapp', 'twitterbot', 'linkedinbot',
            'telegrambot', 'discordbot', 'slackbot', 'pinterest', 'googlebot', 'bingbot'
        ];
        foreach ($bots as $bot) {
            if (str_contains($ua, $bot)) return true;
        }
        return false;
    }

    private function absoluteUrl(?string $path): ?string
    {
        $u = trim((string) ($path ?? ''));
        if ($u === '') return null;
        if (preg_match('/^https?:\/\//i', $u)) return $u;
        $host = request()->getSchemeAndHttpHost();
        if (str_starts_with($u, '/')) return $host . $u;
        return $host . '/' . ltrim($u, '/');
    }

    private function fallbackOgImage(): string
    {
        $host = request()->getSchemeAndHttpHost();
        return $host . '/images/New_Logo_ondgtl.png?v=5';
    }
}