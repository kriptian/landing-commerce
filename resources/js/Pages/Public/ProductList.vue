<script setup>
import CatalogProductCard from '@/Components/Public/CatalogProductCard.vue';
import CookieConsent from '@/Components/CookieConsent.vue';
import FloatingWhatsAppButton from '@/Components/FloatingWhatsAppButton.vue';
import LoginModal from '@/Components/Public/LoginModal.vue';
import PopupModal from '@/Components/Public/PopupModal.vue';
import RegisterModal from '@/Components/Public/RegisterModal.vue';
import StorefrontHeader from '@/Components/Public/StorefrontHeader.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    products: { type: Object, required: true },
    store: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    hasProductsWithPromo: { type: Boolean, default: false },
    maxProductPromoPercent: { type: Number, default: 0 },
});

const page = usePage();
const toast = useToast();
const search = ref(props.filters.search || '');
const sort = ref(props.filters.sort || 'latest');
const minPrice = ref(props.filters.min_price || '');
const maxPrice = ref(props.filters.max_price || '');
const availability = ref(props.filters.availability || '');
const panel = ref(null);
const showLoginModal = ref(false);
const showRegisterModal = ref(false);
const buyingProductId = ref(null);
const isLoading = ref(false);
const isLoadingMore = ref(false);
const allProducts = ref([...(props.products.data || [])]);
const nextPageUrl = ref(props.products.next_page_url);
const categoryLevels = ref([{ title: 'Categorías', items: props.categories }]);
const loadingCategoryId = ref(null);
const activeHero = ref(0);
let searchTimer;
let skipNextSearch = false;

const customer = computed(() => page.props.customer?.user || null);
const cartCount = computed(() => Number(page.props.cart?.count || 0));
const notificationsCount = computed(() => Number(page.props.customer?.notificationsCount || 0));
const selectedCategoryId = computed(() => Number(props.filters.category || String(props.filters.categories || '').split(',')[0] || 0));
const selectedCategoryName = computed(() => props.filters.selected_category || categoryLevels.value.flatMap(level => level.items).find(category => Number(category.id) === selectedCategoryId.value)?.name);
const catalogTemplate = computed(() => props.store.catalog_use_default ? 'default' : (props.store.catalog_product_template || 'default'));
const hasStorePromo = computed(() => Boolean(props.store.promo_active && Number(props.store.promo_discount_percent) > 0));
const hasAnyPromo = computed(() => hasStorePromo.value || props.hasProductsWithPromo);
const maxPromo = computed(() => hasStorePromo.value ? Number(props.store.promo_discount_percent) : props.maxProductPromoPercent);
const activeFiltersCount = computed(() => [selectedCategoryId.value, minPrice.value, maxPrice.value, availability.value, props.filters.promo].filter(Boolean).length);

const themeStyle = computed(() => {
    const customized = !props.store.catalog_use_default;
    return {
        '--catalog-accent': customized ? (props.store.catalog_button_bg_color || '#2563EB') : '#111827',
        '--catalog-accent-text': customized ? (props.store.catalog_button_text_color || '#FFFFFF') : '#FFFFFF',
        '--catalog-bg': customized ? (props.store.catalog_body_bg_color || '#F8FAFC') : '#F6F7F9',
        '--catalog-text': customized ? (props.store.catalog_body_text_color || '#111827') : '#111827',
        '--catalog-header': customized ? (props.store.catalog_header_bg_color || '#FFFFFF') : '#FFFFFF',
        '--catalog-header-text': customized ? (props.store.catalog_header_text_color || '#111827') : '#111827',
        '--catalog-input': customized ? (props.store.catalog_input_bg_color || '#F8FAFC') : '#F3F4F6',
        '--catalog-input-text': customized ? (props.store.catalog_input_text_color || '#111827') : '#111827',
        '--catalog-promo': customized ? (props.store.catalog_promo_banner_color || '#DC2626') : '#DC2626',
        '--catalog-promo-text': customized ? (props.store.catalog_promo_banner_text_color || '#FFFFFF') : '#FFFFFF',
    };
});

