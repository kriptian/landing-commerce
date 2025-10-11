<script setup>
// Navegación por niveles: cache de hijos y pila de navegación
const childrenCache = ref(new Map()); // parentId -> items
const menuStack = ref([]); // [{ id, name }]
const isLevelLoading = ref(false);
const currentParentId = computed(() => menuStack.value.length ? menuStack.value[menuStack.value.length - 1].id : null);
const currentTitle = computed(() => menuStack.value.length ? menuStack.value[menuStack.value.length - 1].name : 'Categorías');
const currentItems = computed(() => {
  if (!currentParentId.value) return props.categories;
  return childrenCache.value.get(currentParentId.value) || [];
});
const openNode = async (cat) => {
  if (!cat.has_children_with_products) return;
  menuStack.value.push({ id: cat.id, name: cat.name });
  if (!childrenCache.value.has(cat.id)) {
    isLevelLoading.value = true;
    const res = await fetch(route('catalog.categories.children', { store: props.store.slug, category: cat.id }));
    const json = await res.json();
    childrenCache.value.set(cat.id, Array.isArray(json.data) ? json.data : []);
    isLevelLoading.value = false;
  }
};
const goBack = () => { if (menuStack.value.length) menuStack.value.pop(); };
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed, h } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    products: Object,
    store: Object,
    categories: Array,
    filters: Object,
});

const search = ref(props.filters.search);

// --- LÓGICA PARA LA BÚSQUEDA ANIMADA ---
const isSearchActive = ref(false);
const drawerOpen = ref(false);
const expanded = ref({});
// Modo navegación: aplicamos filtro inmediato sin checkboxes/botones
const selected = ref(new Set());
const toggleNode = (cat) => {
  expanded.value[cat.id] = !expanded.value[cat.id];
};
const applyImmediate = (categoryId) => {
  router.get(route('catalogo.index', { store: props.store.slug }), { category: categoryId, search: search.value || undefined }, { preserveState: true, replace: true, preserveScroll: true });
  drawerOpen.value = false;
};
const viewAllInLevel = () => {
  // Toma ids visibles del nivel actual y aplica todos
  const ids = (currentItems.value || []).map(i => i.id);
  router.get(route('catalogo.index', { store: props.store.slug }), { categories: ids.join(',') }, { preserveState: true, replace: true, preserveScroll: true });
  drawerOpen.value = false;
};
const applySearch = () => {
  router.get(route('catalogo.index', { store: props.store.slug }), { search: search.value || undefined, category: props.filters.category || undefined }, { preserveState: true, replace: true, preserveScroll: true });
};
const searchInput = ref(null); // Referencia al input de búsqueda

const toggleSearch = () => {
    isSearchActive.value = !isSearchActive.value;
    if (isSearchActive.value) {
        // nextTick espera a que Vue actualice el DOM (muestre el input)
        // antes de intentar ponerle el foco.
        nextTick(() => {
            searchInput.value.focus();
        });
    } else {
        // Si cerramos la búsqueda, limpiamos el filtro
        search.value = '';
    }
};
// --- FIN LÓGICA ---

// Helpers de promoción: la promo global de tienda tiene prioridad
const hasPromo = (product) => {
  return (props.store?.promo_active && props.store?.promo_discount_percent) || (product.promo_active && product.promo_discount_percent);
};
const promoPercent = (product) => {
  if (props.store?.promo_active && props.store?.promo_discount_percent) return Number(props.store.promo_discount_percent);
  if (product.promo_active && product.promo_discount_percent) return Number(product.promo_discount_percent);
  return 0;
};

// Estado global de promos
const storePromoActive = computed(() => {
    try {
        const percent = Number(props.store?.promo_discount_percent || 0);
        return Boolean(props.store?.promo_active) && percent > 0;
    } catch (e) { return false; }
});
const anyProductPromo = computed(() => {
    try {
        const arr = props.products?.data || [];
        return arr.some(p => Boolean(p?.promo_active) && Number(p?.promo_discount_percent || 0) > 0);
    } catch (e) { return false; }
});
const maxPromoPercent = computed(() => {
    let max = storePromoActive.value ? Number(props.store.promo_discount_percent) : 0;
    try {
        const arr = props.products?.data || [];
        for (const p of arr) {
            const pct = Number(p?.promo_discount_percent || 0);
            if (Boolean(p?.promo_active) && pct > 0) max = Math.max(max, pct);
        }
    } catch (e) {}
    return max;
});
const hasAnyPromoGlobally = computed(() => storePromoActive.value || anyProductPromo.value);
const goToPromo = () => {
    router.get(route('catalogo.index', { store: props.store.slug }), { promo: 1 }, { preserveState: true, replace: true, preserveScroll: true });
    drawerOpen.value = false;
};


