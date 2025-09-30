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

                        <div class="overflow-x-auto">
                        <table class="min-w-[600px] w-full divide-y divide-gray-200 table-auto">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="categories.length === 0">
                                    <td colspan="3" class="px-3 py-3 sm:px-6 sm:py-4 text-center text-gray-500">No hay categorías creadas.</td>
                                </tr>
                                <tr v-for="category in categories" :key="category.id">
                                    <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ category.id }}</td>
                                    <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ category.name }}</td>
                                    <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center gap-3">
                                            <Link :href="route('admin.categories.edit', category.id)" class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1">
                                                <svg class="w-5 h-5 sm:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                                <span class="hidden sm:inline">Editar</span>
                                            </Link>
                                            <button @click="confirmCategoryDeletion(category.id)" class="text-red-600 hover:text-red-900 inline-flex items-center gap-1">
                                                <svg class="w-5 h-5 sm:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/></svg>
                                                <span class="hidden sm:inline">Eliminar</span>
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