const galleryItems = computed(() => {
    if (props.store.gallery_type === 'custom') return (props.store.gallery_images || []).slice(0, 6);

    const promoted = allProducts.value.filter(product => promotionPercent(product) > 0);
    const featured = allProducts.value.filter(product => product.is_featured && !promoted.some(item => item.id === product.id));
    return [...promoted, ...featured, ...allProducts.value].filter((product, index, items) => items.findIndex(item => item.id === product.id) === index).slice(0, 5);
});

const currentHero = computed(() => galleryItems.value[activeHero.value] || null);
const heroProductId = computed(() => currentHero.value?.product_id || (props.store.gallery_type !== 'custom' ? currentHero.value?.id : null));
const heroImage = computed(() => currentHero.value?.image_url || currentHero.value?.main_image_url || null);
const heroTitle = computed(() => currentHero.value?.title || currentHero.value?.name || props.store.name);
const heroDescription = computed(() => currentHero.value?.description || currentHero.value?.short_description || (maxPromo.value ? `Descubre productos con hasta ${maxPromo.value}% de descuento.` : 'Encuentra algo especial para ti.'));

const socialLinks = computed(() => [
    { label: 'Facebook', href: props.store.facebook_url },
    { label: 'Instagram', href: props.store.instagram_url },
    { label: 'TikTok', href: props.store.tiktok_url },
].filter(link => link.href));

const promotionPercent = product => {
    if (hasStorePromo.value) return Number(props.store.promo_discount_percent);
    return product?.promo_active ? Number(product.promo_discount_percent || 0) : 0;
};

const filterPayload = overrides => ({
    search: search.value || undefined,
    category: selectedCategoryId.value || undefined,
    promo: props.filters.promo || undefined,
    min_price: minPrice.value || undefined,
    max_price: maxPrice.value || undefined,
    availability: availability.value || undefined,
    sort: sort.value !== 'latest' ? sort.value : undefined,
    ...overrides,
});

