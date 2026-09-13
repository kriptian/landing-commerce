<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import AlertModal from '@/Components/AlertModal.vue';
import { ref, watch, nextTick, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
    products: Object, // Objeto de paginación con los productos y sus variantes
    filters: Object,
    inventoryWarnings: Array,
});
const page = usePage();
const canEditInventory = computed(() => page.props.auth?.isSuperAdmin || (page.props.auth?.permissions || []).includes('editar inventario'));


// Estado UI del buscador
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || ''); // '', 'out_of_stock', 'low_stock'
const expandedProducts = ref({}); // Map of product ID -> boolean

const statuses = [
    { value: '', label: 'Todos' },
    { value: 'out_of_stock', label: 'Agotados' },
    { value: 'low_stock', label: '¡Pocas unidades!' },
];

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
    if (s <= 0) return null;
    return Number((((s - b) / s) * 100).toFixed(2));
};

// Precios efectivos (con herencia producto → variante)
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

// --- LOGICA DE EXPANSIÓN Y AGREGACIÓN ---

const toggleExpand = (productId) => {
    expandedProducts.value[productId] = !expandedProducts.value[productId];
};

const isExpanded = (productId) => {
    return !!expandedProducts.value[productId];
};

const hasVariants = (product) => {
    // 1. Usar el nuevo sistema de opciones jerárquicas si existe
    if (product.variant_options && product.variant_options.length > 0) return true;
    
    // 2. Fallback: Verificar si hay variantes reales con opciones (evitar "ghost variants" vacías)
    if (product.variants && product.variants.length > 0) {
        return product.variants.some(v => v.options && Object.keys(v.options).length > 0);
    }
    
    return false;
};

const formatVariantName = (variant) => {
    // Si la variante tiene sku, mostrarlo
    let name = formatVariantOptions(variant.options || {});
    if (variant.sku) name += ` (SKU: ${variant.sku})`;
    return name;
};

// Calcular stock principal
const calculateMainStock = (product) => {
    // CORRECCIÓN ROBUSTA: Solo sumar stock de variantes si realmente es un producto configurable
    // (debe tener variantes físicas Y definiciones de opciones).
    // Esto ignora "ghost variants" que puedan existir por error.
    if (product.variants && product.variants.length > 0 && product.variant_options && product.variant_options.length > 0) {
        return product.variants.reduce((sum, v) => sum + (Number(v.stock) || 0), 0);
    }
    
    // Si no tiene variantes (aunque tenga variant_options "fantasmas"), usar quantity del producto principal
    return Number(product.quantity) || 0;
};

// "sume también el valor de compra" -> Interpretado como VALOR TOTAL DEL INVENTARIO (Stock * Costo)
const calculateMainPurchaseValue = (product) => {
    if (!hasVariants(product)) {
        // Para productos simples, ¿mostrar precio unitario o valor total?
        // El usuario pidió sumar. Si mostramos valor total aquí, también debería ser para simples.
        // Pero para no cambiar comportamiento existente en simples (que muestra precio unitario),
        // mantendremos Unitario para Simples, y TOTAL para Variantes (o Unitario Promedio?)
        // El usuario dijo: "así como se suma el inventario... sume también el valor de compra".
        // Si tengo 10 items @ $100.
        // Antes mostraba: Stock 10, Precio $100.
        // Con variantes: Stock 24, Precio ¿$22.000? (Valor total).
        // Si muestro Valor Total para variantes, es inconsistente con Simples (Unitario).
        // CAMBIO: Mostraré Valor Total si hay variantes, y Unitario si es simple (como estaba),
        // pero con un label visual que ya puse en el template ("Inversión Total").
        
        return fmt(product.purchase_price); 
    }
    
    // Para productos con variantes, sumamos (Stock * Precio Compra) de cada variante
    const totalValue = product.variants.reduce((sum, v) => {
        const s = Number(v.stock) || 0;
        const p = Number(v.purchase_price) || 0;
        return sum + (s * p);
    }, 0);
    
    return fmt(totalValue);
};

