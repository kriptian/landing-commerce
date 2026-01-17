<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, nextTick, computed } from 'vue'; // <-- Importamos ref
// --- 1. IMPORTAMOS LOS COMPONENTES DEL MODAL ---
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AlertModal from '@/Components/AlertModal.vue';
import Pagination from '@/Components/Pagination.vue';
// ---------------------------------------------

const props = defineProps({
    products: Object,
    categories: Array,
    store: Object,
    filters: Object,
});

// --- 2. LÓGICA NUEVA PARA MANEJAR EL MODAL ---
const page = usePage();
const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || '');
const selectedStatus = ref(props.filters?.status || '');
const confirmingProductDeletion = ref(false);
const productToDelete = ref(null);
const showProductNotice = ref(page?.props?.flash?.success ? true : false);
const productNotice = ref(page?.props?.flash?.success || '');
const confirmProductDeletion = (id) => {
    productToDelete.value = id;
    confirmingProductDeletion.value = true;
};

const closeModal = () => {
    confirmingProductDeletion.value = false;
    productToDelete.value = null;
};

// Renombramos la función 'destroy' a 'deleteProduct'
const deleteProduct = () => {
    router.delete(route('admin.products.destroy', productToDelete.value), {
        onSuccess: () => {
            closeModal();
            productNotice.value = '¡Producto eliminado con éxito!';
            showProductNotice.value = true;
        }
    });
};
// Promo handlers
const togglePromo = (product) => {
    router.put(route('admin.products.update', product.id), { promo_active: !product.promo_active, promo_discount_percent: product.promo_discount_percent }, { preserveScroll: true });
};
const updatePromoPercent = (product, val) => {
    const pct = Math.max(1, Math.min(90, Number(val) || 0));
    router.put(route('admin.products.update', product.id), { promo_discount_percent: pct, promo_active: true }, { preserveScroll: true });
};
// Global promo handlers
const toggleStorePromo = () => {
    router.put(route('admin.products.store_promo'), { promo_active: !props.store.promo_active, promo_discount_percent: props.store.promo_discount_percent }, { preserveScroll: true });
};
const updateStorePromoPercent = (val) => {
    const pct = Math.max(1, Math.min(90, Number(val) || 0));
    router.put(route('admin.products.store_promo'), { promo_active: true, promo_discount_percent: pct }, { preserveScroll: true });
};
const applyFilters = () => {
    router.get(route('admin.products.index'), { 
        search: search.value, 
        category: selectedCategory.value, 
        status: selectedStatus.value 
    }, { 
        preserveState: true, 
        replace: true, 
        preserveScroll: true 
    });
};
// ---------------------------------------------

// Scroll lateral + degradados + header sticky + filas cebra
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

// Redimensionable: primera columna (persistente por usuario)
const PROD_COL_KEY = 'prod_firstcol_w_px';
const FIRST_MIN = 60;
const FIRST_MAX = 320;
const firstColWidth = ref(Number(localStorage.getItem(PROD_COL_KEY)) || 180);
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
    try { localStorage.setItem(PROD_COL_KEY, String(firstColWidth.value)); } catch (_) {}
};
const startResize = (e) => {
    startX = e.touches ? e.touches[0].clientX : e.clientX;
    startW = firstColWidth.value;
    document.addEventListener('mousemove', onResizeMove, { passive: false });
    document.addEventListener('mouseup', stopResize);
    document.addEventListener('touchmove', onResizeMove, { passive: false });
    document.addEventListener('touchend', stopResize);
};
const getDisplayStock = (product) => {
    // CORRECCIÓN ROBUSTA: Solo sumar stock de variantes si realmente es un producto configurable
    // (debe tener variantes físicas Y definiciones de opciones).
    // Esto ignora "ghost variants" que puedan existir por error.
    if (product.variants && product.variants.length > 0 && product.variant_options && product.variant_options.length > 0) {
        return product.variants_sum_stock ?? product.variants.reduce((acc, v) => acc + (Number(v.stock) || 0), 0) ?? 0;
    }
    
    // Si es simple (o tiene datos inconsistentes), usar quantity del producto principal
    return product.quantity;
};
</script>

