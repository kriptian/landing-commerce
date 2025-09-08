<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue'; // Se necesita para la reactividad

const props = defineProps({
    product: Object,
    categories: Array,
});

// --- INICIO DE LA NUEVA LÓGICA ---

// Hacemos una copia "reactiva" de las imágenes para poder borrarlas de la vista al instante
const currentImages = ref([...props.product.images]);

// Preparamos la cadena de texto de las especificaciones (esto ya lo tenías bien)
let specificationsText = '';
if (props.product.specifications) {
    try {
        specificationsText = JSON.parse(props.product.specifications).join(', ');
    } catch (e) {
        console.error('Error al procesar las especificaciones:', e);
        specificationsText = '';
    }
}

// Ajustamos el formulario para manejar los cambios en las imágenes
const form = useForm({
    _method: 'PUT', // Truco para que Laravel entienda que es una actualización con archivos
    name: props.product.name,
    price: props.product.price,
    category_id: props.product.category_id,
    short_description: props.product.short_description,
    long_description: props.product.long_description,
    specifications: specificationsText,
    
    // Nuevos campos para manejar las imágenes
    new_gallery_files: [], // Aquí guardaremos los archivos nuevos que se suban
    images_to_delete: [],  // Aquí los IDs de las imágenes que se van a borrar
});

// Función para marcar una imagen para ser borrada
const markImageForDeletion = (image) => {
    // 1. La agregamos a la lista de IDs que se enviará al backend
    form.images_to_delete.push(image.id);
    // 2. La quitamos de la vista para que el usuario vea que se va a borrar
    currentImages.value = currentImages.value.filter(img => img.id !== image.id);
};

const submit = () => {
    // IMPORTANTE: Para enviar archivos, Inertia siempre usa POST.
    // El "_method: 'PUT'" de arriba le dice a Laravel que lo trate como una actualización.
    form.post(route('admin.products.update', props.product.id));
};
// --- FIN DE LA NUEVA LÓGICA ---
</script>

<template>
    <Head title="Editar Producto" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Producto: {{ product.name }}</h2>
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
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
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
                                </div>
                            </div>
                            
                            <div class="mt-6 border-t pt-6">
                                <div class="mb-4 col-span-2">
                                    <label class="block font-medium text-sm text-gray-700">Imágenes Actuales</label>
                                    <div v-if="currentImages.length > 0" class="mt-2 grid grid-cols-3 md:grid-cols-5 gap-4">
                                        <div v-for="image in currentImages" :key="image.id" class="relative group">
                                            <img :src="image.path" class="rounded-md aspect-square object-cover">
                                            <button @click.prevent="markImageForDeletion(image)" type="button" class="absolute top-1 right-1 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                &times;
                                            </button>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-500 mt-2">Este producto no tiene imágenes.</p>
                                </div>

                                <div class="mb-4 col-span-2">
                                    <label for="new_gallery_files" class="block font-medium text-sm text-gray-700">Añadir más imágenes</label>
                                    <input 
                                        id="new_gallery_files" 
                                        @input="form.new_gallery_files = $event.target.files" 
                                        type="file" 
                                        multiple 
                                        class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                    >
                                    <p v-if="form.errors.new_gallery_files" class="text-sm text-red-600 mt-2">{{ form.errors.new_gallery_files }}</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Actualizar Producto
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>