// --- LOGICA DE ENTRADA RÁPIDA (QUICK ENTRY) ---
const showQuickEntry = ref(false);
const quickSearch = ref('');
const quickSearchResults = ref([]);
const quickSearchState = ref('initial');
const selectedQuickProduct = ref(null);
const selectedQuickVariant = ref(null);
const quickProcessing = ref(false);
const quickErrors = ref({});
let quickSearchController;
let quickSearchRequest = 0;

const openQuickEntry = (product = null, variant = null) => {
    quickSearch.value = '';
    quickSearchResults.value = [];
    quickSearchState.value = 'initial';
    selectedQuickProduct.value = null;
    selectedQuickVariant.value = null;
    quickErrors.value = {};
    showQuickEntry.value = true;
    if (product) {
        selectQuickProduct(product, variant);
        return;
    }
    nextTick(() => document.getElementById('quick-search-input')?.focus());
};

const closeQuickEntry = () => {
    quickSearchController?.abort();
    showQuickEntry.value = false;
};

const fetchSearchResults = async () => {
    const term = quickSearch.value.trim();
    if (term.length < 2) return;

    quickSearchController?.abort();
    quickSearchController = new AbortController();
    const requestId = ++quickSearchRequest;
    quickSearchState.value = 'loading';
    quickErrors.value = {};

    try {
        const res = await axios.get(route('admin.inventory.search', { q: term }), {
            signal: quickSearchController.signal,
        });
        if (requestId !== quickSearchRequest) return;
        quickSearchResults.value = res.data;
        quickSearchState.value = res.data.length ? 'results' : 'empty';
    } catch (err) {
        if (axios.isCancel(err) || requestId !== quickSearchRequest) return;
        quickSearchResults.value = [];
        quickSearchState.value = 'error';
    }
};

let quickSearchTimer;
const searchProductsForModal = () => {
    clearTimeout(quickSearchTimer);
    if (quickSearch.value.trim().length < 2) {
        quickSearchController?.abort();
        quickSearchRequest++;
        quickSearchResults.value = [];
        quickSearchState.value = 'initial';
        return;
    }
    quickSearchTimer = setTimeout(fetchSearchResults, 300);
};

const resetQuickFields = (item) => {
    item.qty_add = '';
    item.new_price = '';
    item.new_purchase_price = '';
};

const selectQuickProduct = (product, variant = null) => {
    selectedQuickProduct.value = JSON.parse(JSON.stringify(product)); // Copia profunda para editar
    selectedQuickVariant.value = null;
    quickErrors.value = {};
    const hasRealVariants = hasVariants(selectedQuickProduct.value);

    if (!hasRealVariants) {
        selectedQuickProduct.value.quantity = calculateMainStock(selectedQuickProduct.value);
        resetQuickFields(selectedQuickProduct.value);
    } else {
        selectedQuickProduct.value.variants.forEach(resetQuickFields);
        if (variant) {
            selectQuickVariant(selectedQuickProduct.value.variants.find((item) => item.id === variant.id));
        }
    }
};

const selectQuickVariant = (variant) => {
    selectedQuickVariant.value = variant || null;
    quickErrors.value = {};
};

const editingQuickItem = computed(() => selectedQuickVariant.value || selectedQuickProduct.value);
const editingCurrentStock = computed(() => selectedQuickVariant.value
    ? Number(selectedQuickVariant.value.stock) || 0
    : calculateMainStock(selectedQuickProduct.value || {}));
const editingProjectedStock = computed(() => editingCurrentStock.value + (Number(editingQuickItem.value?.qty_add) || 0));

const backToSearch = () => {
    selectedQuickProduct.value = null;
    selectedQuickVariant.value = null;
    quickErrors.value = {};
    nextTick(() => document.getElementById('quick-search-input')?.focus());
    if (quickSearch.value.trim().length >= 2) fetchSearchResults();
};

const optionalNumber = (value) => value === '' || value === null || value === undefined ? undefined : Number(value);