<template>
    <Head title="Gestionar Productos" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestionar Productos</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">

                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <input type="text" v-model="search" @input="applyFilters" placeholder="Buscar productos..." class="border rounded px-3 py-2 text-sm h-10" />
                                <select v-model="selectedCategory" @change="applyFilters" class="border rounded px-2 py-2 text-sm h-10 min-w-[170px] max-w-[220px]">
                                    <option value="">Todas las categorías</option>
                                    <option v-for="c in props.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <select v-model="selectedStatus" @change="applyFilters" class="border rounded px-2 py-2 text-sm h-10 min-w-[150px]">
                                    <option value="">Todos</option>
                                    <option value="active">Activos</option>
                                    <option value="inactive">Inactivos</option>
                                </select>
                                <div class="hidden md:block h-6 w-px bg-gray-200"></div>
                                <div class="flex items-center gap-2 border rounded-lg px-3 py-2">
                                    <span class="text-sm text-gray-600">Promo Global:</span>
                                    <label class="inline-flex items-center gap-1 text-sm">
                                        <input type="checkbox" :checked="props.store.promo_active" @change="toggleStorePromo" class="rounded border-gray-300">
                                        Activa
                                    </label>
                                    <input type="number" min="1" max="90" :value="props.store.promo_discount_percent ?? ''" @input="updateStorePromoPercent($event.target.value)" placeholder="%" class="w-16 text-sm border rounded px-1 py-0.5">
                                </div>
                                <Link :href="route('admin.products.create')" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm9-1.5V7h4.5L13 2.5z"/><path d="M8 13h8v2H8zM8 9h5v2H8z"/></svg>
                                    <span>Crear</span>
                                </Link>
                            </div>
                        </div>

                        <div ref="scrollBoxRef" class="relative overflow-x-auto">
                            <div v-show="showLeftFade" class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-white to-transparent"></div>
                            <div v-show="showRightFade" class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-white to-transparent"></div>
                            <table class="min-w-[900px] w-full divide-y divide-gray-200 table-auto">
                                <thead class="sticky top-0 z-10 bg-gray-50">
                                    <tr>
                                        <th class="sticky left-0 z-20 bg-gray-50 px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap relative" :style="firstColStyle">
                                            Nombre
                                            <div @mousedown="startResize" @touchstart.prevent="startResize" class="absolute top-0 right-0 h-full w-3 cursor-col-resize group">
                                                <div class="mx-auto my-auto h-6 w-1.5 bg-gray-300 rounded-full group-hover:bg-indigo-400"></div>
                                            </div>
                                        </th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Precio</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Cantidad</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Categoría</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Activo</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Promoción</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="!products.data || products.data.length === 0">
                                        <td colspan="7" class="px-3 py-3 sm:px-6 sm:py-4 text-center text-gray-500">No hay productos creados.</td>
                                    </tr>
                                    <tr v-for="(product, idx) in products.data" :key="product.id" class="odd:bg-white even:bg-gray-100">
                                        <td class="sticky left-0 z-10 px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap border-r truncate" :style="firstColStyle" :title="product.name" :class="idx % 2 === 1 ? 'bg-gray-100' : 'bg-white'">
                                            <span>{{ product.name }}</span>
                                            <span v-if="!product.is_active" class="ml-2 inline-flex items-center rounded bg-gray-200 text-gray-700 text-xs font-semibold px-2 py-0.5">Inactivo</span>
                                        </td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">$ {{ Number(product.price).toLocaleString('es-CO') }}</td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ getDisplayStock(product) }}</td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ product.category ? product.category.name : 'Sin Categoría' }}</td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">
                                            <label class="inline-flex items-center gap-1 text-sm">
                                                <input type="checkbox" :checked="product.is_active" @change="router.put(route('admin.products.update', product.id), { is_active: !product.is_active }, { preserveScroll: true })" class="rounded border-gray-300">
                                                {{ product.is_active ? 'Sí' : 'No' }}
                                            </label>
                                        </td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <label class="inline-flex items-center gap-1 text-sm">
                                                    <input type="checkbox" :checked="product.promo_active" @change="togglePromo(product)" class="rounded border-gray-300">
                                                    Activa
                                                </label>
                                                <input type="number" min="1" max="90" :value="product.promo_discount_percent ?? ''" @input="updatePromoPercent(product, $event.target.value)" placeholder="%" class="w-16 text-sm border rounded px-1 py-0.5">
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center gap-2">
                                                <Link :href="route('admin.products.edit', product.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-indigo-600" title="Editar">
                                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                                </Link>
                                                <button @click="confirmProductDeletion(product.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-red-600" title="Eliminar">
                                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div v-if="products && products.links" class="mt-6">
                            <Pagination :links="products.links" />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    
    <Modal :show="confirmingProductDeletion" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Estás seguro de que querés eliminar este producto?
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Esta acción es irreversible. Se borrará el producto con todas sus variantes e imágenes.
            </p>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    @click="deleteProduct"
                >
                    Sí, Eliminar Producto
                </DangerButton>
            </div>
        </div>
    </Modal>
    
    <AlertModal
        :show="showProductNotice"
        type="success"
        title="Producto"
        :message="productNotice"
        primary-text="Entendido"
        @primary="showProductNotice=false"
        @close="showProductNotice=false"
    />
</template>