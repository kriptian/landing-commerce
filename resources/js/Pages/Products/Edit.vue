<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    product: Object,
    categories: Array, 
});

// --- Lógica de menús dependientes (Sigue igual) ---
const initialParent = props.product.category.parent_id 
    ? props.product.category.parent 
    : props.product.category;
const initialSubcategory = props.product.category.parent_id 
    ? props.product.category 
    : null;
const selectedParentId = ref(initialParent ? initialParent.id : null);
const subcategories = computed(() => {
    if (!selectedParentId.value) return [];
    const parent = props.categories.find(c => c.id === selectedParentId.value);
    return parent ? parent.children : [];
});
watch(selectedParentId, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        form.category_id = null;
    }
});
// --- FIN Lógica ---


// --- Lógica de Variantes (Sigue igual) ---
const convertJsonToText = (optionsJson) => {
    return Object.entries(optionsJson).map(([key, value]) => `${key}:${value}`).join(', ');
};

const initialVariants = props.product.variants.map(variant => {
    return {
        id: variant.id, 
        options_text: convertJsonToText(variant.options),
        price: variant.price ?? '', 
        stock: variant.stock,
    };
});
// --- FIN Lógica Variantes ---


const form = useForm({
    _method: 'PUT', 
    name: props.product.name,
    price: props.product.price,
    quantity: props.product.quantity, // Este se actualizará solo
    category_id: props.product.category_id,
    short_description: props.product.short_description,
    long_description: props.product.long_description,
    specifications: props.product.specifications ? JSON.parse(props.product.specifications).join(', ') : '',
    new_gallery_files: [],
    images_to_delete: [],
    variants: initialVariants, 
    variants_to_delete: [], 
});

// --- Lógica de Imágenes (Sigue igual) ---
const currentImages = ref([...props.product.images]);
const markImageForDeletion = (image) => {
    form.images_to_delete.push(image.id);
    currentImages.value = currentImages.value.filter(img => img.id !== image.id);
};

// --- Lógica de Añadir/Quitar Variantes (Sigue igual) ---
const addVariant = () => {
    form.variants.push({ 
        id: null, 
        options_text: '', 
        price: '', 
        stock: 0 
    });
};

const removeVariant = (index) => {
    const variant = form.variants[index];
    if (variant.id) {
        form.variants_to_delete.push(variant.id);
    }
    form.variants.splice(index, 1);
};
// --- FIN Lógica Variantes ---

// --- Lógica de Stock Total (Sigue igual) ---
const totalQuantity = computed(() => {
    // Si hay variantes, el stock es la suma de las variantes
    if (form.variants.length > 0) {
        let total = 0;
        form.variants.forEach(variant => {
            total += Number(variant.stock) || 0;
        });
        return total;
    }
    // Si NO hay variantes, el stock es el que está en el campo general
    return Number(form.quantity) || 0; 
});

// Vigilamos la suma total.
watch(totalQuantity, (newTotal) => {
    // Si hay variantes, actualizamos el form.quantity
    if (form.variants.length > 0) {
        form.quantity = newTotal;
    }
    // Si no hay variantes, no hacemos nada, dejamos que el 'form.quantity'
    // mantenga su valor original (que sí se puede editar manualmente)
});
// --- FIN Lógica Stock ---

const submit = () => {
    form.post(route('admin.products.update', props.product.id));
};
</script>

<template>
    <Head title="Editar Producto" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Producto: {{ form.name }}</h2>
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
                                        <label for="price" class="block font-medium text-sm text-gray-700">Precio (Principal)</label>
                                        <input id="price" v-model="form.price" type="number" step="0.01" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="quantity" class="block font-medium text-sm text-gray-700">Cantidad en Inventario (Total)</label>
                                        <input 
                                            id="quantity" 
                                            v-model="form.quantity" 
                                            type="number" 
                                            class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                            :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                            :disabled="form.variants.length > 0" 
                                            required>
                                        <p v-if="form.variants.length > 0" class="text-xs text-gray-500 mt-1">El total se calcula automáticamente sumando el stock de las variantes.</p>
                                        <p v-else class="text-xs text-gray-500 mt-1">Este es el stock general. Se deshabilitará si añades variantes.</p>
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
                                        <input id="specifications" v-model="form.specifications" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-2 mt-6 border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-900">Variantes del Producto</h3>
                                <p class="text-sm text-gray-600 mb-4">
                                    Editá, añadí o eliminá las combinaciones de variantes.
                                </p>

                                <div class="hidden md:grid grid-cols-4 gap-4 mb-2 text-sm font-medium text-gray-600">
                                    <div class="col-span-2">Opciones (ej: Color:Rojo, Talla:M)</div>
                                    <div>Precio (Opcional)</div>
                                    <div>Stock</div>
                                </div>

                                <div v-for="(variant, index) in form.variants" :key="index" class="grid grid-cols-4 gap-4 items-center mb-2">
                                    <div class="col-span-4 md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Opciones</label>
                                        <input 
                                            type="text" 
                                            v-model="variant.options_text" 
                                            placeholder="Color:Rojo, Talla:M"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Precio (Opcional)</label>
                                        <input 
                                            type="number" 
                                            step="0.01"
                                            v-model="variant.price" 
                                            placeholder="Dejar vacío usa precio principal"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Stock</label>
                                        <input 
                                            type="number" 
                                            v-model="variant.stock" 
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                    </div>

                                    <button @click="removeVariant(index)" type="button" class="text-red-600 hover:text-red-800 text-sm self-end pb-2">
                                        Quitar
                                    </button>
                                </div>

                                <button @click="addVariant" type="button" class="mt-4 text-sm font-medium text-blue-600 hover:text-blue-800">
                                    + Añadir Variante
                                </button>
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