const submitQuickUpdate = (item, type) => {
    if (quickProcessing.value) return;
    const payload = { id: item.id, type };
    const quantity = optionalNumber(item.qty_add);
    const purchasePrice = optionalNumber(item.new_purchase_price);
    const price = optionalNumber(item.new_price);
    if (quantity !== undefined) payload.quantity_add = quantity;
    if (purchasePrice !== undefined) payload.purchase_price = purchasePrice;
    if (price !== undefined) payload.price = price;

    quickErrors.value = {};
    if (quantity === undefined && purchasePrice === undefined && price === undefined) {
        quickErrors.value = { quantity_add: 'Ingresa unidades o modifica al menos un precio.' };
        return;
    }

    quickProcessing.value = true;
    router.post(route('admin.inventory.quick-update'), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (quantity !== undefined) {
                if (type === 'variant') item.stock = editingProjectedStock.value;
                else item.quantity = editingProjectedStock.value;
            }
            if (purchasePrice !== undefined) item.purchase_price = purchasePrice;
            if (price !== undefined) item.price = price;
            resetQuickFields(item);
            showAlert('success', 'Entrada registrada', 'El inventario se actualizó correctamente.');
        },
        onError: (errors) => { quickErrors.value = errors; },
        onFinish: () => { quickProcessing.value = false; },
    });
};

