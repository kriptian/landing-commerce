<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref, computed, watch } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    categories: Array, 
});

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const showSaved = ref(page?.props?.flash?.success ? true : false);
const showErrors = ref(false);
const errorMessages = ref([]);

// Validación previa en el navegador
const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2 MB
const onGalleryInput = (event) => {
    const files = Array.from(event?.target?.files || []);
    const msgs = [];
    const validFiles = [];
    for (const file of files) {
        const isImage = (file?.type || '').startsWith('image/');
        if (!isImage) {
            msgs.push(`${file.name}: no es una imagen válida.`);
            continue;
        }
        if (file.size > MAX_IMAGE_BYTES) {
            msgs.push(`${file.name}: supera el límite de 2 MB.`);
            continue;
        }
        validFiles.push(file);
    }
    if (msgs.length) {
        errorMessages.value = ['Problemas con las imágenes seleccionadas:', ...msgs];
        showErrors.value = true;
        // Limpiar selección para evitar enviar archivos inválidos
        if (event?.target) event.target.value = '';
        form.gallery_files = [];
        return;
    }
    form.gallery_files = validFiles;
};

// --- Lógica de menús dependientes (Sigue igual) ---
const selectedParentId = ref(null); 
const subcategories = computed(() => {
    if (!selectedParentId.value) return [];
    const parent = props.categories.find(c => c.id === selectedParentId.value);
    return parent ? parent.children : [];
});
watch(selectedParentId, () => {
    form.category_id = null;
});
// --- FIN Lógica ---

const form = useForm({
    name: '',
    price: '',
    track_inventory: true,
    quantity: 0,
    minimum_stock: 0,
    alert: null,
    category_id: null, 
    short_description: '',
    long_description: '',
    specifications: '',
    gallery_files: [],
    variants: [], 
});

// --- Lógica de Variantes (Sigue igual) ---
const addVariant = () => {
    form.variants.push({ 
        options_text: '', 
        price: '', 
        stock: 0,
        minimum_stock: 1,
        alert: null,
    });
};
const removeVariant = (index) => {
    form.variants.splice(index, 1);
};
// --- FIN Lógica Variantes ---


// --- Lógica de Stock Total (Sigue igual) ---
const totalQuantity = computed(() => {
    if (form.variants.length === 0) return Number(form.quantity) || 0;
    return form.variants.reduce((t, v) => t + (Number(v.stock) || 0), 0);
});
watch(totalQuantity, (newTotal) => {
    if (form.variants.length > 0) form.quantity = newTotal;
});
// --- FIN Lógica Stock ---


// ===== LÓGICA NUEVA PARA SUMAR EL STOCK MÍNIMO =====
const totalMinimumStock = computed(() => {
    if (form.variants.length === 0) return Number(form.minimum_stock) || 0;
    return form.variants.reduce((t, v) => t + (Number(v.minimum_stock) || 0), 0);
});

// Quitamos la regla que limitaba stock actual <= stock mínimo


