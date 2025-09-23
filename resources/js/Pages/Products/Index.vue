<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue'; // <-- Importamos ref
// --- 1. IMPORTAMOS LOS COMPONENTES DEL MODAL ---
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useToast } from 'vue-toastification';
// ---------------------------------------------

defineProps({
    products: Array,
});

// --- 2. LÓGICA NUEVA PARA MANEJAR EL MODAL ---
const confirmingProductDeletion = ref(false);
const productToDelete = ref(null);
const toast = useToast();
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
            toast.success('¡Producto eliminado con éxito!'); // <-- LA LÍNEA NUEVA
        }
    });
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

                        <div class="flex justify-between items-center mb-4">
                            <p>Aquí podés crear, editar y eliminar los productos de tu tienda.</p>
                            <Link :href="route('admin.products.create')" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                Crear Nuevo Producto
                            </Link>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
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
</template>