<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    category: Object, // Recibimos la categoría con sus hijos o padre ya cargado
});

// Formulario para editar el nombre de la categoría actual
const form = useForm({
    name: props.category.name,
});

// Formulario para añadir una NUEVA subcategoría
const newSubcategoryForm = useForm({
    name: '',
});

const updateCategoryName = () => {
    form.put(route('admin.categories.update', props.category.id));
};

const addNewSubcategory = () => {
    newSubcategoryForm.post(route('admin.categories.storeSubcategory', props.category.id), {
        onSuccess: () => newSubcategoryForm.reset(),
        preserveScroll: true,
    });
};

// Confirmación elegante para eliminar subcategorías
import { ref } from 'vue';
const confirmingDeletion = ref(false);
const categoryToDelete = ref(null);

const askDeleteCategory = (categoryId) => {
    categoryToDelete.value = categoryId;
    confirmingDeletion.value = true;
};

const closeDeleteModal = () => {
    confirmingDeletion.value = false;
    categoryToDelete.value = null;
};

const deleteCategory = () => {
    if (!categoryToDelete.value) return;
    router.delete(route('admin.categories.destroy', categoryToDelete.value), {
        preserveScroll: true,
        onFinish: () => closeDeleteModal(),
    });
};
</script>

<template>
    <Head :title="`Editar: ${category.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Editando Categoría: {{ category.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium">Editar Nombre</h3>
                        <form @submit.prevent="updateCategoryName" class="mt-4 flex items-center gap-4">
                            <input v-model="form.name" type="text" class="block w-full rounded-md shadow-sm border-gray-300">
                            <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                Actualizar Nombre
                            </button>
                        </form>
                    </div>
                </div>

                <div v-if="!category.parent_id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium">Gestionar Subcategorías</h3>

                        <div class="mt-4 space-y-2">
                            <p v-if="!category.children || category.children.length === 0" class="text-sm text-gray-500">
                                Esta categoría aún no tiene subcategorías.
                            </p>
                            <div v-for="child in category.children" :key="child.id" class="flex justify-between items-center p-2 border rounded-md">
                                <span>{{ child.name }}</span>
                                <div>
                                    <Link :href="route('admin.categories.edit', child.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">Editar</Link>
                                    <button @click="askDeleteCategory(child.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="addNewSubcategory" class="mt-6 border-t pt-4">
                            <label class="block font-medium text-sm text-gray-700">Añadir Nueva Subcategoría</label>
                            <div class="mt-2 flex items-center gap-4">
                                <input v-model="newSubcategoryForm.name" type="text" class="block w-full rounded-md shadow-sm border-gray-300" placeholder="Nombre de la nueva subcategoría">
                                <button type="submit" :disabled="newSubcategoryForm.processing" class="bg-green-500 text-white font-bold py-2 px-4 rounded hover:bg-green-700">
                                    Añadir
                                </button>
                            </div>
                            <p v-if="newSubcategoryForm.errors.name" class="text-sm text-red-600 mt-2">{{ newSubcategoryForm.errors.name }}</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="confirmingDeletion" @close="closeDeleteModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Querés eliminar esta subcategoría?
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Esta acción es irreversible y removerá la subcategoría seleccionada.
            </p>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeDeleteModal">Cancelar</SecondaryButton>
                <DangerButton class="ms-3" @click="deleteCategory">Sí, eliminar</DangerButton>
            </div>
        </div>
    </Modal>
</template>