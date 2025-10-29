<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import SectionTour from '@/Components/SectionTour.vue';
import { useSectionTour } from '@/utils/useSectionTour.js';
import { ref, watch, nextTick, computed, onMounted, onBeforeUnmount } from 'vue';
import { safeRoute } from '@/utils/safeRoute';

const props = defineProps({
    products: Object, // Objeto de paginación con los productos y sus variantes
    filters: Object,
});

// Tour de la sección de inventario
const { showTour, steps, handleTourComplete } = useSectionTour('inventory');

// Estado UI del buscador
const showSearch = ref(Boolean(props.filters?.search));
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || ''); // '', 'out_of_stock', 'low_stock'
const searchInputRef = ref(null);
const showStatusMenu = ref(false);

const statuses = [
    { value: '', label: 'Todos' },
    { value: 'out_of_stock', label: 'Agotados' },
    { value: 'low_stock', label: '¡Pocas unidades!' },
];

const statusLabel = computed(() => {
    return (statuses.find(s => s.value === status.value) || statuses[0]).label;
});

// Abrir la cortina de búsqueda y enfocar
const toggleSearch = async () => {
    showSearch.value = !showSearch.value;
    if (showSearch.value) {
        await nextTick();
        searchInputRef.value?.focus();
    } else {
        // Si se cierra, limpiar búsqueda y enviar
        search.value = '';
        submitFilters();
    }
};

// Enviar filtros a la URL Conservando scroll y reemplazando historia
const submitFilters = () => {
    router.get(route('admin.inventory.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Disparar búsqueda al tipear con debounce sencillo
let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => submitFilters(), 350);
});

// Seleccionar estado desde el menú y aplicar filtro
const selectStatus = (newStatus) => {
    status.value = newStatus;
    showStatusMenu.value = false;
    submitFilters();
};

// Filtrar variantes en la UI según estado activo (estricto y consistente con backend)
const filteredVariants = (variants) => {
    if (!status.value) return variants;
    return variants.filter((v) => {
        const stock = Number(v.stock) || 0;
        const alert = Number(v.alert) || 0;
        if (status.value === 'out_of_stock') return stock <= 0;
        if (status.value === 'low_stock') return alert > 0 && stock > 0 && stock <= alert;
        return true;
    });
};

// Mostrar productos sin variantes solo si coinciden con el filtro (estricto)
const matchesProductStatus = (product) => {
    if (!status.value) return true;
    const qty = Number(product.quantity) || 0;
    const alert = Number(product.alert) || 0;
    if (status.value === 'out_of_stock') return qty <= 0;
    if (status.value === 'low_stock') return alert > 0 && qty > 0 && qty <= alert;
    return true;
};

// Función para determinar el estado del stock
const getStockStatus = (item) => {
    const stock = Number(item.stock) || 0;
    const threshold = Number(item.alert) || 0;
    if (stock <= 0) {
        return { text: 'Agotado', class: 'bg-red-100 text-red-800' };
    }
    if (threshold > 0 && stock <= threshold) {
        return { text: '¡Pocas unidades!', class: 'bg-yellow-100 text-yellow-800' };
    }
    return { text: 'En Stock', class: 'bg-green-100 text-green-800' };
};

// Formateos y cálculos de márgenes
const fmt = (v) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(Number(v || 0));
const pct = (buy, sell) => {
    const b = Number(buy || 0);
    const s = Number(sell || 0);
    if (!b || !s) return null;
    if (b <= 0) return null;
    return Number((((s - b) / b) * 100).toFixed(2));
};

// Precios efectivos (con herencia producto → variante)
const effBuy = (product, variant = null) => {
    const v = variant?.purchase_price;
    return (v ?? null) !== null ? Number(v) : Number(product.purchase_price ?? 0);
};
const effRetail = (product, variant = null) => Number(variant?.price ?? product.price);
const profit = (buy, sell) => {
    const b = Number(buy || 0);
    const s = Number(sell || 0);
    if (!b || !s) return null;
    if (b <= 0) return null;
    const diff = s - b;
    return diff >= 0 ? diff : null;
};

