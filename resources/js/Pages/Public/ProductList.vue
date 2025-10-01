<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed } from 'vue';
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

// Helper: bajo stock (sólo si existe umbral > 0)
const isLowStock = (product) => {
    try {
        if (product && product.track_inventory === false) return false;
        // Con variantes: si alguna variante tiene stock > 0 y existe umbral > 0, y stock <= umbral
        if (product?.variants?.length > 0) {
            return product.variants.some((v) => {
                const stock = Number(v?.stock || 0);
                const alert = Number(v?.alert) || 0;
                if (alert <= 0) return false;
                return stock > 0 && stock <= alert;
            });
        }
        // Sin variantes: usamos quantity y alert del producto
        const qty = Number(product?.quantity || 0);
        const alert = Number(product?.alert) || 0;
        if (alert <= 0) return false;
        return qty > 0 && qty <= alert;
    } catch (e) {
        return false;
    }
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
    <Head :title="`Catálogo de ${store.name}`" />

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
        <h2 class="text-3xl font-bold mb-4">Nuestro Catálogo</h2>

        <div class="mb-8 p-4 bg-gray-50 rounded-lg">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                
                <div class="flex-grow">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filtrar por categoría</label>
                    <Dropdown align="left" width="64">
                        <template #trigger>
                            <button type="button" class="inline-flex justify-between items-center w-full md:w-auto rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">
                                <span>{{ filters.category ? categories.find(c => c.id == filters.category).name : 'Todas las categorías' }}</span>
                                <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('catalogo.index', { store: store.slug })"> Todas </DropdownLink>
                            <DropdownLink v-for="category in categories" :key="category.id" :href="route('catalogo.index', { store: store.slug, category: category.id })">
                                {{ category.name }}
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
                
                <div class="flex items-center space-x-2">
                    <input 
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        placeholder="Buscar..."
                        class="block h-10 rounded-md shadow-sm transition-all duration-300 ease-in-out border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                        :class="{ 'w-48 sm:w-64 px-2 opacity-100': isSearchActive, 'w-0 p-0 border-transparent opacity-0': !isSearchActive }"
                    />
                    <button @click="toggleSearch" type="button" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                        <svg v-if="isSearchActive" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        <svg v-else class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </button>
                </div>

            </div>
        </div>

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
            <div v-for="product in products.data" :key="product.id" class="group border rounded-xl shadow-sm overflow-hidden bg-white hover:shadow-md transition">
                <div class="relative">
                    <img v-if="product.main_image_url" :src="product.main_image_url" alt="Imagen del producto" class="w-full h-40 sm:h-48 md:h-56 object-cover transform group-hover:scale-105 transition duration-300">
                    <!-- Solo banda diagonal (sin remate de esquina). La promo global de tienda tiene prioridad. -->
                    <div v-if="hasPromo(product)"
                         class="pointer-events-none select-none absolute inset-0 z-10">
                        <div class="absolute -left-14 top-4 -rotate-45 w-64 sm:w-72 bg-red-600 text-white uppercase font-extrabold tracking-wide text-[12px] sm:text-sm text-center py-1 px-4 shadow-lg flex items-center justify-center whitespace-nowrap">
                            PROMO {{ promoPercent(product) }}%
                        </div>
                    </div>
                    <span v-if="(product.track_inventory !== false) && ((product.variants ? product.variants.length === 0 : true) && Number(product.quantity || 0) === 0)"
                          class="absolute top-3 left-3 bg-red-600 text-white text-xs font-semibold px-2 py-1 rounded">
                        Agotado
                    </span>
                    <span v-else-if="isLowStock(product)"
                          class="absolute top-3 left-3 bg-yellow-500 text-white text-xs font-semibold px-2 py-1 rounded">
                        ¡Pocas unidades!
                    </span>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 line-clamp-2">{{ product.name }}</h3>
                    <div class="flex items-baseline gap-2">
                        <p class="text-lg sm:text-xl text-blue-600 font-extrabold">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
                                hasPromo(product) ? Math.round(product.price * (100 - promoPercent(product)) / 100) : product.price
                            ) }}
                        </p>
                        <p v-if="hasPromo(product)" class="text-sm text-gray-400 line-through">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(product.price) }}
                        </p>
                    </div>
                    <div class="mt-1">
                        <Link :href="route('catalogo.show', { store: store.slug, product: product.id })" class="inline-flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-semibold py-2.5 px-4 rounded-lg text-center hover:bg-blue-700">
                            Ver
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M13.5 4.5a.75.75 0 01.75-.75h6a.75.75 0 01.75.75v6a.75.75 0 01-1.5 0V6.31l-7.22 7.22a.75.75 0 11-1.06-1.06L18.44 5.25h-3.44a.75.75 0 01-.75-.75z"/><path d="M6 3.75A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25h12A2.25 2.25 0 0020.25 18v-5.25a.75.75 0 00-1.5 0V18c0 .414-.336.75-.75.75H6A.75.75 0 015.25 18V6c0-.414.336-.75.75-.75h5.25a.75.75 0 000-1.5H6z"/></svg>
                        </Link>
                    </div>
                </div>
            </div>
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
                    <a v-for="item in socialLinks" :key="item.key" :href="item.href" target="_blank" class="w-11 h-11 rounded-full bg-white/70 backdrop-blur ring-1 ring-rose-500/50 flex items-center justify-center shadow-2xl active:scale-95">
                        <svg v-if="item.key==='fb'" class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
                        <svg v-else-if="item.key==='ig'" class="w-5 h-5 text-pink-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.012-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.08 2.525c.636-.247 1.363-.416 2.427-.465C9.53 2.013 9.884 2 12.315 2zm-1.161 1.545a1.12 1.12 0 10-1.584 1.584 1.12 1.12 0 001.584-1.584zm-3.097 3.569a3.468 3.468 0 106.937 0 3.468 3.468 0 00-6.937 0z" clip-rule="evenodd" /><path d="M12 6.166a5.834 5.834 0 100 11.668 5.834 5.834 0 000-11.668zm0 1.545a4.289 4.289 0 110 8.578 4.289 4.289 0 010-8.578z" /></svg>
                        <svg v-else-if="item.key==='tt'" class="w-5 h-5 text-black" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.01-1.58-.31-3.15-.82-4.7-.52-1.56-1.23-3.04-2.1-4.42a.1.1 0 00-.2-.04c-.02.13-.03.26-.05.39v7.24a.26.26 0 00.27.27c.82.04 1.63.16 2.42.37.04.83.16 1.66.36 2.47.19.82.49 1.6.86 2.33.36.73.81 1.41 1.32 2.02-.17.1-.34.19-.51.28a4.26 4.26 0 01-1.93.52c-1.37.04-2.73-.06-4.1-.23a9.8 9.8 0 01-3.49-1.26c-.96-.54-1.8-1.23-2.52-2.03-.72-.8-1.3-1.7-1.77-2.69-.47-.99-.8-2.06-1.02-3.13a.15.15 0 01.04-.15.24.24 0 01.2-.09c.64-.02 1.28-.04 1.92-.05 .1 0 .19-.01 .28-.01 .07 .01 .13 .02 .2 .04 .19 .04 .38 .09 .57 .14a5.2 5.2 0 005.02-5.22v-.02a.23 .23 0 00-.23-.23 .2 .2 0 00-.2-.02c-.83-.06-1.66-.13-2.49-.22-.05-.01-.1-.01-.15-.02-1.12-.13-2.25-.26-3.37-.44a.2 .2 0 01-.16-.24 .22 .22 0 01.23-.18c.41-.06 .82-.12 1.23-.18C9.9 .01 11.21 0 12.525 .02z"/></svg>
                        <svg v-else-if="item.key==='wa'" class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74.97.99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64 .15-.19 .29-.74 .94-.9 1.13-.17 .19-.33 .22-.62 .07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45 .13-.6 .13-.13 .29-.33 .43-.5 .15-.17 .19-.29 .29-.48 .1-.19 .05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0 -.5.07-.76 .36-.26 .29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15 .19 2 3.06 4.84 4.29 .68 .29 1.21 .46 1.62 .59 .68 .22 1.3 .19 1.79 .12 .55-.08 1.7-.7 1.94-1.38 .24-.68 .24-1.26 .17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                        <svg v-else class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM13.5 19a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z"/></svg>
                    </a>
                </div>
            </transition>

            <!-- Trigger -->
            <button @click="showSocialFab = !showSocialFab" class="w-12 h-12 rounded-full bg-rose-600/70 backdrop-blur ring-1 ring-rose-500/50 text-white flex items-center justify-center shadow-2xl active:scale-95 transition-transform duration-300" :class="{ 'rotate-90': showSocialFab }">
                <svg v-if="!showSocialFab" class="w-6 h-6 transition-opacity duration-200" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM13.5 19a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z"/></svg>
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