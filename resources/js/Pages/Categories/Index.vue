<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue'; // <-- Importamos ref
// --- 1. IMPORTAMOS LO QUE NECESITAMOS ---
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AlertModal from '@/Components/AlertModal.vue';
// ------------------------------------------

defineProps({
    categories: Array,
});

const showNotice = ref(false);
const noticeMessage = ref('');

// --- 2. LÓGICA NUEVA PARA MANEJAR EL MODAL ---
const confirmingCategoryDeletion = ref(false);
const categoryToDelete = ref(null);

const confirmCategoryDeletion = (id) => {
    categoryToDelete.value = id;
    confirmingCategoryDeletion.value = true;
};

const closeModal = () => {
    confirmingCategoryDeletion.value = false;
    categoryToDelete.value = null;
};

// Renombramos la función 'destroy' a 'deleteCategory'
const deleteCategory = () => {
    router.delete(route('admin.categories.destroy', categoryToDelete.value), {
        preserveScroll: true,
        onSuccess: () => { closeModal(); noticeMessage.value = '¡Categoría eliminada con éxito!'; showNotice.value = true; },
        onError: () => { closeModal(); noticeMessage.value = 'Hubo un error al eliminar la categoría.'; showNotice.value = true; }
    });
};
// ---------------------------------------------
</script>

<template>
    <Head title="Gestionar Categorías" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestionar Categorías</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">

                        <div class="flex justify-between items-center mb-4">
                            <p>Aquí podés crear, editar y eliminar las categorías de tus productos.</p>

                            <Link :href="route('admin.categories.create')" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                Crear Nueva
                            </Link>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="categories.length === 0">
                                    <td colspan="3" class="px-6 py-4 text-center text-gray-500">No hay categorías creadas.</td>
                                </tr>
                                <tr v-for="category in categories" :key="category.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ category.id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ category.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">

                                        <Link :href="route('admin.categories.edit', category.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>

                                        <button @click="confirmCategoryDeletion(category.id)" class="ml-4 text-red-600 hover:text-red-900">
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
    
    <Modal :show="confirmingCategoryDeletion" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Estás seguro de que querés eliminar esta categoría?
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Esta acción es irreversible. Se borrará la categoría y todas sus subcategorías.
            </p>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    @click="deleteCategory"
                >
                    Sí, Eliminar Categoría
                </DangerButton>
            </div>
        </div>
    </Modal>

    <AlertModal
        :show="showNotice"
        type="info"
        title="Categorías"
        :message="noticeMessage"
        primary-text="Entendido"
        @primary="showNotice=false"
        @close="showNotice=false"
    />
</template>