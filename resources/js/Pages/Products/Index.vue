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
    router.get(route('admin.products.index'), { search: search.value, category: selectedCategory.value }, { preserveState: true, replace: true, preserveScroll: true });
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
                            <p class="shrink-0">Aquí podés crear, editar y eliminar los productos de tu tienda.</p>
                            <div class="flex flex-wrap items-center gap-3">
                                <input type="text" v-model="search" @input="applyFilters" placeholder="Buscar productos..." class="border rounded px-3 py-2 text-sm h-10" />
                                <select v-model="selectedCategory" @change="applyFilters" class="border rounded px-2 py-2 text-sm h-10 min-w-[170px] max-w-[220px]">
                                    <option value="">Todas las categorías</option>
                                    <option v-for="c in props.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
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
                                <Link :href="route('admin.products.create')" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Crear Nuevo Producto
                                </Link>
                            </div>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Promoción</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="products.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No hay productos creados.</td>
                                </tr>
                                <tr v-for="product in products" :key="product.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ product.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">$ {{ Number(product.price).toLocaleString('es-CO') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ product.quantity }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ product.category ? product.category.name : 'Sin Categoría' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <label class="inline-flex items-center gap-1 text-sm">
                                                <input type="checkbox" :checked="product.promo_active" @change="togglePromo(product)" class="rounded border-gray-300">
                                                Activa
                                            </label>
                                            <input type="number" min="1" max="90" :value="product.promo_discount_percent ?? ''" @input="updatePromoPercent(product, $event.target.value)" placeholder="%" class="w-16 text-sm border rounded px-1 py-0.5">
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">

                                        <Link :href="route('admin.products.edit', product.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>

                                        <button @click="confirmProductDeletion(product.id)" class="ml-4 text-red-600 hover:text-red-900">
                                            Eliminar
                                        </button>

                                    </td>
                                </tr>
                            </tbody>
                        </table>

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