// Función para juntar las opciones de la variante en un texto
const formatVariantOptions = (options) => {
    return Object.entries(options).map(([key, value]) => `${key}: ${value}`).join(', ');
};

// Indicadores de scroll lateral (degradados) para mobile
const scrollBoxRef = ref(null);
const showLeftFade = ref(false);
const showRightFade = ref(false);
const updateFades = () => {
    const el = scrollBoxRef.value;
    if (!el) return;
    const maxScrollLeft = el.scrollWidth - el.clientWidth;
    const left = el.scrollLeft || 0;
    showLeftFade.value = left > 0;
    showRightFade.value = left < (maxScrollLeft - 1);
};
onMounted(() => {
    nextTick(() => updateFades());
    scrollBoxRef.value?.addEventListener('scroll', updateFades, { passive: true });
    window.addEventListener('resize', updateFades);
});
onBeforeUnmount(() => {
    scrollBoxRef.value?.removeEventListener('scroll', updateFades);
    window.removeEventListener('resize', updateFades);
});

// Redimensionable: primera columna (persistente en localStorage)
const INV_COL_KEY = 'inv_firstcol_w_px';
const FIRST_MIN = 90;
const FIRST_MAX = 320;
const firstColWidth = ref(Number(localStorage.getItem(INV_COL_KEY)) || 180);
const firstColStyle = computed(() => ({
    width: firstColWidth.value + 'px',
    minWidth: firstColWidth.value + 'px',
    maxWidth: firstColWidth.value + 'px',
}));
let startX = 0;
let startW = 0;
const onResizeMove = (e) => {
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const next = Math.max(FIRST_MIN, Math.min(FIRST_MAX, startW + (clientX - startX)));
    firstColWidth.value = next;
};
const stopResize = () => {
    document.removeEventListener('mousemove', onResizeMove);
    document.removeEventListener('mouseup', stopResize);
    document.removeEventListener('touchmove', onResizeMove);
    document.removeEventListener('touchend', stopResize);
    try { localStorage.setItem(INV_COL_KEY, String(firstColWidth.value)); } catch (_) {}
};
const startResize = (e) => {
    startX = e.touches ? e.touches[0].clientX : e.clientX;
    startW = firstColWidth.value;
    document.addEventListener('mousemove', onResizeMove, { passive: false });
    document.addEventListener('mouseup', stopResize);
    document.addEventListener('touchmove', onResizeMove, { passive: false });
    document.addEventListener('touchend', stopResize);
};
</script>