watch(search, (value) => {
    setTimeout(() => {
        router.get(route('catalogo.index', { store: props.store.slug }), { search: value }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    }, 500);
});

// Loading state (Inertia navigation)
const isLoading = ref(false);
router.on('start', () => { isLoading.value = true; });
router.on('finish', () => { isLoading.value = false; });

// Al abrir el drawer, reiniciar navegación al nivel raíz
watch(drawerOpen, (open) => {
    if (open) {
        menuStack.value = [];
        isLevelLoading.value = false;
    }
});

// Helpers de stock para badges en cards
const isOutOfStock = (product) => {
    try {
        if (product && product.track_inventory === false) return false;
        const qty = Number(product?.quantity || 0);
        return qty <= 0;
    } catch (e) { return false; }
};

const isLowStock = (product) => {
    try {
        if (product && product.track_inventory === false) return false;
        const qty = Number(product?.quantity || 0);
        const alert = Number(product?.alert || 0);
        if (qty <= 0) return false;
        if (alert <= 0) return false;
        return qty <= alert;
    } catch (e) { return false; }
};

// (El botón de acción en la tarjeta redirige directamente al detalle del producto)

// FAB de redes sociales (móvil)
const showSocialFab = ref(false);
const hasAnySocial = computed(() => {
    try {
        const phone = (props.store?.phone ?? '').toString().replace(/[^0-9]/g, '');
        return Boolean(props.store?.facebook_url || props.store?.instagram_url || props.store?.tiktok_url || phone);
    } catch (e) {
        return false;
    }
});

const socialLinks = computed(() => {
    const links = [];
    const s = props.store || {};
    const fb = (s.facebook_url ?? '').toString().trim();
    const ig = (s.instagram_url ?? '').toString().trim();
    const tt = (s.tiktok_url ?? s.tiktok ?? s.tik_tok_url ?? '').toString().trim();
    const phone = (s.phone ?? '').toString().replace(/[^0-9]/g, '');
    if (fb) links.push({ key: 'fb', href: fb, iconClass: 'text-blue-500' });
    if (ig) links.push({ key: 'ig', href: ig, iconClass: 'text-pink-500' });
    if (tt) links.push({ key: 'tt', href: tt, iconClass: 'text-black' });
    if (phone) links.push({ key: 'wa', href: `https://wa.me/${phone}` , iconClass: 'text-green-500' });
    return links;
});

const fabItems = computed(() => {
    const links = socialLinks.value;
    const spacing = 66; // px entre burbujas
    return links.map((l, i) => ({
        ...l,
        style: `transform: translate(0, -${spacing * (i + 1)}px)`,
    }));
});
</script>

<template>
    <Head :title="`Catálogo de ${store.name}`">
        <template #default>
            <link v-if="store.logo_url" rel="icon" type="image/png" :href="store.logo_url">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        </template>
    </Head>

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover">
                <h1 class="truncate text-lg sm:text-2xl font-bold text-gray-900" :class="{'text-base': store.name && store.name.length > 22, 'text-sm': store.name && store.name.length > 32}">{{ store.name }}</h1>
            </div>
            <div class="flex items-center space-x-3 shrink-0">
            </div>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-12">
        <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight leading-tight mb-4 bg-clip-text text-transparent bg-gradient-to-b from-gray-900 to-gray-600" style="font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif;">Nuestro Catálogo</h2>

        <div class="mb-4 flex items-center justify-between">
            <button @click="drawerOpen = true" class="p-2 rounded border hover:bg-gray-100" aria-label="Abrir filtros">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            </button>
            <div class="flex items-center gap-2">
                <input ref="searchInput" v-model="search" type="text" placeholder="Buscar..." class="border rounded px-3 py-2 text-sm" />
                <button @click="applySearch" class="px-3 py-2 rounded border hover:bg-gray-100">Buscar</button>
            </div>
        </div>

		<!-- Cinta de ofertas (animada) -->
		<div v-if="hasAnyPromoGlobally" class="mb-4">
			<button @click="goToPromo" class="promo-ribbon w-full rounded-xl py-3 shadow-lg ring-1 ring-red-400/40 hover:ring-red-300 transition">
				<div class="marquee">
					<div class="marquee__inner text-white font-extrabold uppercase tracking-wider text-sm sm:text-base">
						<span class="flex items-center gap-2">
							<span class="blink">🔥</span>
							Ofertas hasta {{ maxPromoPercent }}%
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
						<span class="flex items-center gap-2">
							<span class="blink">🔥</span>
							Ofertas hasta {{ maxPromoPercent }}%
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
						<span class="flex items-center gap-2">
							<span class="blink">🔥</span>
							Ofertas hasta {{ maxPromoPercent }}%
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
					</div>
				</div>
			</button>
		</div>

        <transition name="fade-slide">
            <div v-if="drawerOpen" class="fixed inset-0 z-50 flex">
                <div class="relative w-80 max-w-[90%] bg-white h-full shadow-2xl">
                    <div class="sticky top-0 z-10 bg-white/95 backdrop-blur border-b px-4 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button v-if="menuStack.length" @click="goBack" class="p-2 rounded hover:bg-gray-100" aria-label="Volver">‹</button>
                            <h3 class="font-semibold text-gray-900">{{ currentTitle }}</h3>
                        </div>
                        <button @click="drawerOpen=false" class="p-2 rounded hover:bg-gray-100" aria-label="Cerrar">✖</button>
                    </div>
                    <div class="px-2 py-2 overflow-y-auto h-[calc(100%-56px)]">
                        <div class="px-3 pb-2 flex items-center justify-between">
                            <div class="text-sm font-semibold text-gray-800">{{ currentTitle }}</div>
                            <button @click="viewAllInLevel" class="text-xs font-semibold text-blue-600 hover:underline">Ver todo</button>
                        </div>
                        <!-- Entrada destacada de Promoción -->
                        <div v-if="hasAnyPromoGlobally" class="mb-1">
                            <button class="w-full flex items-center justify-between rounded-lg px-3 py-2 bg-red-50 hover:bg-red-100 active:scale-[.99] transition border border-red-200" @click="goToPromo">
                                <span class="font-bold text-red-700 uppercase">Promociones</span>
                                <span class="text-xs bg-red-600 text-white rounded-full px-2 py-0.5">Hasta {{ maxPromoPercent }}%</span>
                            </button>
                        </div>
                        <div v-if="isLevelLoading" class="px-2 py-2 text-sm text-gray-500">Cargando...</div>
                        <div v-for="cat in currentItems" :key="cat.id" class="mb-1">
                            <button class="w-full flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50 active:scale-[.99] transition" @click="cat.has_children_with_products ? openNode(cat) : applyImmediate(cat.id)">
                                <span class="font-medium text-gray-800">{{ cat.name }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs bg-gray-100 text-gray-700 rounded-full px-2 py-0.5">{{ cat.products_count }}</span>
                                    <span v-if="cat.has_children_with_products" class="w-4 h-4 inline-flex items-center justify-center">›</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex-1 bg-black/40" @click="drawerOpen=false"></div>
            </div>
        </transition>

        <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
            <div v-for="i in 6" :key="i" class="animate-pulse border rounded-xl shadow-sm overflow-hidden bg-white">
                <div class="w-full h-40 sm:h-48 md:h-56 bg-gray-200"></div>
                <div class="p-4 space-y-3">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-6 bg-gray-200 rounded w-1/3"></div>
                    <div class="h-10 bg-gray-200 rounded w-full"></div>
                </div>
            </div>
        </div>

		<div v-else-if="products.data.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
			<Link v-for="product in products.data" :key="product.id" :href="route('catalogo.show', { store: store.slug, product: product.id })" class="group block border rounded-xl shadow-sm overflow-hidden bg-white hover:shadow-md transition">
				<div class="relative">
					<img v-if="product.main_image_url" :src="product.main_image_url" alt="Imagen del producto" class="w-full h-40 sm:h-48 md:h-56 object-cover transform group-hover:scale-105 transition duration-300">
					<span v-if="isOutOfStock(product)" class="absolute top-3 left-3 bg-red-600 text-white text-xs font-semibold px-2 py-1 rounded">Agotado</span>
					<span v-else-if="isLowStock(product)" class="absolute top-3 left-3 bg-yellow-500 text-white text-xs font-semibold px-2 py-1 rounded">¡Pocas unidades!</span>
				</div>
				<div class="p-4 flex flex-col gap-3">
					<h3 class="text-base sm:text-lg font-semibold text-gray-900 line-clamp-2">{{ product.name }}</h3>
					<div class="flex items-center gap-2">
						<p class="text-lg sm:text-xl text-gray-900 font-extrabold">
							{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
								hasPromo(product) ? Math.round(product.price * (100 - promoPercent(product)) / 100) : product.price
							) }}
						</p>
						<span v-if="hasPromo(product)" class="inline-flex items-center rounded bg-red-600 text-white font-bold px-1.5 py-0.5 text-[11px] sm:text-xs">
							-{{ promoPercent(product) }}%
						</span>
					</div>
					<p v-if="hasPromo(product)" class="text-xs sm:text-sm text-gray-400 line-through">
						{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(product.price) }}
					</p>
				</div>
			</Link>
		</div>
        <div v-else>
            <div class="text-center py-16">
                <p class="text-xl font-semibold text-gray-700">No se encontraron productos</p>
                <p class="text-gray-500 mt-2">Intenta con otra búsqueda o categoría.</p>
                <Link :href="route('catalogo.index', { store: store.slug })" class="mt-4 inline-block text-blue-600 hover:underline font-semibold">
                    Limpiar filtros
                </Link>
            </div>
        </div>
        
        <Pagination v-if="products.data.length > 0" class="mt-8" :links="products.links" />

    </main>

    <footer class="bg-white mt-16 border-t">
        <div class="container mx-auto px-6 py-4 text-center text-gray-500">
            <p>&copy; 2025 {{ store.name }}</p>
        </div>
    </footer>

    <!-- FAB Social (todas las vistas, móvil y desktop) -->
    <div v-if="hasAnySocial" class="fixed bottom-6 left-6 z-50">
        <div class="relative">
            <!-- Burbujas -->
            <transition name="fade">
                <div v-if="showSocialFab" class="absolute right-0 bottom-0 flex flex-col items-end gap-3 -translate-y-14 z-10">
