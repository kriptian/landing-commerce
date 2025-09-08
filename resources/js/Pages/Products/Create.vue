<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    categories: Array,
});

const form = useForm({
    name: '',
    price: '',
    category_id: null,
    short_description: '',
    long_description: '',
    specifications: '',
    main_image_url: '', // Este lo dejamos por si quieren pegar una URL principal
    gallery_files: [], // Array para guardar los archivos de la galería
});

const submit = () => {
    form.post(route('admin.products.store'));
};
</script>

<template>
    <Head title="Crear Producto" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear Nuevo Producto</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="mb-4">
                                        <label for="name" class="block font-medium text-sm text-gray-700">Nombre</label>
                                        <input id="name" v-model="form.name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="price" class="block font-medium text-sm text-gray-700">Precio</label>
                                        <input id="price" v-model="form.price" type="number" step="0.01" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="category_id" class="block font-medium text-sm text-gray-700">Categoría</label>
                                        <select id="category_id" v-model="form.category_id" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                            <option :value="null" disabled>Selecciona una categoría</option>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label for="gallery_files" class="block font-medium text-sm text-gray-700">Cargar Imágenes para la Galería</label>
                                        <input id="gallery_files" type="file" @input="form.gallery_files = $event.target.files" multiple class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                        <p class="text-xs text-gray-500 mt-1">Podés seleccionar varias fotos a la vez.</p>
                                    </div>
                                    
                                </div>

                                <div>
                                    <div class="mb-4">
                                        <label for="short_description" class="block font-medium text-sm text-gray-700">Descripción Corta</label>
                                        <textarea id="short_description" v-model="form.short_description" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" rows="3"></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label for="long_description" class="block font-medium text-sm text-gray-700">Descripción Larga</label>
                                        <textarea id="long_description" v-model="form.long_description" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" rows="5"></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label for="specifications" class="block font-medium text-sm text-gray-700">Especificaciones (separadas por comas)</label>
                                        <input id="specifications" v-model="form.specifications" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" placeholder="Alto: 2.30m,Ancho: 1.20m,...">
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Guardar Producto
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>