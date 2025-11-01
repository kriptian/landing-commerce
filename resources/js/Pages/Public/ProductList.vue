<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed, h, onMounted, onBeforeUnmount } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Pagination from '@/Components/Pagination.vue';

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

const handleSearchBlur = () => {
    if (!search.value) {
        toggleSearch();
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

// Al abrir el drawer, reiniciar navegación al nivel raíz y forzar re-render para animación
const drawerItemsKey = ref(0);
const showMenuItems = ref(false);

watch(drawerOpen, (open) => {
    if (open) {
        menuStack.value = [];
        isLevelLoading.value = false;
        showMenuItems.value = false;
        // Forzar re-render y luego mostrar items con animación
        drawerItemsKey.value++;
        nextTick(() => {
            // Pequeño delay para que el DOM esté listo
            setTimeout(() => {
                showMenuItems.value = true;
            }, 50);
        });
    } else {
        showMenuItems.value = false;
    }
});

watch(currentItems, () => {
    // Cada vez que cambian los items (navegación entre niveles), forzar re-render
    if (drawerOpen.value) {
        showMenuItems.value = false;
        drawerItemsKey.value++;
        nextTick(() => {
            setTimeout(() => {
                showMenuItems.value = true;
            }, 50);
        });
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

// Galería de productos destacados con transición
const featuredProducts = computed(() => {
    const allProducts = props.products?.data || [];
    // Tomamos los primeros 5 productos o los que tienen promoción
    const featured = allProducts.filter(p => hasPromo(p)).slice(0, 5);
    // Si no hay suficientes con promoción, completamos con los primeros productos
    if (featured.length < 5) {
        const remaining = allProducts.filter(p => !featured.some(fp => fp.id === p.id)).slice(0, 5 - featured.length);
        return [...featured, ...remaining];
    }
    return featured.slice(0, 5);
});

const currentSlide = ref(0);
const autoPlayInterval = ref(null);

// Estado para el swipe/drag manual
const galleryContainer = ref(null);
const isDragging = ref(false);
const dragStart = ref(0);
const dragCurrent = ref(0);
const dragOffset = ref(0);

const nextSlide = () => {
    if (featuredProducts.value.length > 0 && !isDragging.value) {
        currentSlide.value = (currentSlide.value + 1) % featuredProducts.value.length;
    }
};

const prevSlide = () => {
    if (featuredProducts.value.length > 0 && !isDragging.value) {
        currentSlide.value = currentSlide.value === 0 ? featuredProducts.value.length - 1 : currentSlide.value - 1;
    }
};

const goToSlide = (index) => {
    currentSlide.value = index;
    resetAutoPlay();
};

const resetAutoPlay = () => {
    if (autoPlayInterval.value) {
        clearInterval(autoPlayInterval.value);
    }
    if (featuredProducts.value.length > 1 && !isDragging.value) {
        autoPlayInterval.value = setInterval(nextSlide, 3000); // Cambia cada 3 segundos
    }
};

// Funciones para el swipe/drag manual
const handleDragStart = (e) => {
    // Ignorar si es un clic en el botón (excepto en móvil donde sí queremos arrastrar)
    if (e.target.closest('button') && window.innerWidth >= 768) return;
    
    isDragging.value = true;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    dragStart.value = clientX;
    dragCurrent.value = clientX;
    dragOffset.value = 0;
    
    // Pausar autoplay mientras se arrastra
    if (autoPlayInterval.value) {
        clearInterval(autoPlayInterval.value);
    }
};

const handleDragMove = (e) => {
    if (!isDragging.value) return;
    
    e.preventDefault();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    dragCurrent.value = clientX;
    dragOffset.value = dragCurrent.value - dragStart.value;
};

const handleDragEnd = (e) => {
    if (!isDragging.value) return;
    
    // Ignorar si fue un clic (no hubo movimiento significativo)
    if (Math.abs(dragOffset.value) < 5) {
        isDragging.value = false;
        dragStart.value = 0;
        dragCurrent.value = 0;
        dragOffset.value = 0;
        resetAutoPlay();
        return;
    }
    
    const threshold = 50; // Mínimo de píxeles para cambiar de slide
    const offset = dragOffset.value;
    
    if (Math.abs(offset) > threshold) {
        if (offset > 0) {
            // Arrastró hacia la derecha, ir al slide anterior
            prevSlide();
        } else {
            // Arrastró hacia la izquierda, ir al slide siguiente
            nextSlide();
        }
    }
    
    // Resetear estado
    isDragging.value = false;
    dragStart.value = 0;
    dragCurrent.value = 0;
    dragOffset.value = 0;
    
    // Reanudar autoplay después de un pequeño delay
    setTimeout(() => {
        resetAutoPlay();
    }, 300);
};

const buyNowFromGallery = (product) => {
    router.post(route('cart.store'), {
        product_id: product.id,
        product_variant_id: null,
        quantity: 1,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Redirigir directamente al checkout después de agregar al carrito
            router.visit(route('checkout.index', { store: props.store.slug }), {
                preserveScroll: false,
            });
        }
    });
};

// Iniciar autoplay al montar
onMounted(() => {
    resetAutoPlay();
});

onBeforeUnmount(() => {
    if (autoPlayInterval.value) {
        clearInterval(autoPlayInterval.value);
    }
});

// Reset autoplay cuando cambian los productos destacados
watch(featuredProducts, () => {
    resetAutoPlay();
});
</script>

<template>
    <Head :title="`Catálogo de ${store.name}`">
        <template #default>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        </template>
    </Head>

    <!-- Cinta de ofertas arriba del todo (fixed) -->
    <div v-if="hasAnyPromoGlobally" class="fixed top-0 left-0 right-0 z-[60] bg-red-600/60 backdrop-blur-sm">
        <button @click="goToPromo" class="w-full py-2 sm:py-3 shadow-lg hover:bg-red-600/70 transition-all">
				<div class="marquee">
                <div class="marquee__inner text-white font-extrabold uppercase tracking-wider text-xs sm:text-sm">
                    <span class="flex items-center gap-2 whitespace-nowrap">
							<span class="blink">🔥</span>
							Ofertas hasta {{ maxPromoPercent }}%
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
                    <span class="flex items-center gap-2 whitespace-nowrap">
							<span class="blink">🔥</span>
							Ofertas hasta {{ maxPromoPercent }}%
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
                    <span class="flex items-center gap-2 whitespace-nowrap">
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

    <header class="bg-white shadow-sm sticky z-50" :class="hasAnyPromoGlobally ? 'top-[44px] sm:top-[52px]' : 'top-0'">
        <nav class="container mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-2 relative">
            <!-- Menú hamburguesa - siempre visible -->
            <button @click="drawerOpen = true" class="p-2 rounded-lg hover:bg-gray-100 transition-colors z-10 flex-shrink-0" aria-label="Abrir menú">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-gray-700">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                </svg>
            </button>

            <!-- Logo centrado -->
            <div class="absolute left-1/2 flex items-center justify-center z-10 transition-all duration-300 ease-out" :class="isSearchActive ? 'opacity-0 scale-75 pointer-events-none' : 'opacity-100 scale-100 pointer-events-auto'" :style="isSearchActive ? 'transform: translate(-50%, 0) translateX(-100px)' : 'transform: translate(-50%, 0)'">
                <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-12 w-12 sm:h-14 sm:w-14 md:h-16 md:w-16 rounded-full object-cover ring-2 ring-gray-100 shadow-sm">
            </div>

            <!-- Lupa / Búsqueda expandible -->
            <div class="flex items-center justify-end flex-shrink-0 z-10">
                <transition name="search-expand">
                    <div v-if="!isSearchActive" class="flex items-center">
                        <button @click="toggleSearch" class="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Buscar">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-gray-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                            </svg>
                        </button>
                    </div>
                    <div v-else class="flex items-center gap-2 min-w-[200px] sm:min-w-[280px]">
                        <input 
                            ref="searchInput" 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar..." 
                            @blur="handleSearchBlur"
                            class="border border-gray-300 rounded-lg px-4 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300" 
                        />
                        <button @click="toggleSearch" class="p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0" aria-label="Cerrar búsqueda">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </transition>
            </div>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-12" :class="hasAnyPromoGlobally ? 'pt-16 sm:pt-20' : 'pt-12'">

		<!-- Galería de productos destacados con transición -->
		<div 
			v-if="featuredProducts.length > 0 && !isSearchActive && !search" 
			ref="galleryContainer"
			class="mb-8 relative overflow-hidden rounded-2xl shadow-xl md:cursor-default cursor-grab active:cursor-grabbing"
			@touchstart="handleDragStart"
			@touchmove="handleDragMove"
			@touchend="handleDragEnd"
			@mousedown="handleDragStart"
			@mousemove="handleDragMove"
			@mouseup="handleDragEnd"
			@mouseleave="handleDragEnd"
		>
			<!-- Flechas de navegación solo para desktop -->
			<button 
				v-if="featuredProducts.length > 1"
				@click="prevSlide"
				class="hidden md:flex absolute left-4 top-1/2 -translate-y-1/2 z-10 bg-white/90 backdrop-blur-sm hover:bg-white text-gray-800 p-3 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-110 active:scale-95"
				aria-label="Anterior"
			>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
					<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
				</svg>
			</button>
			<button 
				v-if="featuredProducts.length > 1"
				@click="nextSlide"
				class="hidden md:flex absolute right-4 top-1/2 -translate-y-1/2 z-10 bg-white/90 backdrop-blur-sm hover:bg-white text-gray-800 p-3 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-110 active:scale-95"
				aria-label="Siguiente"
			>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
					<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
				</svg>
			</button>
			<div class="relative h-[300px] sm:h-[400px] md:h-[500px]">
				<!-- Slides -->
				<div class="relative h-full overflow-hidden">
					<transition name="slide-fade" mode="out-in">
						<div 
							v-if="featuredProducts[currentSlide]"
							:key="`${featuredProducts[currentSlide].id}-${currentSlide}`"
							class="absolute inset-0 gallery-slide"
							:style="isDragging ? { 
								transform: `translateX(${dragOffset}px)`,
								transition: 'none'
							} : {}"
						>
						<div v-if="featuredProducts[currentSlide]" class="relative h-full bg-white/90 backdrop-blur-sm overflow-hidden">
							<!-- Imagen del producto -->
							<div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6 md:p-8">
								<img 
									v-if="featuredProducts[currentSlide].main_image_url" 
									:src="featuredProducts[currentSlide].main_image_url" 
									:alt="featuredProducts[currentSlide].name"
									class="w-full h-full object-cover object-center"
								>
							</div>
							
							<!-- Overlay con información y botón -->
							<div class="absolute inset-0 flex flex-col justify-between p-4 sm:p-6 md:p-8 pointer-events-none">
								<!-- Título del producto -->
								<div class="text-gray-900 bg-white/70 backdrop-blur-sm rounded-lg px-4 py-3 max-w-full pointer-events-auto">
									<h3 class="text-xl sm:text-2xl md:text-3xl font-bold mb-2 truncate">{{ featuredProducts[currentSlide].name }}</h3>
									<div class="flex items-center gap-2 sm:gap-3 flex-wrap">
										<p class="text-lg sm:text-xl md:text-2xl font-extrabold whitespace-nowrap">
											{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
												hasPromo(featuredProducts[currentSlide]) ? Math.round(featuredProducts[currentSlide].price * (100 - promoPercent(featuredProducts[currentSlide])) / 100) : featuredProducts[currentSlide].price
											) }}
										</p>
										<span v-if="hasPromo(featuredProducts[currentSlide])" class="inline-flex items-center rounded bg-red-600 text-white font-bold px-2 sm:px-3 py-1 text-xs sm:text-sm whitespace-nowrap">
											-{{ promoPercent(featuredProducts[currentSlide]) }}%
										</span>
									</div>
								</div>
								
								<!-- Botón comprar -->
								<div class="flex justify-center pointer-events-auto mb-8 sm:mb-12 md:mb-16">
									<button 
										@click="buyNowFromGallery(featuredProducts[currentSlide])"
										class="bg-white/80 backdrop-blur-sm text-gray-900 font-bold py-3 px-6 sm:px-8 rounded-full shadow-2xl hover:bg-white/90 transition-all transform hover:scale-105 active:scale-95 text-base sm:text-lg md:text-xl border-2 border-gray-200"
									>
										COMPRAR
									</button>
								</div>
							</div>
							
						</div>
						</div>
					</transition>
				</div>
				
				<!-- Indicadores de paginación -->
				<div v-if="featuredProducts.length > 1" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2 z-10">
					<button 
						v-for="(product, index) in featuredProducts" 
						:key="product.id"
						@click="goToSlide(index)"
						:class="currentSlide === index ? 'bg-white w-8' : 'bg-white/50 w-2'"
						class="h-2 rounded-full transition-all duration-300"
						:aria-label="`Ir a slide ${index + 1}`"
					></button>
				</div>
			</div>
		</div>

        <transition name="drawer">
            <div v-if="drawerOpen" class="fixed inset-0 z-[70] flex">
                <!-- Overlay con efecto cortina translúcida -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="drawerOpen=false"></div>
                
                <!-- Panel del menú -->
                <div class="relative w-80 max-w-[90%] bg-white/95 backdrop-blur-md h-full shadow-2xl">
                    <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-sm border-b px-4 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button v-if="menuStack.length" @click="goBack" class="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Volver">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-700">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                                </svg>
                            </button>
                            <h3 class="font-semibold text-gray-900">{{ currentTitle }}</h3>
                        </div>
                        <button @click="drawerOpen=false" class="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Cerrar">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
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
                        <div v-show="showMenuItems">
                            <transition-group name="menu-item" tag="div" :key="drawerItemsKey">
                                <div v-for="(cat, index) in currentItems" :key="`${cat.id}-${drawerItemsKey}`" class="menu-item-wrapper" :style="{ '--i': index }">
                                    <button class="w-full flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50 active:scale-[.99] transition-all duration-200" @click="cat.has_children_with_products ? openNode(cat) : applyImmediate(cat.id)">
                                <span class="font-medium text-gray-800">{{ cat.name }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs bg-gray-100 text-gray-700 rounded-full px-2 py-0.5">{{ cat.products_count }}</span>
                                    <span v-if="cat.has_children_with_products" class="w-4 h-4 inline-flex items-center justify-center">›</span>
                                </div>
                            </button>
                                </div>
                            </transition-group>
                        </div>
                    </div>
                </div>
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

/* Transiciones para el drawer (cortina translúcida) */
.drawer-enter-active {
    transition: opacity 0.3s ease;
}

.drawer-leave-active {
    transition: opacity 0.3s ease;
}

.drawer-enter-from {
    opacity: 0;
}

.drawer-leave-to {
    opacity: 0;
}

.drawer-enter-active > div:last-child {
    animation: slideInLeft 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-leave-active > div:last-child {
    animation: slideOutLeft 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideInLeft {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes slideOutLeft {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-100%);
    }
}

/* Transiciones para búsqueda expandible */
.search-expand-enter-active,
.search-expand-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.search-expand-enter-from {
    opacity: 0;
    transform: scale(0.9) translateX(10px);
}

.search-expand-leave-to {
    opacity: 0;
    transform: scale(0.9) translateX(10px);
}

.search-expand-enter-to,
.search-expand-leave-from {
    opacity: 1;
    transform: scale(1) translateX(0);
}

/* Transiciones para la galería de productos - desplazamiento automático hacia la izquierda */
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.5s ease-in-out;
}

.slide-fade-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.slide-fade-leave-to {
    opacity: 0;
    transform: translateX(-100%);
}

.slide-fade-enter-to,
.slide-fade-leave-from {
    opacity: 1;
    transform: translateX(0);
}

/* Estilos para la galería con arrastre manual */
.gallery-slide {
    will-change: transform;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

/* Efecto de menú hamburguesa estilo chalochalo.co - entrada progresiva desde la izquierda */
.menu-item-wrapper {
    margin-bottom: 0.25rem;
}

.menu-item-enter-active {
    transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    transition-delay: calc(var(--i) * 80ms);
}

.menu-item-enter-from {
    opacity: 0;
    transform: translateX(-60px);
}

.menu-item-enter-to {
    opacity: 1;
    transform: translateX(0);
}

.menu-item-leave-active {
    transition: all 0.3s ease-in-out;
}

.menu-item-leave-from {
    opacity: 1;
    transform: translateX(0);
}

.menu-item-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}

.menu-item-move {
    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>