<a v-for="item in socialLinks" :key="item.key" :href="item.href" target="_blank" class="w-11 h-11 rounded-full bg-white/70 backdrop-blur ring-1 ring-blue-500/50 flex items-center justify-center shadow-2xl active:scale-95">
                        <svg v-if="item.key==='fb'" class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
                        <svg v-else-if="item.key==='ig'" class="w-5 h-5 text-pink-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.012-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.08 2.525c.636-.247 1.363-.416 2.427-.465C9.53 2.013 9.884 2 12.315 2zm-1.161 1.545a1.12 1.12 0 10-1.584 1.584 1.12 1.12 0 001.584-1.584zm-3.097 3.569a3.468 3.468 0 106.937 0 3.468 3.468 0 00-6.937 0z" clip-rule="evenodd" /><path d="M12 6.166a5.834 5.834 0 100 11.668 5.834 5.834 0 000-11.668zm0 1.545a4.289 4.289 0 110 8.578 4.289 4.289 0 010-8.578z" /></svg>
                        <svg v-else-if="item.key==='tt'" class="w-5 h-5 text-black" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.01-1.58-.31-3.15-.82-4.7-.52-1.56-1.23-3.04-2.1-4.42a.1.1 0 00-.2-.04c-.02.13-.03.26-.05.39v7.24a.26.26 0 00.27.27c.82.04 1.63.16 2.42.37.04.83.16 1.66.36 2.47.19.82.49 1.6.86 2.33.36.73.81 1.41 1.32 2.02-.17.1-.34.19-.51.28a4.26 4.26 0 01-1.93.52c-1.37.04-2.73-.06-4.1-.23a9.8 9.8 0 01-3.49-1.26c-.96-.54-1.8-1.23-2.52-2.03-.72-.8-1.3-1.7-1.77-2.69-.47-.99-.8-2.06-1.02-3.13a.15.15 0 01.04-.15.24.24 0 01.2-.09c.64-.02 1.28-.04 1.92-.05 .1 0 .19-.01 .28-.01 .07 .01 .13 .02 .2 .04 .19 .04 .38 .09 .57 .14a5.2 5.2 0 005.02-5.22v-.02a.23 .23 0 00-.23-.23 .2 .2 0 00-.2-.02c-.83-.06-1.66-.13-2.49-.22-.05-.01-.1-.01-.15-.02-1.12-.13-2.25-.26-3.37-.44a.2 .2 0 01-.16-.24 .22 .22 0 01.23-.18c.41-.06 .82-.12 1.23-.18C9.9 .01 11.21 0 12.525 .02z"/></svg>
                        <svg v-else-if="item.key==='wa'" class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74.97.99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64 .15-.19 .29-.74 .94-.9 1.13-.17 .19-.33 .22-.62 .07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45 .13-.6 .13-.13 .29-.33 .43-.5 .15-.17 .19-.29 .29-.48 .1-.19 .05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0 -.5.07-.76 .36-.26 .29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15 .19 2 3.06 4.84 4.29 .68 .29 1.21 .46 1.62 .59 .68 .22 1.3 .19 1.79 .12 .55-.08 1.7-.7 1.94-1.38 .24-.68 .24-1.26 .17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                        <svg v-else class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM13.5 19a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z"/></svg>
                    </a>
                </div>
            </transition>

            <!-- Trigger -->
            <button @click="showSocialFab = !showSocialFab" class="w-12 h-12 rounded-full bg-blue-600/70 backdrop-blur ring-1 ring-blue-500/50 text-white flex items-center justify-center shadow-2xl active:scale-95 transition-transform duration-300" :class="{ 'scale-95': showSocialFab }">
                <svg v-if="!showSocialFab" class="w-6 h-6 transition-opacity duration-200" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2c-3.18-.35-6.2-1.63-8.82-3.68a19.86 19.86 0 0 1-6.24-6.24C2.7 9.38 1.42 6.36 1.07 3.18A2 2 0 0 1 3.06 1h3a2 2 0 0 1 2 1.72c.09.74.25 1.46.46 2.16a2 2 0 0 1-.45 2.06L7.5 8.5a16 16 0 0 0 8 8l1.56-1.57a2 2 0 0 1 2.06-.45c.7.21 1.42.37 2.16.46A2 2 0 0 1 22 16.92z"/>
                </svg>
                <svg v-else class="w-6 h-6 transition-opacity duration-200" viewBox="0 0 24 24" fill="currentColor"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>
    </div>

    <!-- FAB Carrito (móvil y desktop) -->
    <div class="fixed bottom-6 right-6 z-50">
        <Link :href="route('cart.index', { store: store.slug })" class="relative w-12 h-12 rounded-full bg-blue-600/70 backdrop-blur ring-1 ring-blue-500/50 text-white flex items-center justify-center shadow-2xl active:scale-95">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
            <span v-if="$page.props.cart.count > 0" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                {{ $page.props.cart.count }}
            </span>
    </Link>
    </div>
</template>

<style scoped>
/* Cinta con gradiente y shimmer */
.promo-ribbon {
	background: linear-gradient(90deg, #ef4444, #dc2626);
	position: relative;
	overflow: hidden;
}
.promo-ribbon::before {
	content: '';
	position: absolute;
	top: 0;
	left: -50%;
	width: 50%;
	height: 100%;
	background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
	transform: skewX(-20deg);
	animation: shimmer 2.2s infinite;
}
@keyframes shimmer {
	0% { left: -60%; }
	60% { left: 120%; }
	100% { left: 120%; }
}

/* Marquee horizontal */
.marquee {
	position: relative;
	overflow: hidden;
	width: 100%;
}
.marquee__inner {
	display: inline-flex;
	gap: 2rem;
	white-space: nowrap;
	animation: marquee 12s linear infinite;
}
@keyframes marquee {
	0%   { transform: translateX(0); }
	100% { transform: translateX(-50%); }
}

/* Parpadeo suave para el ícono */
.blink { animation: blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
</style>