<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import AlertModal from '@/Components/AlertModal.vue';
import { ref, watch, nextTick, computed, onMounted, onBeforeUnmount } from 'vue';
import { safeRoute } from '@/utils/safeRoute';
import axios from 'axios';

const props = defineProps({
    products: Object, // Objeto de paginación con los productos y sus variantes
    filters: Object,
});


// Estado UI del buscador
const showSearch = ref(Boolean(props.filters?.search));
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || ''); // '', 'out_of_stock', 'low_stock'
const searchInputRef = ref(null);
const showStatusMenu = ref(false);
const expandedProducts = ref({}); // Map of product ID -> boolean

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
const selectedQuickProduct = ref(null);
const quickProcessing = ref(false);

const openQuickEntry = () => {
    quickSearch.value = '';
    quickSearchResults.value = [];
    selectedQuickProduct.value = null;
    showQuickEntry.value = true;
    nextTick(() => document.getElementById('quick-search-input')?.focus());
};

const closeQuickEntry = () => {
    showQuickEntry.value = false;
};

// Función core de búsqueda
const fetchSearchResults = async () => {
    if (quickSearch.value.length < 2) return Promise.resolve();
    try {
        const res = await axios.get(route('admin.inventory.search', { q: quickSearch.value }));
        quickSearchResults.value = res.data;
        return Promise.resolve();
    } catch (err) {
        console.error(err);
        return Promise.resolve(); // Resolver incluso en caso de error para no bloquear
    }
};

let quickSearchTimer;
const searchProductsForModal = () => {
    clearTimeout(quickSearchTimer);
    if (quickSearch.value.length < 2) {
        quickSearchResults.value = [];
        return;
    }
    quickSearchTimer = setTimeout(fetchSearchResults, 300);
};

const selectQuickProduct = (product) => {
    selectedQuickProduct.value = JSON.parse(JSON.stringify(product)); // Copia profunda para editar
    
    // CORRECCIÓN: Asegurar que quantity esté correctamente establecido para productos simples
    // Si es producto simple (sin variantes reales), usar calculateMainStock para obtener el stock correcto
    const hasRealVariants = selectedQuickProduct.value.variants && 
                           selectedQuickProduct.value.variants.length > 0 && 
                           selectedQuickProduct.value.variant_options && 
                           selectedQuickProduct.value.variant_options.length > 0;
    
    if (!hasRealVariants) {
        // Para productos simples, asegurar que quantity tenga el valor correcto
        selectedQuickProduct.value.quantity = calculateMainStock(selectedQuickProduct.value);
    }
    
    // Inicializar campos de edición
    if (hasRealVariants) {
        selectedQuickProduct.value.variants.forEach(v => {
            v.qty_add = '';
            v.new_price = v.price;
            v.new_purchase_price = v.purchase_price;
        });
    } else {
        selectedQuickProduct.value.qty_add = '';
        selectedQuickProduct.value.new_price = selectedQuickProduct.value.price;
        selectedQuickProduct.value.new_purchase_price = selectedQuickProduct.value.purchase_price;
    }
};

const backToSearch = () => {
    selectedQuickProduct.value = null;
    nextTick(() => document.getElementById('quick-search-input')?.focus());
    // Refrescar lista al volver, por si acaso
    fetchSearchResults();
};

