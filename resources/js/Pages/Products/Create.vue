<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue'; // <-- Se necesitan estos para la magia

const props = defineProps({
    categories: Array, // Recibimos las categorías principales con sus hijos
});

// --- INICIO: Lógica para los menús dependientes ---
const selectedParentId = ref(null); // Aquí guardamos el ID de la categoría principal que elijan

// Esta es una propiedad "calculada". Nos da la lista de subcategorías
// basándose en la categoría principal que se haya seleccionado.
const subcategories = computed(() => {
    if (!selectedParentId.value) return [];
    const parent = props.categories.find(c => c.id === selectedParentId.value);
    return parent ? parent.children : [];
});

// Esto es un "vigilante". Si el usuario cambia de categoría principal,
// resetea la selección de la subcategoría.
watch(selectedParentId, () => {
    form.category_id = null;
});
// --- FIN: Lógica para los menús dependientes ---

const form = useForm({
    name: '',
    price: '',
    quantity: 0,
    category_id: null, // IMPORTANTE: Ahora este campo guardará el ID de la SUBCATEGORÍA
    short_description: '',
    long_description: '',
    specifications: '',
    gallery_files: [],
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
                        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
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
                                    <label for="quantity" class="block font-medium text-sm text-gray-700">Cantidad en Inventario</label>
                                    <input id="quantity" v-model="form.quantity" type="number" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="parent_category" class="block font-medium text-sm text-gray-700">Categoría Principal</label>
                                    <select id="parent_category" v-model="selectedParentId" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                                        <option :value="null">Seleccione una categoría</option>
                                        <option v-for="category in categories" :key="category.id" :value="category.id">
                                            {{ category.name }}
                                        </option>
                                    </select>
                                </div>
                                <div v-if="subcategories.length > 0" class="mb-4">
                                    <label for="category_id" class="block font-medium text-sm text-gray-700">Subcategoría</label>
                                    <select id="category_id" v-model="form.category_id" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                        <option :value="null">Seleccione una subcategoría</option>
                                        <option v-for="subcategory in subcategories" :key="subcategory.id" :value="subcategory.id">
                                            {{ subcategory.name }}
                                        </option>
                                    </select>
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
                                <div class="mb-4">
                                    <label for="gallery_files" class="block font-medium text-sm text-gray-700">Imágenes de la Galería</label>
                                    <input @input="form.gallery_files = $event.target.files" type="file" multiple class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                            </div>
                            
                            <div class="md:col-span-2 flex items-center justify-end mt-6 border-t pt-6">
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