const submit = () => {
    form.post(route('admin.products.store'), {
        preserveScroll: true,
        onError: async () => {
            // Llevar el foco/scroll al primer error y dejar claro qué falta
            await nextTick();
            // Construir mensajes legibles
            const msgs = [];
            for (const [key, val] of Object.entries(form.errors)) {
                if (key.startsWith('gallery_files.') && String(val).includes('The gallery files.')) {
                    msgs.push('Una o más imágenes superan el tamaño máximo permitido (2 MB) o no son válidas.');
                } else if (key.startsWith('new_gallery_files.')) {
                    msgs.push('Una o más imágenes nuevas superan el tamaño máximo permitido (2 MB) o no son válidas.');
                } else {
                    msgs.push(String(val));
                }
            }
            errorMessages.value = msgs;
            showErrors.value = msgs.length > 0;
            const firstErrorField = Object.keys(form.errors)[0];
            const el = document.getElementById(firstErrorField);
            if (el && typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.focus?.();
            }
        },
        onSuccess: () => {
            showSaved.value = true;
            form.reset();
        }
    });
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
                                    <label for="price" class="block font-medium text-sm text-gray-700">Precio (Principal)</label>
                                    <input id="price" v-model="form.price" type="number" step="0.01" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                </div>

                                <div class="p-4 border rounded-md bg-gray-50">
                                    <label class="inline-flex items-center gap-2 mb-3 select-none">
                                        <input type="checkbox" v-model="form.track_inventory" class="rounded">
                                        <span class="font-semibold">Controlar inventario</span>
                                    </label>
                                    <h4 class="font-semibold text-gray-800 mb-2">Inventario General</h4>
                                    <p class="text-xs text-gray-500 mb-4">
                                        Si creas variantes, estos totales se calcularán solos.
                                    </p>
                                    <div v-if="form.track_inventory && form.variants.length === 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="quantity" class="block font-medium text-sm text-gray-700">Inventario (Total)</label>
                                            <input 
                                                id="quantity" 
                                                v-model="form.quantity" 
                                                type="number" 
                                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                                :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                :disabled="form.variants.length > 0"  
                                            />
                                            <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">{{ form.errors.quantity }}</p>
                                            <p v-else class="text-xs text-gray-500 mt-1">Stock si no hay variantes.</p>
                                        </div>
                                        <div>
                                            <label for="minimum_stock" class="block font-medium text-sm text-gray-700">Mínimo en Stock</label>
                                            <input 
                                                id="minimum_stock" 
                                                v-model="form.minimum_stock" 
                                                type="number" 
                                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                :disabled="form.variants.length > 0"
                                            />
                                            <p v-if="form.errors.minimum_stock" class="mt-1 text-sm text-red-600">{{ form.errors.minimum_stock }}</p>
                                            <p v-else class="text-xs text-gray-500 mt-1">Alerta de bajo stock (general).</p>
                                        </div>
                                        <div>
                                            <label for="alert" class="block font-medium text-sm text-gray-700">Alerta (Opcional)</label>
                                            <input 
                                                id="alert" 
                                                v-model="form.alert" 
                                                type="number" 
                                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                :disabled="form.variants.length > 0"
                                            />
                                            <p class="text-xs text-gray-500 mt-1">Para producto único. Con variantes, usá las alertas por variante.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 mb-4">
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
                                    <p v-if="form.errors.category_id" class="mt-1 text-sm text-red-600">{{ form.errors.category_id }}</p>
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
                                    <input id="gallery_files" @input="onGalleryInput($event)" type="file" multiple class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <p class="mt-1 text-xs text-gray-500">Formatos de imagen y hasta 2 MB por archivo.</p>
                                </div>
                            </div>
                            
                                <div class="md:col-span-2 mt-6 border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-900">Variantes del Producto</h3>
                                <p class="text-sm text-gray-600 mb-4">
                                    Añadí cada combinación como una variante separada.
                                </p>
                                <div class="hidden md:grid grid-cols-6 gap-4 mb-2 text-sm font-medium text-gray-600">
                                    <div class="col-span-2">Opciones (ej: Color:Rojo)</div>
                                    <div>Precio (Opcional)</div>
                                    <div v-if="form.track_inventory">Stock Actual</div>
                                    <div v-if="form.track_inventory">Stock Mínimo</div>
                                    <div v-if="form.track_inventory">Alerta (Opcional)</div>
                                </div>
                                <div v-for="(variant, index) in form.variants" :key="index" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-center mb-2">
                                    <div class="md:col-span-2">
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
                                            placeholder="Usa precio principal"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                    </div>
                                    <div v-if="form.track_inventory">
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Stock Actual</label>
                                        <input 
                                            type="number" 
                                            v-model="variant.stock"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                        <p v-if="form.errors[`variants.${index}.stock`]" class="mt-1 text-sm text-red-600">{{ form.errors[`variants.${index}.stock`] }}</p>
                                    </div>
                                    
                                    <div v-if="form.track_inventory">
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Stock Mínimo</label>
                                        <input 
                                            type="number" 
                                            v-model="variant.minimum_stock" 
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                        <p v-if="form.errors[`variants.${index}.minimum_stock`]" class="mt-1 text-sm text-red-600">{{ form.errors[`variants.${index}.minimum_stock`] }}</p>
                                    </div>

                                    <div v-if="form.track_inventory">
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Alerta (Opcional)</label>
                                        <input 
                                            type="number" 
                                            v-model="variant.alert" 
                                            placeholder="Ej: 5"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                    </div>
                                    
                                    <button @click="removeVariant(index)" type="button" class="text-red-600 hover:text-red-800 text-sm self-end pb-2 md:pb-0">
                                        Quitar
                                    </button>
                                </div>
                                <button @click="addVariant" type="button" class="mt-4 text-sm font-medium text-blue-600 hover:text-blue-800">
                                    + Añadir Variante
                                </button>
                            </div>
                            
                            <div class="md:col-span-2 flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Guardar Producto
                                </button>
                                <span v-if="Object.keys(form.errors).length" class="ml-3 text-sm text-red-600">Por favor corrige los campos marcados.</span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <AlertModal
        :show="showSaved"
        type="success"
        title="¡Producto creado con éxito!"
        primary-text="Entendido"
        @primary="showSaved=false"
        @close="showSaved=false"
    />

    <AlertModal
        :show="showErrors"
        type="error"
        title="No pudimos guardar el producto"
        message="Revisá los siguientes puntos:"
        :messages="errorMessages"
        primary-text="Entendido"
        @primary="showErrors=false"
        @close="showErrors=false"
    />
</template>