<template>
    <Head title="Gestión de Inventario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de Inventario</h2>
        </template>

        <div class="py-12">
            <div class="max-w-screen-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <!-- Barra de acciones: buscador con cortina + filtro -->
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <!-- Botón lupita -->
                                <button type="button" @click="toggleSearch" class="p-2 rounded hover:bg-gray-100 transition">
                                    <!-- Ícono de lupa -->
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
                                    </svg>
                                </button>
                                <!-- Cortina del buscador -->
                                <transition name="fade-slide">
                                    <div v-if="showSearch" class="relative">
                                        <input
                                            ref="searchInputRef"
                                            v-model="search"
                                            type="text"
                                            placeholder="Buscar producto..."
                                            class="w-64 md:w-80 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                </transition>
                            </div>
                            <div class="flex items-center gap-2">
                                <a :href="route('admin.inventory.export')"
                                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2">
                                        <path d="M7 10a1 1 0 011-1h2V4a1 1 0 112 0v5h2a1 1 0 01.7 1.714l-3 3a1 1 0 01-1.4 0l-3-3A1 1 0 017 10z"/>
                                        <path d="M5 15a1 1 0 011 1v2a2 2 0 002 2h8a2 2 0 002-2v-2a1 1 0 112 0v2a4 4 0 01-4 4H8a4 4 0 01-4-4v-2a1 1 0 011-1z"/>
                                    </svg>
                                    Exportar a Excel
                                </a>
                                <!-- Dropdown de estado (estilizado) -->
                            <div class="relative z-30">
                                <button type="button" @click="showStatusMenu = !showStatusMenu" class="inline-flex items-center gap-2 px-3 py-2 border rounded-md shadow-sm hover:bg-gray-50">
                                    <span>{{ statusLabel }}</span>
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                                </button>
                                <transition name="fade-slide">
                                    <ul v-if="showStatusMenu" class="absolute right-0 mt-2 w-40 bg-white border rounded-md shadow-lg z-50">
                                        <li v-for="opt in statuses" :key="opt.value">
                                            <button type="button" @click="selectStatus(opt.value)" class="w-full text-left px-3 py-2 hover:bg-gray-100" :class="{ 'font-semibold text-indigo-600': opt.value === status }">
                                                {{ opt.label }}
                                            </button>
                                        </li>
                                    </ul>
                                </transition>
                            </div>
                            </div>
                        </div>

                        <div ref="scrollBoxRef" class="relative overflow-x-auto w-full z-0">
                            <!-- Fades laterales como hint de scroll -->
                            <div v-show="showLeftFade" class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-white to-transparent"></div>
                            <div v-show="showRightFade" class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-white to-transparent"></div>
                            <table class="min-w-[920px] sm:min-w-full divide-y divide-gray-200">
                                <thead class="sticky top-0 z-10 bg-gray-50">
                                    <tr>
                                        <th class="sticky left-0 z-20 bg-gray-50 px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap relative" :style="firstColStyle">
                                            Producto
                                            <div @mousedown="startResize" @touchstart.prevent="startResize" class="absolute top-0 right-0 h-full w-3 cursor-col-resize group">
                                                <div class="mx-auto my-auto h-6 w-1.5 bg-gray-300 rounded-full group-hover:bg-indigo-400"></div>
                                            </div>
                                        </th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">STOCK ACTUAL</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">$ COMPRA</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">$ VENTA</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">% Gan.</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">$ Gan.</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Estado</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap w-12">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template v-for="(product, idx) in products.data" :key="product.id">
                                        <tr v-if="matchesProductStatus(product)" class="odd:bg-white even:bg-gray-100">
                                            <td class="sticky left-0 z-10 px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r truncate" :style="firstColStyle" :title="product.name" :class="idx % 2 === 1 ? 'bg-gray-100' : 'bg-white'">{{ product.name }}</td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm text-gray-800 font-bold">{{ product.quantity }}</td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm text-gray-700">{{ fmt(product.purchase_price) }}</td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm text-gray-700">{{ fmt(product.price) }}</td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm" :class="{ 'text-gray-500': pct(product.purchase_price, product.price) === null }">
                                                <span v-if="pct(product.purchase_price, product.price) !== null">{{ pct(product.purchase_price, product.price) }}%</span>
                                                <span v-else>—</span>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm" :class="{ 'text-gray-500': profit(product.purchase_price, product.price) === null }">
                                                <span v-if="profit(product.purchase_price, product.price) !== null">{{ fmt(profit(product.purchase_price, product.price)) }}</span>
                                                <span v-else>—</span>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="getStockStatus({ stock: product.quantity, alert: product.alert }).class">
                                                    {{ getStockStatus({ stock: product.quantity, alert: product.alert }).text }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap w-12">
                                                <Link :href="route('admin.products.edit', product.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-indigo-600" title="Editar">
                                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                                </Link>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-if="products.data.length === 0">
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                            No hay productos para mostrar.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <Pagination class="mt-6" :links="products.links" />

                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tour de la sección de inventario -->
        <SectionTour 
            :show="showTour" 
            section="inventory" 
            :steps="steps" 
            @complete="handleTourComplete" 
        />
    </AuthenticatedLayout>
</template>

<style>
.fade-slide-enter-active, .fade-slide-leave-active { transition: all .2s ease; }
.fade-slide-enter-from { opacity: 0; transform: translateY(-4px); }
.fade-slide-enter-to { opacity: 1; transform: translateY(0); }
.fade-slide-leave-from { opacity: 1; transform: translateY(0); }
.fade-slide-leave-to { opacity: 0; transform: translateY(-4px); }
</style>