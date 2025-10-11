<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue'; // <-- Importamos ref
// --- 1. IMPORTAMOS LOS COMPONENTES DEL MODAL ---
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AlertModal from '@/Components/AlertModal.vue';
// ---------------------------------------------

const props = defineProps({
    products: Array,
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
    router.get(route('admin.products.index'), { search: search.value, category: selectedCategory.value, status: selectedStatus.value }, { preserveState: true, replace: true, preserveScroll: true });
};
// ---------------------------------------------
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

                        <div class="overflow-x-auto">
                            <table class="min-w-[900px] w-full divide-y divide-gray-200 table-auto">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Activo</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Promoción</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="products.length === 0">
                                        <td colspan="6" class="px-3 py-3 sm:px-6 sm:py-4 text-center text-gray-500">No hay productos creados.</td>
                                    </tr>
                                    <tr v-for="product in products" :key="product.id">
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">
                                            <span>{{ product.name }}</span>
                                            <span v-if="!product.is_active" class="ml-2 inline-flex items-center rounded bg-gray-200 text-gray-700 text-xs font-semibold px-2 py-0.5">Inactivo</span>
                                        </td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">$ {{ Number(product.price).toLocaleString('es-CO') }}</td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ product.quantity }}</td>
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