const visitCatalog = (overrides = {}, options = {}) => {
    router.get(route('catalogo.index', { store: props.store.slug }), filterPayload(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
};

const submitSearch = () => {
    clearTimeout(searchTimer);
    visitCatalog();
};

watch(search, () => {
    if (skipNextSearch) {
        skipNextSearch = false;
        return;
    }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(submitSearch, 500);
});

watch(() => props.products, products => {
    allProducts.value = [...(products.data || [])];
    nextPageUrl.value = products.next_page_url;
}, { deep: true });

watch(() => props.filters, filters => {
    if ((filters.search || '') !== search.value) search.value = filters.search || '';
    sort.value = filters.sort || 'latest';
    minPrice.value = filters.min_price || '';
    maxPrice.value = filters.max_price || '';
    availability.value = filters.availability || '';
}, { deep: true });

watch(galleryItems, items => {
    if (activeHero.value >= items.length) activeHero.value = 0;
});

const chooseCategory = category => {
    panel.value = null;
    visitCatalog({ category: category?.id || undefined, categories: undefined });
};

const openCategories = () => {
    categoryLevels.value = [{ title: 'Categorías', items: props.categories }];
    panel.value = 'categories';
};

const openCategory = async category => {
    if (!category.has_children_with_products || loadingCategoryId.value) return;
    loadingCategoryId.value = category.id;

    try {
        const response = await fetch(route('catalog.categories.children', { store: props.store.slug, category: category.id }), {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('Category request failed');
        const payload = await response.json();
        categoryLevels.value.push({ title: category.name, items: payload.data || [] });
    } catch {
        toast.error('No pudimos cargar las subcategorías. Intenta nuevamente.');
    } finally {
        loadingCategoryId.value = null;
    }
};

const applyAdvancedFilters = () => {
    if (minPrice.value && maxPrice.value && Number(minPrice.value) > Number(maxPrice.value)) {
        toast.error('El precio mínimo no puede superar el máximo.');
        return;
    }
    panel.value = null;
    visitCatalog();
};

const clearFilters = () => {
    skipNextSearch = true;
    search.value = '';
    sort.value = 'latest';
    minPrice.value = '';
    maxPrice.value = '';
    availability.value = '';
    clearTimeout(searchTimer);
    router.get(route('catalogo.index', { store: props.store.slug }), {}, { preserveState: true, replace: true });
};

const showPromotions = () => visitCatalog({ promo: true, category: undefined, categories: undefined });

const loadMore = async () => {
    if (!nextPageUrl.value || isLoadingMore.value) return;
    isLoadingMore.value = true;

    try {
        const response = await fetch(nextPageUrl.value, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Product request failed');
        const payload = await response.json();
        const existingIds = new Set(allProducts.value.map(product => product.id));
        allProducts.value.push(...(payload.data || []).filter(product => !existingIds.has(product.id)));
        nextPageUrl.value = payload.next_page_url;
    } catch {
        toast.error('No pudimos cargar más productos. Intenta nuevamente.');
    } finally {
        isLoadingMore.value = false;
    }
};

const buyProduct = product => {
    if (buyingProductId.value) return;
    if (product.has_variants) {
        router.visit(route('catalogo.show', { store: props.store.slug, product: product.id }));
        return;
    }
    buyingProductId.value = product.id;
    router.post(route('cart.store'), {
        product_id: product.id,
        product_variant_id: null,
        quantity: 1,
    }, {
        preserveScroll: true,
        onSuccess: () => router.visit(route('checkout.index', { store: props.store.slug })),
        onError: errors => toast.error(Object.values(errors)[0] || 'No pudimos agregar el producto.'),
        onFinish: () => { buyingProductId.value = null; },
    });
};

const nextHero = () => {
    if (galleryItems.value.length > 1) activeHero.value = (activeHero.value + 1) % galleryItems.value.length;
};

const previousHero = () => {
    if (galleryItems.value.length > 1) activeHero.value = (activeHero.value - 1 + galleryItems.value.length) % galleryItems.value.length;
};

const removeStartListener = router.on('start', () => { isLoading.value = true; });
const removeFinishListener = router.on('finish', () => { isLoading.value = false; });

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    removeStartListener();
    removeFinishListener();
});
</script>

<template>
    <div class="min-h-screen bg-[var(--catalog-bg)] text-[var(--catalog-text)]" :style="themeStyle">
        <Head :title="`Catálogo de ${store.name}`">
            <meta name="description" :content="`Compra productos de ${store.name}. Explora categorías, promociones y disponibilidad.`">
            <link v-if="store.logo_url" rel="icon" :href="store.logo_url">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        </Head>

        <RegisterModal :show="showRegisterModal" :store="store" @close="showRegisterModal = false" />
        <LoginModal
            :show="showLoginModal"
            :store="store"
            @close="showLoginModal = false"
            @switch-to-register="showLoginModal = false; showRegisterModal = true"
        />
        <PopupModal v-if="store.popup_active" :store="store" @open-register="showRegisterModal = true" />

        <StorefrontHeader
            v-model:search="search"
            :store="store"
            :cart-count="cartCount"
            :customer="customer"
            :notifications-count="notificationsCount"
            :has-promo="hasAnyPromo"
            @submit-search="submitSearch"
            @open-categories="openCategories"
            @open-filters="panel = 'filters'"
            @open-login="showLoginModal = true"
            @open-register="showRegisterModal = true"
        />

        <main class="mx-auto max-w-7xl px-4 pb-20 pt-6 sm:px-6 sm:pt-8 lg:px-8">
            <section v-if="currentHero" class="relative isolate mb-8 overflow-hidden rounded-[2rem] bg-slate-950 text-white shadow-[0_24px_70px_rgba(15,23,42,0.18)] sm:mb-10">
                <div class="absolute inset-0">
                    <video
                        v-if="currentHero.media_type === 'video' && currentHero.video_url"
                        :src="currentHero.video_url"
                        class="h-full w-full object-cover opacity-65"
                        muted
                        playsinline
                        controls
                    />
                    <img v-else-if="heroImage" :src="heroImage" :alt="heroTitle" class="h-full w-full object-cover opacity-60" fetchpriority="high">
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/70 to-transparent" />
                </div>
                <div class="relative flex min-h-[19rem] max-w-2xl flex-col justify-end p-6 sm:min-h-[26rem] sm:p-10 lg:p-14">
                    <p class="mb-3 text-xs font-extrabold uppercase tracking-[0.2em] text-white/70">Selección de {{ store.name }}</p>
                    <h1 class="max-w-xl text-3xl font-extrabold leading-tight tracking-[-0.035em] sm:text-5xl">{{ heroTitle }}</h1>
                    <p class="mt-3 max-w-lg text-sm leading-6 text-white/75 sm:text-base">{{ heroDescription }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            v-if="heroProductId"
                            :href="route('catalogo.show', { store: store.slug, product: heroProductId })"
                            class="inline-flex h-11 items-center rounded-full bg-white px-5 text-sm font-extrabold text-slate-950 transition hover:bg-white/90"
                        >
                            Ver producto
                        </Link>
                        <button v-if="hasAnyPromo" type="button" class="inline-flex h-11 items-center rounded-full border border-white/30 px-5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/10" @click="showPromotions">
                            Ver promociones
                        </button>
                    </div>
                </div>
                <template v-if="galleryItems.length > 1">
                    <button type="button" class="absolute bottom-6 right-16 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur transition hover:bg-black/55" aria-label="Anterior" @click="previousHero">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
                    </button>
                    <button type="button" class="absolute bottom-6 right-5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur transition hover:bg-black/55" aria-label="Siguiente" @click="nextHero">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" /></svg>
                    </button>
                </template>
            </section>

            <section aria-labelledby="catalog-heading">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-slate-400">Catálogo</p>
                        <h2 id="catalog-heading" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                            {{ selectedCategoryName || (props.filters.promo ? 'Productos en promoción' : 'Todos los productos') }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">{{ products.total }} resultado{{ products.total === 1 ? '' : 's' }}</p>
                    </div>
                    <button type="button" class="relative inline-flex h-11 items-center gap-2 rounded-full border border-black/10 bg-white px-4 text-sm font-bold shadow-sm transition hover:bg-slate-50" @click="panel = 'filters'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M7 12h10m-7 6h4" /></svg>
                        Filtrar
                        <span v-if="activeFiltersCount" class="rounded-full bg-[var(--catalog-accent)] px-1.5 text-[11px] text-[var(--catalog-accent-text)]">{{ activeFiltersCount }}</span>
                    </button>
                </div>

                <div class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-2 [scrollbar-width:none] sm:mx-0 sm:px-0">
                    <button type="button" class="shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition" :class="!selectedCategoryId && !props.filters.promo ? 'border-[var(--catalog-accent)] bg-[var(--catalog-accent)] text-[var(--catalog-accent-text)]' : 'border-black/10 bg-white hover:border-black/20'" @click="chooseCategory(null)">
                        Todo
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition"
                        :class="selectedCategoryId === Number(category.id) ? 'border-[var(--catalog-accent)] bg-[var(--catalog-accent)] text-[var(--catalog-accent-text)]' : 'border-black/10 bg-white hover:border-black/20'"
                        @click="chooseCategory(category)"
                    >
                        {{ category.name }} <span class="ml-1 opacity-55">{{ category.products_count }}</span>
                    </button>
                </div>

                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-black/[0.06] bg-white px-4 py-3 shadow-sm">
                    <div class="flex flex-wrap gap-2 text-sm">
                        <span v-if="search" class="rounded-full bg-slate-100 px-3 py-1.5 font-semibold">Búsqueda: “{{ search }}”</span>
                        <span v-if="props.filters.promo" class="rounded-full bg-red-50 px-3 py-1.5 font-semibold text-red-700">Promociones</span>
                        <button v-if="activeFiltersCount || search" type="button" class="px-2 py-1.5 font-bold text-slate-500 underline decoration-slate-300 underline-offset-4 hover:text-slate-900" @click="clearFilters">Limpiar</button>
                        <span v-if="!activeFiltersCount && !search" class="text-slate-500">Explora lo más reciente de la tienda.</span>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <span class="hidden sm:inline">Ordenar</span>
                        <select v-model="sort" class="rounded-full border-slate-200 bg-slate-50 py-2 pl-3 pr-9 text-sm font-bold text-slate-800 focus:border-[var(--catalog-accent)] focus:ring-[var(--catalog-accent)]" @change="visitCatalog()">
                            <option value="latest">Más recientes</option>
                            <option value="name_asc">Nombre A-Z</option>
                            <option value="name_desc">Nombre Z-A</option>
                            <option value="price_asc">Menor precio</option>
                            <option value="price_desc">Mayor precio</option>
                        </select>
                    </label>
                </div>

                <div v-if="isLoading" class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4" aria-label="Cargando productos">
                    <div v-for="item in 8" :key="item" class="animate-pulse overflow-hidden rounded-3xl bg-white">
                        <div class="aspect-[4/5] bg-slate-200" />
                        <div class="space-y-3 p-5"><div class="h-3 w-1/3 rounded bg-slate-200" /><div class="h-5 w-4/5 rounded bg-slate-200" /><div class="h-6 w-1/2 rounded bg-slate-200" /></div>
                    </div>
                </div>

                <div
                    v-else-if="allProducts.length"
                    class="grid gap-3 sm:gap-5"
                    :class="catalogTemplate === 'full_text' ? 'grid-cols-1' : catalogTemplate === 'big' ? 'grid-cols-1 md:grid-cols-2' : 'grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'"
                >
                    <CatalogProductCard
                        v-for="product in allProducts"
                        :key="product.id"
                        :product="product"
                        :store="store"
                        :template="catalogTemplate"
                        :buying="buyingProductId === product.id"
                        @buy="buyProduct"
                    />
                </div>

                <div v-else class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-20 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.3-4.3m1.3-5.2a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    </div>
                    <h3 class="mt-4 text-xl font-extrabold">No encontramos productos</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Prueba otra búsqueda, cambia la categoría o elimina los filtros activos.</p>
                    <button type="button" class="mt-6 rounded-full bg-[var(--catalog-accent)] px-5 py-3 text-sm font-bold text-[var(--catalog-accent-text)]" @click="clearFilters">Ver todo el catálogo</button>
                </div>

                <div v-if="nextPageUrl && !isLoading" class="mt-10 text-center">
                    <button type="button" :disabled="isLoadingMore" class="inline-flex h-12 items-center justify-center rounded-full bg-[var(--catalog-accent)] px-7 text-sm font-extrabold text-[var(--catalog-accent-text)] shadow-sm transition hover:opacity-90 disabled:opacity-50" @click="loadMore">
                        {{ isLoadingMore ? 'Cargando productos...' : 'Mostrar más productos' }}
                    </button>
                </div>
            </section>
        </main>

        <footer class="border-t border-black/[0.07] bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" loading="lazy" class="h-10 w-10 rounded-xl object-cover">
                    <div><p class="font-extrabold text-slate-900">{{ store.name }}</p><p class="text-xs text-slate-400">Tienda creada con OnDigital Solution</p></div>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-slate-500">
                    <a v-for="link in socialLinks" :key="link.label" :href="link.href" target="_blank" rel="noopener noreferrer" class="hover:text-slate-900">{{ link.label }}</a>
                    <Link v-if="store.cookie_consent_active" :href="route('store.privacy', { store: store.slug })" class="hover:text-slate-900">Privacidad</Link>
                </div>
            </div>
        </footer>

        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="panel" class="fixed inset-0 z-[70] bg-slate-950/45 backdrop-blur-sm" @click.self="panel = null">
                <aside class="ml-auto flex h-full w-full max-w-md flex-col bg-white shadow-2xl" role="dialog" aria-modal="true" :aria-label="panel === 'categories' ? 'Categorías' : 'Filtros'">
                    <div class="flex h-16 items-center justify-between border-b px-5">
                        <div class="flex items-center gap-2">
                            <button v-if="panel === 'categories' && categoryLevels.length > 1" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-slate-100" aria-label="Volver" @click="categoryLevels.pop()">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
                            </button>
                            <h2 class="text-lg font-extrabold">{{ panel === 'categories' ? categoryLevels[categoryLevels.length - 1].title : 'Filtrar productos' }}</h2>
                        </div>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-slate-100" aria-label="Cerrar" @click="panel = null">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <div v-if="panel === 'categories'" class="flex-1 overflow-y-auto p-3">
                        <button type="button" class="mb-2 flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left font-bold hover:bg-slate-50" @click="chooseCategory(null)">
                            <span>Todos los productos</span><span class="text-sm text-slate-400">{{ products.total }}</span>
                        </button>
                        <div v-for="category in categoryLevels[categoryLevels.length - 1].items" :key="category.id" class="flex items-center rounded-2xl transition hover:bg-slate-50">
                            <button type="button" class="min-w-0 flex-1 px-4 py-3 text-left" @click="chooseCategory(category)">
                                <span class="block truncate font-bold text-slate-900">{{ category.name }}</span>
                                <span class="text-xs text-slate-400">{{ category.products_count }} producto{{ category.products_count === 1 ? '' : 's' }}</span>
                            </button>
                            <button v-if="category.has_children_with_products" type="button" class="mr-2 inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200" :aria-label="`Ver subcategorías de ${category.name}`" @click="openCategory(category)">
                                <svg v-if="loadingCategoryId !== category.id" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" /></svg>
                                <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-30" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-linecap="round" stroke-width="3" /></svg>
                            </button>
                        </div>
                    </div>

                    <form v-else class="flex flex-1 flex-col" @submit.prevent="applyAdvancedFilters">
                        <div class="flex-1 space-y-7 overflow-y-auto p-5">
                            <fieldset>
                                <legend class="mb-3 text-sm font-extrabold text-slate-900">Disponibilidad</legend>
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 p-4">
                                    <input v-model="availability" type="checkbox" true-value="in_stock" false-value="" class="rounded border-slate-300 text-[var(--catalog-accent)] focus:ring-[var(--catalog-accent)]">
                                    <span><span class="block font-bold text-slate-900">Disponible ahora</span><span class="text-xs text-slate-500">Ocultar productos agotados</span></span>
                                </label>
                            </fieldset>
                            <fieldset>
                                <legend class="mb-3 text-sm font-extrabold text-slate-900">Rango de precio</legend>
                                <div class="grid grid-cols-2 gap-3">
                                    <label><span class="mb-1.5 block text-xs font-bold text-slate-500">Mínimo</span><input v-model="minPrice" type="number" min="0" inputmode="numeric" placeholder="$ 0" class="w-full rounded-2xl border-slate-200 focus:border-[var(--catalog-accent)] focus:ring-[var(--catalog-accent)]"></label>
                                    <label><span class="mb-1.5 block text-xs font-bold text-slate-500">Máximo</span><input v-model="maxPrice" type="number" min="0" inputmode="numeric" placeholder="Sin límite" class="w-full rounded-2xl border-slate-200 focus:border-[var(--catalog-accent)] focus:ring-[var(--catalog-accent)]"></label>
                                </div>
                            </fieldset>
                            <button v-if="hasAnyPromo" type="button" class="flex w-full items-center justify-between rounded-2xl bg-red-50 p-4 text-left text-red-700" @click="panel = null; showPromotions()">
                                <span><span class="block font-extrabold">Ver promociones</span><span class="text-xs">Descuentos de hasta {{ maxPromo }}%</span></span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" /></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-3 border-t p-5">
                            <button type="button" class="h-12 rounded-full border border-slate-200 font-bold text-slate-700" @click="clearFilters(); panel = null">Limpiar</button>
                            <button type="submit" class="h-12 rounded-full bg-[var(--catalog-accent)] font-extrabold text-[var(--catalog-accent-text)]">Aplicar filtros</button>
                        </div>
                    </form>
                </aside>
            </div>
        </Transition>

        <Link :href="route('cart.index', { store: store.slug })" class="fixed bottom-5 right-5 z-30 inline-flex h-14 w-14 items-center justify-center rounded-full bg-[var(--catalog-accent)] text-[var(--catalog-accent-text)] shadow-xl sm:hidden" aria-label="Ver carrito">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4.5h2l1.6 9.1a2 2 0 0 0 2 1.65h7.9a2 2 0 0 0 1.95-1.55L20 7.5H6m3.5 11.25h.01m6.49 0h.01" /></svg>
            <span v-if="cartCount" class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-600 px-1 text-center text-xs font-bold leading-5 text-white">{{ cartCount > 99 ? '99+' : cartCount }}</span>
        </Link>

        <CookieConsent />
        <FloatingWhatsAppButton v-if="store.whatsapp_floating_button_active && store.phone" :phone-number="store.phone" :message="store.whatsapp_floating_button_message" />
    </div>
</template>

<style scoped>
:global(body) {
    font-family: 'Manrope', sans-serif;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
