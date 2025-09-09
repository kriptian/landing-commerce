<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '', // El nombre de la categoría principal
    subcategories: [], // Un array para los nombres de las subcategorías
});

// Función para añadir un nuevo campo de subcategoría
const addSubcategory = () => {
    form.subcategories.push({ name: '' });
};

// Función para eliminar un campo de subcategoría
const removeSubcategory = (index) => {
    form.subcategories.splice(index, 1);
};

const submit = () => {
    form.post(route('admin.categories.store'), {
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Crear Categoría y Subcategorías" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear Nueva Categoría</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit">
                            <div class="mb-6 border-b pb-6">
                                <label for="name" class="block font-medium text-lg text-gray-800">Nombre de la Categoría Principal</label>
                                <input id="name" v-model="form.name" type="text" class="block mt-2 w-full rounded-md shadow-sm border-gray-300" required>
                                <p v-if="form.errors.name" class="text-sm text-red-600 mt-2">{{ form.errors.name }}</p>
                            </div>

                            <div class="mt-6">
                                <h3 class="font-medium text-lg text-gray-800">Subcategorías (Opcional)</h3>

                                <div v-for="(subcategory, index) in form.subcategories" :key="index" class="flex items-center gap-4 mt-4">
                                    <input 
                                        v-model="subcategory.name" 
                                        type="text" 
                                        :placeholder="`Nombre de la Subcategoría ${index + 1}`"
                                        class="block w-full rounded-md shadow-sm border-gray-300"
                                    >
                                    <button @click.prevent="removeSubcategory(index)" type="button" class="bg-red-500 text-white font-bold p-2 rounded-full h-8 w-8 flex items-center justify-center">
                                        -
                                    </button>
                                </div>
                                <p v-if="form.errors.subcategories" class="text-sm text-red-600 mt-2">{{ form.errors.subcategories }}</p>


                                <button @click.prevent="addSubcategory" type="button" class="mt-4 bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded hover:bg-gray-300">
                                    + Añadir Subcategoría
                                </button>
                            </div>

                            <div class="flex items-center justify-end mt-8 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Guardar Todo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>