const submitQuickUpdate = (id, type, qtyAdd, purchasePrice, price) => {
    if (quickProcessing.value) return;
    const qty = Number(qtyAdd) || 0;
    
    // Validar al menos un cambio
    if (!qtyAdd && purchasePrice === undefined && price === undefined) return;

    quickProcessing.value = true;
    router.post(route('admin.inventory.quick-update'), {
        id,
        type,
        quantity_add: qty,
        purchase_price: purchasePrice,
        price: price
    }, {
        preserveScroll: true,
        preserveState: false, // FORCE REFRESH: Ensure props are reloaded to show actual server data
        onSuccess: async () => {
             // 1. Mostrar Feedback
             showAlert('success', 'Éxito', 'Inventario actualizado correctamente.');
             
             // 2. Refresh inmediato de la lista de búsqueda (Backend Source of Truth)
             // Esto asegura que el "Total Stock" calculado sea el real de base de datos
             await fetchSearchResults();

             // 3. Actualizar modelo visual actual (Formulario) con datos frescos del servidor
            if (type === 'product' && selectedQuickProduct.value && selectedQuickProduct.value.id === id) {
                 // Buscar el producto actualizado en los resultados de búsqueda
                 const updatedProduct = quickSearchResults.value.find(p => p.id === id);
                 if (updatedProduct) {
                     // Actualizar el producto seleccionado con los datos frescos
                     selectedQuickProduct.value.quantity = calculateMainStock(updatedProduct);
                     selectedQuickProduct.value.purchase_price = updatedProduct.purchase_price;
                     selectedQuickProduct.value.price = updatedProduct.price;
                 } else {
                     // Fallback: actualizar manualmente si no se encuentra en los resultados
                     if (qty > 0) {
                         selectedQuickProduct.value.quantity = (Number(selectedQuickProduct.value.quantity) || 0) + qty;
                     }
                     if (purchasePrice !== undefined) selectedQuickProduct.value.purchase_price = purchasePrice;
                     if (price !== undefined) selectedQuickProduct.value.price = price;
                 }
                 selectedQuickProduct.value.qty_add = '';

            } else if (type === 'variant' && selectedQuickProduct.value) {
                // Buscar el producto actualizado en los resultados de búsqueda
                const updatedProduct = quickSearchResults.value.find(p => p.id === selectedQuickProduct.value.id);
                if (updatedProduct && updatedProduct.variants) {
                    const updatedVariant = updatedProduct.variants.find(v => v.id === id);
                    if (updatedVariant) {
                        const v = selectedQuickProduct.value.variants.find(v => v.id === id);
                        if (v) {
                            v.stock = Number(updatedVariant.stock) || 0;
                            v.purchase_price = updatedVariant.purchase_price;
                            v.price = updatedVariant.price;
                        }
                    }
                } else {
                    // Fallback: actualizar manualmente si no se encuentra en los resultados
                    const v = selectedQuickProduct.value.variants.find(v => v.id === id);
                    if (v) {
                        if (qty > 0) {
                            v.stock = (Number(v.stock) || 0) + qty;
                        }
                        if (purchasePrice !== undefined) v.purchase_price = purchasePrice;
                        if (price !== undefined) v.price = price;
                    }
                }
                const v = selectedQuickProduct.value.variants.find(v => v.id === id);
                if (v) v.qty_add = '';
            }
            


            quickProcessing.value = false;
        },
        onError: () => {
            quickProcessing.value = false;
            showAlert('error', 'Error', 'No se pudo actualizar el inventario.');
        }
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
                                <!-- Botón Entrada Rápida -->
                                <button type="button" @click="openQuickEntry"
                                   class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 transition ease-in-out duration-150"
                                   title="Entrada Rápida">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                                        <path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="hidden md:inline ml-1">Nueva Entrada</span>
                                </button>
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
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template v-for="(product, idx) in products.data" :key="product.id">
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
                                                <td class="px-3 py-2 sm:px-6 sm:py-3"></td>
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
            </div>
        </div>
        

    
    <!-- MODAL DE ENTRADA RÁPIDA -->
    <Modal :show="showQuickEntry" @close="closeQuickEntry">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                <span v-if="!selectedQuickProduct">Buscar Producto para Entrada Rápida</span>
                <span v-else>
                    <button @click="backToSearch" class="text-indigo-600 hover:underline mr-2">&larr;</button>
                    Editando: {{ selectedQuickProduct.name }}
                </span>
            </h2>

            <!-- BUSCADOR -->
            <div v-if="!selectedQuickProduct">
                <input 
                    id="quick-search-input"
                    type="text" 
                    v-model="quickSearch" 
                    @input="searchProductsForModal"
                    placeholder="Escribe el nombre o escanea código..."
                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <div class="mt-4 max-h-60 overflow-y-auto border rounded-md" v-if="quickSearchResults.length > 0">
                    <div v-for="p in quickSearchResults" :key="p.id" 
                         @click="selectQuickProduct(p)"
                         class="p-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0 flex justify-between items-center group">
                         <div>
                            <div class="font-medium">{{ p.name }}</div>
                            <div class="text-xs text-gray-500">
                                Stock Total: {{ calculateMainStock(p) }}
                            </div>
                         </div>
                         <div class="text-indigo-600 opacity-0 group-hover:opacity-100 font-bold text-sm">SELECCIONAR</div>
                    </div>
                </div>
                <div v-else-if="quickSearch.length > 2" class="mt-4 text-gray-500 text-center text-sm">
                    No se encontraron productos.
                </div>
            </div>

            <!-- FORMULARIO DE EDICIÓN RÁPIDA -->
            <div v-else class="max-h-[60vh] overflow-y-auto pr-2">
                
                <!-- CASO: PRODUCTO SIMPLE -->
                <div v-if="!hasVariants(selectedQuickProduct)">
                    <div class="bg-gray-50 p-4 rounded-md border text-sm grid gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-500 text-xs">Stock Actual</label>
                                <div class="font-bold text-lg">{{ calculateMainStock(selectedQuickProduct) }}</div>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-1">Agregar Stock (+)</label>
                                <input type="number" min="0" v-model="selectedQuickProduct.qty_add" class="w-full border-gray-300 rounded-md shadow-sm h-8">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-500 text-xs">Precio Compra</label>
                                <input type="number" min="0" v-model="selectedQuickProduct.new_purchase_price" class="w-full border-gray-300 rounded-md shadow-sm h-8" :placeholder="selectedQuickProduct.purchase_price">
                            </div>
                            <div>
                                <label class="block text-gray-500 text-xs">Precio Venta</label>
                                <input type="number" min="0" v-model="selectedQuickProduct.new_price" class="w-full border-gray-300 rounded-md shadow-sm h-8" :placeholder="selectedQuickProduct.price">
                            </div>
                        </div>
                        <div class="flex justify-end mt-2">
                            <button 
                                @click="submitQuickUpdate(selectedQuickProduct.id, 'product', selectedQuickProduct.qty_add, selectedQuickProduct.new_purchase_price, selectedQuickProduct.new_price)"
                                :disabled="quickProcessing"
                                class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition disabled:opacity-50">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CASO: VARIANTES -->
                <template v-else>
                    <div v-for="variant in selectedQuickProduct.variants" :key="variant.id" class="mb-4 bg-white border rounded-md p-3 shadow-sm">
                        <div class="font-bold text-gray-800 border-b pb-2 mb-2 bg-gray-50 -mx-3 -mt-3 px-3 pt-2 text-sm">
                            {{ formatVariantName(variant) }} 
                            <span v-if="variant.sku" class="text-gray-400 font-normal text-xs">({{ variant.sku }})</span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
                            <div class="md:col-span-1">
                                <label class="block text-gray-400 text-[10px] uppercase">Actual</label>
                                <div class="font-bold">{{ variant.stock }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 text-xs font-bold mb-1">Sumar (+)</label>
                                <input type="number" min="0" v-model="variant.qty_add" class="w-full border-gray-300 rounded shadow-sm text-sm py-1 px-2">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-500 text-[10px] uppercase">$ Compra</label>
                                <input type="number" min="0" v-model="variant.new_purchase_price" 
                                       class="w-full border-gray-300 rounded shadow-sm text-sm py-1 px-2" 
                                       placeholder="Heredado"
                                       :title="'Heredado: ' + (selectedQuickProduct.purchase_price || 0)">
                            </div>
                            <div class="md:col-span-2 flex gap-2">
                                <div class="flex-1">
                                    <label class="block text-gray-500 text-[10px] uppercase">$ Venta</label>
                                    <input type="number" min="0" v-model="variant.new_price" 
                                           class="w-full border-gray-300 rounded shadow-sm text-sm py-1 px-2"
                                           placeholder="Heredado"
                                           :title="'Heredado: ' + (selectedQuickProduct.price || 0)">
                                </div>
                                <button 
                                    @click="submitQuickUpdate(variant.id, 'variant', variant.qty_add, variant.new_purchase_price, variant.new_price)"
                                    :disabled="quickProcessing"
                                    class="mb-[1px] p-2 bg-green-50 text-green-700 border border-green-200 rounded hover:bg-green-100 transition"
                                    title="Guardar Variantes">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button @click="closeQuickEntry" class="text-gray-500 hover:text-gray-700 mr-4">Cerrar</button>
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