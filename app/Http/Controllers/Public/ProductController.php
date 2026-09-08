<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            $description = 'Catálogo de '.$store->name;
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

        $allCategories = $store->categories()->orderBy('name')->get(['id', 'name', 'parent_id']);
        $categoryCounts = $store->products()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');
        $categories = $this->categorySummaries($allCategories, $categoryCounts);

        // Empezamos la consulta de productos (incluimos variantes para calcular bajo stock en frontend)
        // También cargamos variantOptions para incluir sus imágenes en main_image_url
        $productsQuery = $store->products()->where('is_active', true)->with([
            'category:id,name',
            'images',
            'store:id,logo_url',
            'variants:id,product_id,options,stock,minimum_stock,alert',
            'variantOptions.children', // Cargar variantOptions con children para incluir imágenes de variantes
        ]);

        // --- LÓGICA DE FILTRADO: múltiples categorías y descendientes ---
        $selectedIds = collect();
        $categoryIds = $allCategories->pluck('id')->map(fn ($id) => (int) $id);
        $childrenByParent = $allCategories->groupBy(fn ($category) => (int) ($category->parent_id ?? 0));
        if ($request->filled('categories')) {
            $raw = $request->input('categories');
            $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id <= 0 || ! $categoryIds->contains($id)) {
                    continue;
                }
                $selectedIds->push(...$this->descendantIds($childrenByParent, $id));
            }
        } elseif ($request->filled('category')) {
            $id = (int) $request->category;
            if ($id > 0 && $categoryIds->contains($id)) {
                $selectedIds->push(...$this->descendantIds($childrenByParent, $id));
            }
        }
        if ($selectedIds->isNotEmpty()) {
            $productsQuery->whereIn('category_id', $selectedIds->unique()->values()->all());
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $productsQuery->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where('products.name', 'like', $like)
                    ->orWhere('products.short_description', 'like', $like)
                    ->orWhere('products.long_description', 'like', $like)
                    ->orWhere('products.meta_keywords', 'like', $like)
                    ->orWhere('products.barcode', 'like', $like)
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $like));
            });
        }

        if ($request->filled('min_price') && is_numeric($request->input('min_price'))) {
            $productsQuery->where('products.price', '>=', max(0, (float) $request->input('min_price')));
        }

        if ($request->filled('max_price') && is_numeric($request->input('max_price'))) {
            $productsQuery->where('products.price', '<=', max(0, (float) $request->input('max_price')));
        }

        if ($request->input('availability') === 'in_stock') {
            $productsQuery->where(function ($query) {
                $query->where('products.track_inventory', false)
                    ->orWhere('products.quantity', '>', 0)
                    ->orWhereHas('variants', fn ($variants) => $variants->where('stock', '>', 0));
            });
        }

        // Filtro: solo productos en promoción (global o individual)
        if ($request->boolean('promo')) {
            $storePromoOn = (int) ($store->promo_active ? 1 : 0) === 1 && (int) ($store->promo_discount_percent ?? 0) > 0;
            if (! $storePromoOn) {
                $productsQuery->where('promo_active', true)->where('promo_discount_percent', '>', 0);
            }
        }

        // --- ORDENAMIENTO ---
        $sort = $request->input('sort', 'latest'); // Por defecto: más recientes primero
        switch ($sort) {
            case 'name_asc':
                $productsQuery->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $productsQuery->orderBy('name', 'desc');
                break;
            case 'price_asc':
                $productsQuery->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $productsQuery->orderBy('price', 'desc');
                break;
            case 'category_asc':
                $productsQuery->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                    ->orderBy('categories.name', 'asc')
                    ->select('products.*');
                break;
            case 'category_desc':
                $productsQuery->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                    ->orderBy('categories.name', 'desc')
                    ->select('products.*');
                break;
            case 'latest':
            default:
                $productsQuery->latest();
                break;
        }

        // --- FIN DE LA LÓGICA ---

        // Verificar si hay productos con promoción en toda la tienda (no solo en la página actual)
        $hasProductsWithPromo = \App\Models\Product::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('promo_active', true)
            ->where('promo_discount_percent', '>', 0)
            ->exists();

        // Obtener el porcentaje máximo de promoción de todos los productos con promoción
        $maxProductPromoPercent = \App\Models\Product::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('promo_active', true)
            ->where('promo_discount_percent', '>', 0)
            ->max('promo_discount_percent') ?? 0;

        // Cargar imágenes de galería si el tipo es 'custom'
        $galleryImages = [];
        if ($store->gallery_type === 'custom') {
            // Usar la relación que ya filtra por is_active
            $galleryImages = $store->galleryImages()
                ->with(['product' => fn ($query) => $query
                    ->where('store_id', $store->id)
                    ->where('is_active', true)
                    ->select(['id', 'store_id', 'name'])])
                ->get()
                ->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'media_type' => $img->media_type ?? 'image',
                        'image_url' => $img->image_url,
                        'video_url' => $img->video_url,
                        'title' => $img->title,
                        'description' => $img->description,
                        'product_id' => $img->product_id,
                        'product' => $img->product ? [
                            'id' => $img->product->id,
                            'name' => $img->product->name,
                        ] : null,
                        'show_buy_button' => $img->show_buy_button,
                    ];
                })->toArray();
        }

        $products = $productsQuery->paginate(36)->withQueryString();
        $products->setCollection($products->getCollection()->map(fn (Product $product) => $this->catalogCard($product)));

        // Si es una petición AJAX normal (Load More) y no es Inertia, devolver JSON puro
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($products);
        }

        return Inertia::render('Public/ProductList', [
            'products' => $products,
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
                'gallery_type' => $store->gallery_type ?? 'products',
                'gallery_show_buy_button' => $store->gallery_show_buy_button ?? true,
                'gallery_images' => $galleryImages,
                'catalog_use_default' => $store->catalog_use_default ?? true,
                'catalog_product_template' => $store->catalog_product_template ?? 'default',
                'catalog_show_buy_button' => $store->catalog_show_buy_button ?? false,
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
                'cookie_consent_active' => $store->cookie_consent_active ?? false,
                'whatsapp_floating_button_active' => $store->whatsapp_floating_button_active ?? false,
                'whatsapp_floating_button_message' => $store->whatsapp_floating_button_message,
                'popup_active' => $store->popup_active ?? false,
                'popup_image_path' => $store->popup_image_path,
                'popup_button_text' => $store->popup_button_text,
                'popup_button_link' => $store->popup_button_link,
                'popup_show_button' => $store->popup_show_button ?? false,
                'popup_frequency' => $store->popup_frequency ?? 'session',
            ],
            'categories' => $categories, // Mandamos solo las categorías principales para los botones
            'hasProductsWithPromo' => $hasProductsWithPromo, // Información global sobre productos con promoción
            'maxProductPromoPercent' => (int) $maxProductPromoPercent, // Porcentaje máximo de promoción de productos
            'filters' => [
                'category' => $request->input('category'),
                'categories' => $request->input('categories'),
                'search' => $request->input('search'),
                'promo' => $request->boolean('promo'),
                'sort' => $request->input('sort', 'latest'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'availability' => $request->input('availability'),
                'selected_category' => $allCategories->firstWhere('id', (int) $request->input('category'))?->name,
            ],
        ]);
    }

    /**
     * Muestra un producto específico de UNA tienda.
     */
    public function show(Store $store, Product $product)
    {
        if ($product->store_id !== $store->id || ! $product->is_active) {
            abort(404);
        }

        $product->load('images', 'category', 'variants', 'variantOptions.children');
        $product->makeHidden(['purchase_price']);
        $product->variants->each->makeHidden(['purchase_price']);
        // Productos relacionados: misma tienda, misma categoría si existe, activos, excluyendo el actual
        $related = $store->products()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, function ($q) use ($product) {
                $q->where('category_id', $product->category_id);
            })
            ->with(['variants:id,product_id,stock', 'variantOptions:id,product_id'])
            ->latest()
            ->take(12)
            ->get(['id', 'name', 'price', 'promo_active', 'promo_discount_percent', 'quantity', 'alert', 'track_inventory', 'main_image_url']);

        // Calcular stock total para productos relacionados considerando variantes
        $related = $related->map(function ($relatedProduct) {
            // Solo calcular stock de variantes si realmente es un producto configurable
            // (debe tener variantes físicas Y definiciones de opciones)
            if ($relatedProduct->variants && $relatedProduct->variants->count() > 0
                && $relatedProduct->variantOptions && $relatedProduct->variantOptions->count() > 0) {
                $relatedProduct->quantity = $relatedProduct->variants->sum('stock');
            }
            // Remover las relaciones del objeto para no enviarlas al frontend
            $relatedProduct->unsetRelation('variants');
            $relatedProduct->unsetRelation('variantOptions');

            return $relatedProduct;
        });

        // Te puede interesar: otros productos de la tienda, preferiblemente de otra categoría y sin duplicar los relacionados
        $excludeIds = $related->pluck('id')->push($product->id)->all();
        $youMayLike = $store->products()
            ->where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->when($product->category_id, function ($q) use ($product) {
                $q->where('category_id', '!=', $product->category_id);
            })
            ->with(['variants:id,product_id,stock', 'variantOptions:id,product_id'])
            ->inRandomOrder()
            ->take(12)
            ->get(['id', 'name', 'price', 'promo_active', 'promo_discount_percent', 'quantity', 'alert', 'track_inventory', 'main_image_url']);

        // Calcular stock total para productos sugeridos considerando variantes
        $youMayLike = $youMayLike->map(function ($suggestedProduct) {
            // Solo calcular stock de variantes si realmente es un producto configurable
            // (debe tener variantes físicas Y definiciones de opciones)
            if ($suggestedProduct->variants && $suggestedProduct->variants->count() > 0
                && $suggestedProduct->variantOptions && $suggestedProduct->variantOptions->count() > 0) {
                $suggestedProduct->quantity = $suggestedProduct->variants->sum('stock');
            }
            // Remover las relaciones del objeto para no enviarlas al frontend
            $suggestedProduct->unsetRelation('variants');
            $suggestedProduct->unsetRelation('variantOptions');

            return $suggestedProduct;
        });
        // Crawler: OG por producto
        if ($this->isCrawler(request())) {
            $title = $product->name.' · '.$store->name;
            $description = $product->short_description ?: ('Compra '.$product->name.' en '.$store->name);
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

                        // Asegurar que image_path esté en el formato correcto
                        if (! empty($imagePath) && ! str_starts_with($imagePath, 'http')) {
                            // Si no empieza con /storage/, agregarlo
                            if (! str_starts_with($imagePath, '/storage/')) {
                                $imagePath = '/storage/'.ltrim($imagePath, '/');
                            }
                        }

                        $childData = [
                            'id' => $child->id,
                            'name' => $child->name,
                            'parent_id' => $child->parent_id,
                            'price' => $child->price,
                            'stock' => $child->stock,
                            'alert' => $child->alert,
                            'image_path' => $imagePath ?: null,
                            'order' => $child->order,
                        ];

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
                'catalog_show_buy_button' => $store->catalog_show_buy_button ?? false,
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
                'cookie_consent_active' => $store->cookie_consent_active ?? false,
                'whatsapp_floating_button_active' => $store->whatsapp_floating_button_active ?? false,
                'whatsapp_floating_button_message' => $store->whatsapp_floating_button_message,
            ],
            'related' => $related,
            'suggested' => $youMayLike,
        ]);
    }

    // Nueva página de política de privacidad
    public function privacyPolicy(Store $store)
    {
        return inertia('Public/PrivacyPolicy', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'logo_url' => $store->logo_url,
                'catalog_button_bg_color' => $store->catalog_button_bg_color ?? '#2563EB',
                'catalog_button_text_color' => $store->catalog_button_text_color ?? '#FFFFFF',
                'catalog_use_default' => $store->catalog_use_default ?? true,
            ],
            'privacyPolicy' => $store->privacy_policy_text,
        ]);
    }

    // Nuevo: hijos públicos para arbol en el catálogo
    public function children(Request $request, Store $store, Category $category)
    {
        if ($category->store_id !== $store->id) {
            abort(404);
        }
        $allCategories = $store->categories()->orderBy('name')->get(['id', 'name', 'parent_id']);
        $categoryCounts = $store->products()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');
        $data = $this->categorySummaries($allCategories, $categoryCounts, $category->id);

        return response()->json([
            'data' => $data,
        ]);
    }

    private function descendantIds(Collection $childrenByParent, int $categoryId): array
    {
        $ids = [$categoryId];
        $stack = [$categoryId];
        while (! empty($stack)) {
            $current = array_pop($stack);
            $children = $childrenByParent->get($current, collect())->pluck('id')->all();
            foreach ($children as $child) {
                if (! in_array($child, $ids, true)) {
                    $ids[] = $child;
                    $stack[] = $child;
                }
            }
        }

        return $ids;
    }

    private function categorySummaries(Collection $categories, Collection $counts, ?int $parentId = null): Collection
    {
        $childrenByParent = $categories->groupBy(fn ($category) => (int) ($category->parent_id ?? 0));
        $targetParent = $parentId ?? 0;

        return $childrenByParent->get($targetParent, collect())
            ->map(function ($category) use ($childrenByParent, $counts) {
                $childIds = $this->descendantIds($childrenByParent, (int) $category->id);
                $productCount = collect($childIds)->sum(fn ($id) => (int) ($counts[$id] ?? 0));
                $hasChildrenWithProducts = $childrenByParent->get((int) $category->id, collect())
                    ->contains(function ($child) use ($childrenByParent, $counts) {
                        return collect($this->descendantIds($childrenByParent, (int) $child->id))
                            ->sum(fn ($id) => (int) ($counts[$id] ?? 0)) > 0;
                    });

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'parent_id' => $category->parent_id,
                    'products_count' => $productCount,
                    'has_children_with_products' => $hasChildrenWithProducts,
                ];
            })
            ->filter(fn ($category) => $category['products_count'] > 0)
            ->values();
    }

    private function catalogCard(Product $product): array
    {
        $realVariants = $product->variants
            ->filter(fn ($variant) => ! empty($variant->options))
            ->values();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'short_description' => $product->short_description,
            'quantity' => $product->quantity,
            'alert' => $product->alert,
            'track_inventory' => $product->track_inventory,
            'promo_active' => $product->promo_active,
            'promo_discount_percent' => $product->promo_discount_percent,
            'is_featured' => $product->is_featured,
            'main_image_url' => $product->main_image_url,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'has_variants' => $realVariants->isNotEmpty(),
            'variants' => $realVariants->map(fn ($variant) => [
                'id' => $variant->id,
                'stock' => $variant->stock,
                'alert' => $variant->alert,
            ])->all(),
        ];
    }

    private function isCrawler(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return false;
        }
        $bots = [
            'facebookexternalhit', 'facebot', 'whatsapp', 'twitterbot', 'linkedinbot',
            'telegrambot', 'discordbot', 'slackbot', 'pinterest', 'googlebot', 'bingbot',
        ];
        foreach ($bots as $bot) {
            if (str_contains($ua, $bot)) {
                return true;
            }
        }

        return false;
    }

    private function absoluteUrl(?string $path): ?string
    {
        $u = trim((string) ($path ?? ''));
        if ($u === '') {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $u)) {
            return $u;
        }
        $host = request()->getSchemeAndHttpHost();
        if (str_starts_with($u, '/')) {
            return $host.$u;
        }

        return $host.'/'.ltrim($u, '/');
    }

    private function fallbackOgImage(): string
    {
        $host = request()->getSchemeAndHttpHost();

        return $host.'/images/New_Logo_ondgtl.png?v=5';
    }
}