// --- ALERT MODAL ---
const alertState = ref({ show: false, type: 'success', title: '', message: '' });
const showAlert = (type, title, message) => {
    alertState.value = { show: true, type, title, message };
    // Auto cerrar éxito rápido
    if (type === 'success') {
        setTimeout(() => { alertState.value.show = false; }, 1500); 
    }
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
    clearTimeout(quickSearchTimer);
    quickSearchController?.abort();
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
    try { localStorage.setItem(INV_COL_KEY, String(firstColWidth.value)); } catch (_) { /* Storage may be unavailable in private mode. */ }
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
        <AdminPage wide>
            <PageHeader eyebrow="Operacion" title="Inventario" description="Detecta faltantes, consulta costos y registra entradas sin salir de esta pantalla.">
                <template #actions><a :href="route('admin.inventory.export')" class="ui-secondary-button">Exportar Excel</a><button v-if="canEditInventory" dusk="inventory-entry" type="button" class="ui-primary-button" @click="openQuickEntry()">+ Registrar entrada</button></template>
            </PageHeader>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="inventoryWarnings.length" class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                            <p class="font-semibold">Inventario por variante pendiente</p>
                            <p class="mt-1 text-sm">
                                Estos productos tienen cantidad general, pero todas sus variantes están en cero. Asigna existencias a cada variante para habilitar su compra.
                            </p>
                            <ul class="mt-2 list-disc pl-5 text-sm">
                                <li v-for="warning in inventoryWarnings" :key="warning.id">
                                    {{ warning.name }}: {{ warning.global_quantity }} generales, {{ warning.variants_count }} variantes sin stock
                                </li>
                            </ul>
                        </div>

                        <form class="mb-5 grid gap-3 md:grid-cols-[minmax(240px,1fr)_220px_auto]" @submit.prevent="submitFilters">
                            <div><label for="inventory-search" class="ui-label">Buscar producto</label><input id="inventory-search" v-model="search" type="search" class="ui-input" placeholder="Nombre, SKU o codigo" /></div>
                            <div><label for="inventory-status" class="ui-label">Estado del stock</label><select id="inventory-status" v-model="status" class="ui-input"><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                            <div class="flex items-end"><button type="submit" class="ui-primary-button w-full">Aplicar filtros</button></div>
                        </form>

                        <div class="space-y-3 md:hidden">
                            <article v-for="product in products.data" :key="product.id" class="rounded-xl border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate font-bold text-slate-900">{{ product.name }}</h2><p class="mt-1 text-xs text-slate-500">{{ hasVariants(product) ? `${product.variants.length} variantes` : 'Producto simple' }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="getStockStatus({ stock: calculateMainStock(product), alert: product.alert || 0 }).class">{{ getStockStatus({ stock: calculateMainStock(product), alert: product.alert || 0 }).text }}</span></div>
                                <dl class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-sm"><div><dt class="text-xs text-slate-500">Stock</dt><dd class="mt-1 font-extrabold">{{ calculateMainStock(product) }}</dd></div><div><dt class="text-xs text-slate-500">Compra</dt><dd class="mt-1 font-bold">{{ calculateMainPurchaseValue(product) }}</dd></div><div><dt class="text-xs text-slate-500">Venta</dt><dd class="mt-1 font-bold">{{ hasVariants(product) ? 'Variable' : fmt(product.price) }}</dd></div></dl>
                                <div class="mt-3 flex items-center justify-between gap-3"><button v-if="hasVariants(product)" type="button" class="text-sm font-bold text-indigo-700" @click="toggleExpand(product.id)">{{ isExpanded(product.id) ? 'Ocultar variantes' : 'Ver variantes' }}</button><span v-else></span><button v-if="canEditInventory" type="button" class="ui-secondary-button" @click="openQuickEntry(product)">Registrar entrada</button></div>
                                <div v-if="isExpanded(product.id) && hasVariants(product)" class="mt-3 space-y-2 border-l-2 border-indigo-100 pl-3"><div v-for="variant in filteredVariants(product.variants)" :key="variant.id" class="rounded-lg bg-slate-50 p-3 text-sm"><p class="font-semibold text-slate-800">{{ formatVariantName(variant) }}</p><p class="mt-1 text-slate-500">Stock {{ Number(variant.stock) || 0 }} · Venta {{ fmt(effRetail(product, variant)) }}</p></div></div>
                            </article>
                            <div v-if="!products.data.length" class="rounded-xl border-2 border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">No hay productos para mostrar.</div>
                        </div>

                        <div ref="scrollBoxRef" class="relative hidden overflow-x-auto w-full z-0 md:block">
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
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Accion</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template v-for="product in products.data" :key="product.id">
                                        <tr v-if="matchesProductStatus(product)" class="odd:bg-white even:bg-gray-100 group">
                                            <!-- Columna Producto con Chevron -->
                                            <td class="sticky left-0 z-10 px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r bg-[inherit]" :style="firstColStyle">
                                                <div class="flex items-center gap-2">
                                                    <button 
                                                        v-if="product.variants && product.variants.length > 0"
                                                        type="button" 
                                                        @click="toggleExpand(product.id)"
                                                        class="p-1 rounded hover:bg-gray-200 text-gray-500 transition-transform duration-200"
                                                        :class="{ 'rotate-90': isExpanded(product.id) }"
                                                    >
                                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.16 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    <span class="truncate" :title="product.name">{{ product.name }}</span>
                                                </div>
                                            </td>
                                            
                                            <!-- STOCKS Y PRECIOS AGREGADOS -->
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm text-gray-800 font-bold">
                                                {{ calculateMainStock(product) }}
                                                <span v-if="hasVariants(product)" class="text-xs text-gray-500 font-normal ml-1">(Total)</span>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm text-gray-700">
                                                <!-- Suma del valor de compra (Inversión Total) -->
                                                {{ calculateMainPurchaseValue(product) }}
                                                <div v-if="hasVariants(product)" class="text-xs text-gray-500">Inversión Total</div>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm text-gray-700">
                                                <span v-if="!hasVariants(product)">{{ fmt(product.price) }}</span>
                                                <span v-else class="text-gray-400 italic text-xs">Ver variantes</span>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm">
                                                <span v-if="!hasVariants(product)" :class="{ 'text-gray-500': pct(product.purchase_price, product.price) === null }">
                                                    {{ pct(product.purchase_price, product.price) !== null ? pct(product.purchase_price, product.price) + '%' : '—' }}
                                                </span>
                                                <span v-else class="text-gray-400 text-xs">—</span>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm">
                                                <span v-if="!hasVariants(product)" :class="{ 'text-gray-500': profit(product.purchase_price, product.price) === null }">
                                                    {{ profit(product.purchase_price, product.price) !== null ? fmt(profit(product.purchase_price, product.price)) : '—' }}
                                                </span>
                                                <span v-else class="text-gray-400 text-xs">—</span>
                                            </td>
                                            <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">
                                                <!-- Estado Global (simplificado) -->
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="getStockStatus({ stock: calculateMainStock(product), alert: product.alert || 0 }).class">
                                                    {{ getStockStatus({ stock: calculateMainStock(product), alert: product.alert || 0 }).text }}
                                                </span>
                                            </td>
                                             <td class="px-3 py-3 text-right sm:px-6 sm:py-4"><button v-if="canEditInventory" type="button" class="ui-secondary-button" @click="openQuickEntry(product)">Registrar entrada</button></td>
                                        </tr>

                                        <!-- Filas de Variantes (Expandible) -->
                                        <template v-if="isExpanded(product.id) && hasVariants(product)">
                                            <tr v-for="variant in product.variants" :key="variant.id" class="bg-gray-50 border-b border-gray-100">
                                                <td class="sticky left-0 z-10 px-3 py-2 sm:px-6 sm:py-3 text-sm text-gray-500 border-r bg-gray-50" :style="{...firstColStyle, paddingLeft: '2rem'}">
                                                    <div class="flex items-center gap-2">
                                                        <svg class="w-3 h-3 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                                            <polyline points="16 17 21 12 16 7" />
                                                            <line x1="21" y1="12" x2="9" y2="12" />
                                                        </svg>
                                                        <span class="truncate">{{ formatVariantName(variant) }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2 sm:px-6 sm:py-3 whitespace-nowrap text-sm text-gray-600 font-medium">
                                                    {{ Number(variant.stock) || 0 }}
                                                </td>
                                                <td class="px-3 py-2 sm:px-6 sm:py-3 whitespace-nowrap text-sm text-gray-600">
                                                    {{ fmt(variant.purchase_price || product.purchase_price) }}
                                                </td>
                                                <td class="px-3 py-2 sm:px-6 sm:py-3 whitespace-nowrap text-sm text-gray-600">
                                                    {{ fmt(variant.price || product.price) }}
                                                </td>
                                                <td class="px-3 py-2 sm:px-6 sm:py-3 whitespace-nowrap text-sm">
                                                    <span :class="{ 'text-gray-400': pct(variant.purchase_price || product.purchase_price, variant.price || product.price) === null }">
                                                        {{ pct(variant.purchase_price || product.purchase_price, variant.price || product.price) !== null ? pct(variant.purchase_price || product.purchase_price, variant.price || product.price) + '%' : '—' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 sm:px-6 sm:py-3 whitespace-nowrap text-sm">
                                                    <span :class="{ 'text-green-600 font-medium': profit(variant.purchase_price || product.purchase_price, variant.price || product.price) !== null }">
                                                        {{ profit(variant.purchase_price || product.purchase_price, variant.price || product.price) !== null ? fmt(profit(variant.purchase_price || product.purchase_price, variant.price || product.price)) : '—' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 sm:px-6 sm:py-3 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-[10px] leading-4 font-semibold rounded-full"
                                                          :class="getStockStatus({ stock: variant.stock, alert: variant.alert || 0 }).class">
                                                        {{ getStockStatus({ stock: variant.stock, alert: variant.alert || 0 }).text }}
                                                    </span>
                                                </td>
                                                 <td class="px-3 py-2 text-right sm:px-6 sm:py-3"><button v-if="canEditInventory" type="button" class="text-xs font-bold text-indigo-700 hover:text-indigo-900" @click="openQuickEntry(product, variant)">Registrar</button></td>
                                            </tr>
                                        </template>
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
        </AdminPage>
        

    
    <!-- MODAL DE ENTRADA RÁPIDA -->
    <Modal :show="showQuickEntry" @close="closeQuickEntry">
        <div class="p-5 sm:p-6">
            <h2 class="text-xl font-bold text-slate-900">Registrar entrada de inventario</h2>
            <p class="mt-1 text-sm text-slate-500">{{ selectedQuickProduct ? 'Confirma el artículo recibido antes de guardar.' : 'Busca por nombre, código de barras, SKU u opción.' }}</p>

            <div v-if="!selectedQuickProduct" class="mt-5">
                <label for="quick-search-input" class="ui-label">Producto recibido</label>
                <div class="relative">
                    <input id="quick-search-input" v-model="quickSearch" type="search" autocomplete="off" class="ui-input pr-10" placeholder="Nombre, barcode, SKU, color, talla..." @input="searchProductsForModal">
                    <svg v-if="quickSearchState === 'loading'" class="absolute right-3 top-3 h-5 w-5 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                </div>

                <div class="mt-4" aria-live="polite">
                    <div v-if="quickSearchState === 'initial'" class="rounded-xl border-2 border-dashed border-slate-200 px-5 py-8 text-center text-sm text-slate-500">Escribe al menos 2 caracteres o escanea un código para comenzar.</div>
                    <div v-else-if="quickSearchState === 'loading'" class="space-y-2" aria-label="Buscando productos"><div v-for="item in 3" :key="item" class="h-20 animate-pulse rounded-xl bg-slate-100"></div></div>
                    <div v-else-if="quickSearchState === 'empty'" class="rounded-xl bg-slate-50 px-5 py-8 text-center"><p class="font-semibold text-slate-700">No encontramos coincidencias</p><p class="mt-1 text-sm text-slate-500">Revisa el código o intenta con otro nombre u opción.</p></div>
                    <div v-else-if="quickSearchState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-5 py-5 text-center"><p class="font-semibold text-red-800">No pudimos completar la búsqueda.</p><button type="button" class="mt-2 text-sm font-bold text-red-700 underline" @click="fetchSearchResults">Intentar de nuevo</button></div>
                    <div v-else class="max-h-[52vh] space-y-2 overflow-y-auto pr-1">
                        <button v-for="product in quickSearchResults" :key="product.id" type="button" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3 text-left transition hover:border-indigo-300 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500" @click="selectQuickProduct(product)">
                            <img :src="product.thumbnail" alt="" class="h-14 w-14 shrink-0 rounded-lg bg-slate-100 object-cover">
                            <span class="min-w-0 flex-1"><span class="block truncate font-bold text-slate-900">{{ product.name }}</span><span class="mt-1 block text-xs text-slate-500">{{ product.barcode ? `Código ${product.barcode}` : 'Sin código' }} · Stock {{ calculateMainStock(product) }}</span><span v-if="hasVariants(product)" class="mt-1 block truncate text-xs font-medium text-indigo-700">{{ product.variants.length }} variantes · {{ product.variants.map(formatVariantName).join(' · ') }}</span><span v-else class="mt-1 block text-xs text-slate-500">Producto simple</span></span>
                            <span class="hidden text-xs font-bold text-indigo-700 sm:block">Elegir</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="mt-5 max-h-[65vh] overflow-y-auto pr-1">
                <section class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <img :src="selectedQuickProduct.thumbnail" alt="" class="h-16 w-16 shrink-0 rounded-lg bg-white object-cover">
                    <div class="min-w-0"><p class="truncate font-bold text-slate-900">{{ selectedQuickProduct.name }}</p><p class="mt-1 text-xs text-slate-500">{{ selectedQuickProduct.barcode ? `Código ${selectedQuickProduct.barcode}` : 'Sin código de barras' }}</p><p class="mt-1 text-xs font-semibold text-slate-700">Stock total: {{ calculateMainStock(selectedQuickProduct) }} · {{ hasVariants(selectedQuickProduct) ? `${selectedQuickProduct.variants.length} variantes` : 'Producto simple' }}</p></div>
                </section>

                <div v-if="hasVariants(selectedQuickProduct) && !selectedQuickVariant" class="mt-5">
                    <h3 class="font-bold text-slate-900">¿Qué variante recibiste?</h3>
                    <p class="mt-1 text-sm text-slate-500">Selecciona una combinación exacta para evitar mover stock incorrecto.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button v-for="variant in selectedQuickProduct.variants" :key="variant.id" type="button" class="rounded-xl border border-slate-200 p-3 text-left hover:border-indigo-400 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500" @click="selectQuickVariant(variant)">
                            <span class="block font-bold text-slate-800">{{ formatVariantOptions(variant.options || {}) || 'Variante' }}</span><span class="mt-1 block text-xs text-slate-500">{{ variant.sku ? `SKU ${variant.sku}` : 'Sin SKU' }} · Stock {{ Number(variant.stock) || 0 }}</span>
                        </button>
                    </div>
                </div>

                <form v-else class="mt-5" @submit.prevent="submitQuickUpdate(editingQuickItem, selectedQuickVariant ? 'variant' : 'product')">
                    <div v-if="selectedQuickVariant" class="mb-4 flex items-center justify-between gap-3 rounded-lg bg-indigo-50 px-3 py-2"><div><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Variante elegida</p><p class="font-bold text-indigo-950">{{ formatVariantOptions(selectedQuickVariant.options || {}) }}</p><p v-if="selectedQuickVariant.sku" class="text-xs text-indigo-700">SKU {{ selectedQuickVariant.sku }}</p></div><button type="button" class="text-sm font-bold text-indigo-700 underline" @click="selectQuickVariant(null)">Cambiar</button></div>

                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-slate-900 p-4 text-white">
                        <div><p class="text-xs text-slate-300">Stock actual</p><p class="mt-1 text-2xl font-extrabold">{{ editingCurrentStock }}</p></div>
                        <div><p class="text-xs text-slate-300">Quedará en</p><p class="mt-1 text-2xl font-extrabold text-emerald-300">{{ editingProjectedStock }}</p></div>
                    </div>

                    <div class="mt-4">
                        <label for="quick-quantity" class="ui-label">Unidades recibidas</label>
                        <input id="quick-quantity" v-model="editingQuickItem.qty_add" type="number" min="1" step="1" inputmode="numeric" class="ui-input" :class="{ 'border-red-400': quickErrors.quantity_add }" placeholder="Ej. 12">
                        <p v-if="quickErrors.quantity_add" class="mt-1 text-sm text-red-600">{{ quickErrors.quantity_add }}</p>
                    </div>

                    <details class="mt-4 rounded-xl border border-slate-200 p-3">
                        <summary class="cursor-pointer text-sm font-bold text-slate-700">También cambiaron los precios</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div><label for="quick-cost" class="ui-label">Nuevo costo unitario</label><input id="quick-cost" v-model="editingQuickItem.new_purchase_price" type="number" min="0" step="0.01" class="ui-input" :placeholder="String(editingQuickItem.purchase_price ?? selectedQuickProduct.purchase_price ?? 0)"><p v-if="quickErrors.purchase_price" class="mt-1 text-sm text-red-600">{{ quickErrors.purchase_price }}</p></div>
                            <div><label for="quick-price" class="ui-label">Nuevo precio de venta</label><input id="quick-price" v-model="editingQuickItem.new_price" type="number" min="0" step="0.01" class="ui-input" :placeholder="String(editingQuickItem.price ?? selectedQuickProduct.price ?? 0)"><p v-if="quickErrors.price" class="mt-1 text-sm text-red-600">{{ quickErrors.price }}</p></div>
                        </div>
                    </details>

                    <button type="submit" :disabled="quickProcessing" class="ui-primary-button mt-5 w-full justify-center disabled:cursor-wait disabled:opacity-60">{{ quickProcessing ? 'Guardando...' : 'Registrar entrada' }}</button>
                </form>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                <button v-if="selectedQuickProduct" type="button" class="ui-secondary-button" @click="backToSearch">Elegir otro producto</button>
                <button type="button" class="ui-secondary-button" @click="closeQuickEntry">Cerrar</button>
            </div>
        </div>
    </Modal>

    <!-- FEEDBACK MODAL -->
    <AlertModal 
        :show="alertState.show" 
        :type="alertState.type" 
        :title="alertState.title" 
        :message="alertState.message" 
        primaryText="Aceptar" 
        @close="alertState.show = false" 
        @primary="alertState.show = false" 
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
