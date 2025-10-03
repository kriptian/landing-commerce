<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    product: Object,
    categories: Array, 
    selectedCategoryPath: Array,
});

const page = usePage();
const showSaved = ref(page?.props?.flash?.success ? true : false);
const successMessage = ref(page?.props?.flash?.success || '');
const confirmingSave = ref(false);
const showErrors = ref(false);
const errorMessages = ref([]);

// Validación previa en el navegador
const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2 MB
const onNewGalleryInput = (event) => {
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
        if (event?.target) event.target.value = '';
        form.new_gallery_files = [];
        return;
    }
    form.new_gallery_files = validFiles;
};

// --- Selects en cascada multi-nivel (con preselección) ---
const levels = ref([ { options: props.categories, selected: null } ]);
const hydratePath = async () => {
    // Preseleccionamos desde la raíz a la hoja
    if (!Array.isArray(props.selectedCategoryPath) || props.selectedCategoryPath.length === 0) return;
    // Nivel 0 ya tiene opciones: categorías raíz
    for (let i = 0; i < props.selectedCategoryPath.length; i++) {
        const node = props.selectedCategoryPath[i];
        // Seleccionar en el nivel i
        if (!levels.value[i]) {
            levels.value[i] = { options: [], selected: null };
        }
        // Si el nivel i no tiene opciones (i>0), cargamos hijos del seleccionado anterior
        if (i > 0 && levels.value[i].options.length === 0) {
            const prevSelectedId = levels.value[i-1].selected;
            if (prevSelectedId) {
                const res = await fetch(route('admin.categories.children', prevSelectedId));
                const json = await res.json();
                levels.value[i].options = Array.isArray(json.data) ? json.data : [];
            }
        }
        // Establecer selección del nivel i
        levels.value[i].selected = node.id;
        form.category_id = node.id;
        // Asegurar que exista un nivel i+1 si hay más por seleccionar
        if (i < props.selectedCategoryPath.length - 1) {
            if (!levels.value[i+1]) levels.value[i+1] = { options: [], selected: null };
        }
    }
    // Finalmente, ofrecer hijos del último seleccionado
    const lastId = form.category_id;
    if (lastId) {
        const res = await fetch(route('admin.categories.children', lastId));
        const json = await res.json();
        const children = Array.isArray(json.data) ? json.data : [];
        if (children.length > 0) {
            levels.value.push({ options: children, selected: null });
        }
    }
};

const removeDeeperLevels = (levelIndex) => {
    levels.value.splice(levelIndex + 1);
};
const onSelectAtLevel = async (levelIndex) => {
    const selectedId = levels.value[levelIndex].selected;
    removeDeeperLevels(levelIndex);
    form.category_id = selectedId || null;
    if (!selectedId) return;
    const res = await fetch(route('admin.categories.children', selectedId));
    if (!res.ok) return;
    const json = await res.json();
    const children = Array.isArray(json.data) ? json.data : [];
    if (children.length > 0) {
        levels.value.push({ options: children, selected: null });
    }
};
// --- FIN Selects en cascada ---


// --- Lógica de Variantes (ajustada) ---
const convertJsonToText = (optionsJson) => {
    return Object.entries(optionsJson).map(([key, value]) => `${key}:${value}`).join(', ');
};

const initialVariants = props.product.variants.map(variant => {
    return {
        id: variant.id, 
        options_text: convertJsonToText(variant.options),
        price: variant.price ?? '', 
        stock: variant.stock,
        minimum_stock: variant.minimum_stock, // <-- 1. CARGAMOS EL STOCK MÍNIMO EXISTENTE
        alert: variant.alert ?? null,
    };
});
// --- FIN Lógica Variantes ---


const form = useForm({
    _method: 'PUT', 
    name: props.product.name,
    price: props.product.price,
    track_inventory: props.product.track_inventory ?? true,
    quantity: props.product.quantity,
    minimum_stock: props.product.minimum_stock, // <-- 2. CAMPO NUEVO EN EL FORM
    alert: props.product.alert ?? null,
    category_id: props.product.category_id,
    short_description: props.product.short_description,
    long_description: props.product.long_description,
    specifications: props.product.specifications ? JSON.parse(props.product.specifications).join(', ') : '',
    new_gallery_files: [],
    images_to_delete: [],
    variants: initialVariants, 
    variants_to_delete: [], 
});

// --- Lógica de Imágenes (sigue igual) ---
const currentImages = ref([...props.product.images]);
const markImageForDeletion = (image) => {
    form.images_to_delete.push(image.id);
    currentImages.value = currentImages.value.filter(img => img.id !== image.id);
};

// ===== Galería en modal (para más de 4 imágenes) =====
const showGalleryModal = ref(false);
const currentImageIndex = ref(0);
const isMobile = ref(false);
function updateIsMobile() { isMobile.value = window.innerWidth < 768; }
onMounted(() => { 
    updateIsMobile(); 
    window.addEventListener('resize', updateIsMobile);
    // Preseleccionar la cadena de categorías del producto
    hydratePath();
});
onBeforeUnmount(() => window.removeEventListener('resize', updateIsMobile));

const visibleCount = computed(() => isMobile.value ? 1 : 4);
const visibleEditImages = computed(() => currentImages.value.slice(0, visibleCount.value));
const hiddenEditImages = computed(() => currentImages.value.slice(visibleCount.value));

function openGallery(startIndex = 0) {
    currentImageIndex.value = startIndex;
    showGalleryModal.value = true;
}
function closeGallery() { showGalleryModal.value = false; }
function prevGallery() {
    const n = currentImages.value.length; if (n === 0) return;
    currentImageIndex.value = (currentImageIndex.value - 1 + n) % n;
}
function nextGallery() {
    const n = currentImages.value.length; if (n === 0) return;
    currentImageIndex.value = (currentImageIndex.value + 1) % n;
}
function setGalleryIndex(i) {
    if (i < 0 || i >= currentImages.value.length) return;
    currentImageIndex.value = i;
}

// --- Lógica de Añadir/Quitar Variantes (ajustada) ---
const addVariant = () => {
    form.variants.push({ 
        id: null, 
        options_text: '', 
        price: '', 
        stock: 0,
        minimum_stock: 1, // <-- 3. CAMPO NUEVO AL AÑADIR VARIANTE
        alert: null,
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

// --- Lógica de Stock Total (sigue igual) ---
const totalQuantity = computed(() => {
    if (form.variants.length > 0) {
        let total = 0;
        form.variants.forEach(variant => {
            total += Number(variant.stock) || 0;
        });
        return total;
    }
    return Number(form.quantity) || 0; 
});

watch(totalQuantity, (newTotal) => {
    if (form.variants.length > 0) {
        form.quantity = newTotal;
    }
});
// --- FIN Lógica Stock ---

// --- Lógica: mínimo general = suma de mínimos por variante ---
const totalMinimumStock = computed(() => {
    if (form.variants.length === 0) return Number(form.minimum_stock) || 0;
    return form.variants.reduce((t, v) => t + (Number(v.minimum_stock) || 0), 0);
});

// Mantener sincronizado el mínimo general cuando hay variantes
watch(totalMinimumStock, (newTotal) => {
    if (form.variants.length > 0) form.minimum_stock = newTotal;
}, { immediate: true });

const submit = () => {
    confirmingSave.value = true;
};

const closeSaveModal = () => {
    confirmingSave.value = false;
};

const confirmSave = () => {
    form.post(route('admin.products.update', props.product.id), {
        onError: async () => {
            // Construir mensajes legibles
            const msgs = [];
            for (const [key, val] of Object.entries(form.errors)) {
                if (key.startsWith('new_gallery_files.') && String(val).toLowerCase().includes('image')) {
                    msgs.push('Una o más imágenes nuevas superan el tamaño máximo permitido (2 MB) o no son válidas.');
                } else {
                    msgs.push(String(val));
                }
            }
            errorMessages.value = msgs;
            showErrors.value = msgs.length > 0;
        },
        onFinish: () => { confirmingSave.value = false; }
    });
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

                                    <label class="inline-flex items-center gap-2 mb-3 select-none">
                                        <input type="checkbox" v-model="form.track_inventory" class="rounded">
                                        <span class="font-semibold">Controlar inventario</span>
                                    </label>
                                    <div v-if="form.track_inventory && form.variants.length === 0" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                         <div>
                                             <label for="quantity" class="block font-medium text-sm text-gray-700">Inventario (Total)</label>
                                             <input 
                                                 id="quantity" 
                                                 v-model="form.quantity" 
                                                 type="number" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                                 :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                 :disabled="form.variants.length > 0" 
                                                 required>
                                             <p v-if="form.variants.length > 0" class="text-xs text-gray-500 mt-1">Suma automática de variantes.</p>
                                             <p v-else class="text-xs text-gray-500 mt-1">Stock si no hay variantes.</p>
                                         </div>
                                         <div>
                                             <label for="minimum_stock" class="block font-medium text-sm text-gray-700">Inventario Mínimo</label>
                                             <input 
                                                 id="minimum_stock" 
                                                 v-model="form.minimum_stock" 
                                                 type="number" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                 :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                 :disabled="form.variants.length > 0"
                                             />
                                             <p class="text-xs text-gray-500 mt-1">Alerta de bajo stock (general).</p>
                                             </div>
                                         <div>
                                             <label for="alert_general" class="block font-medium text-sm text-gray-700">Alerta (Opcional)</label>
                                             <input 
                                                 id="alert_general" 
                                                 v-model="form.alert" 
                                                 type="number" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                 :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                 :disabled="form.variants.length > 0"
                                             />
                                         </div>
                                     </div>
                                    
                                    <div class="mb-2">
                                        <label class="block font-medium text-sm text-gray-700">Categorías</label>
                                    </div>
                                    <div class="space-y-3">
                                        <div v-for="(lvl, idx) in levels" :key="idx" class="mb-1">
                                            <select 
                                                class="block w-full rounded-md shadow-sm border-gray-300"
                                                v-model="lvl.selected"
                                                @change="onSelectAtLevel(idx)"
                                            >
                                                <option :value="null">Seleccione una categoría</option>
                                                <option v-for="opt in lvl.options" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                            </select>
                                        </div>
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

                                <div class="hidden md:grid grid-cols-6 gap-4 mb-2 text-sm font-medium text-gray-600">
                                    <div class="col-span-2">Opciones (ej: Color:Rojo)</div>
                                    <div>Precio (Opcional)</div>
                                    <div>Stock Actual</div>
                                    <div>Stock Mínimo</div>
                                    <div>Alerta (Opcional)</div>
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

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Stock Actual</label>
                                        <input 
                                            type="number" 
                                            v-model="variant.stock"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 md:hidden">Stock Mínimo</label>
                                        <input 
                                            type="number" 
                                            v-model="variant.minimum_stock" 
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                    </div>
                                    <div>
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
                            <div class="mt-6 border-t pt-6">
                                <div class="p-4 border rounded-md bg-gray-50 col-span-2">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block font-medium text-sm text-gray-700">Imágenes actuales</label>
                                        <span class="text-xs text-gray-500">{{ currentImages.length }} imagen(es)</span>
                                    </div>
                                    <div v-if="currentImages.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                        <div v-for="(image, idx) in visibleEditImages" :key="image.id" class="relative rounded-lg overflow-hidden border bg-white shadow-sm hover:shadow-md transition group cursor-pointer" @click="openGallery(idx)">
                                            <img :src="image.path" class="w-full h-full aspect-[4/3] object-cover" alt="Imagen del producto">
                                            <button @click.stop.prevent="markImageForDeletion(image)" type="button" class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-md hover:bg-red-700">
                                                &times;
                                            </button>
                                        </div>
                                        <div v-if="hiddenEditImages.length > 0" class="relative rounded-lg overflow-hidden border bg-white shadow-sm hover:shadow-md transition group cursor-pointer flex items-center justify-center text-gray-800 font-bold text-base h-full min-h-[90px]" @click="openGallery(visibleCount)">
                                            +{{ hiddenEditImages.length }}
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-500 mt-2">Este producto no tiene imágenes.</p>
                                </div>
                                <div class="mt-4 col-span-2">
                                    <label for="new_gallery_files" class="block font-medium text-sm text-gray-700">Añadir más imágenes</label>
                                    <input 
                                        id="new_gallery_files" 
                                        @input="onNewGalleryInput($event)" 
                                        type="file" 
                                        multiple 
                                        class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                    >
                                    <p class="mt-1 text-xs text-gray-500">Formatos de imagen y hasta 2 MB por archivo.</p>
                                </div>
                            </div>

                            <!-- Modal Galería para ver/eliminar -->
                            <div v-if="showGalleryModal" class="fixed inset-0 bg-black/50 z-[1000] flex items-center justify-center p-4" style="pointer-events: auto;" @click.self="closeGallery">
                                <div class="bg-white rounded-lg p-4 w-full max-w-5xl max-h-[85vh] overflow-hidden relative" @click.stop>
                                    <button type="button" class="absolute top-3 right-3 bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center z-30" @click.stop.prevent="closeGallery" @mousedown.stop.prevent>×</button>
                                    <div class="relative flex items-center justify-center bg-gray-50 border rounded-lg h-[60vh] min-h-[320px] mb-3 select-none">
                                        <button class="absolute left-2 bg-white/90 border rounded-full w-9 h-9 flex items-center justify-center z-20" @click.stop.prevent="prevGallery" @mousedown.stop.prevent>‹</button>
                                        <img :src="currentImages[currentImageIndex]?.path" class="max-w-full max-h-full object-contain" alt="Imagen">
                                        <button class="absolute right-2 bg-white/90 border rounded-full w-9 h-9 flex items-center justify-center z-20" @click.stop.prevent="nextGallery" @mousedown.stop.prevent>›</button>
                                        <button v-if="currentImages[currentImageIndex]" class="absolute top-2 right-12 bg-red-600 text-white rounded px-2 py-1 text-xs z-20" type="button" @click.stop.prevent="markImageForDeletion(currentImages[currentImageIndex])" @mousedown.stop.prevent>Eliminar</button>
                                    </div>
                                    <div class="flex items-center gap-2 overflow-x-auto py-2">
                                        <div v-for="(img, i) in currentImages" :key="img.id" class="border rounded p-1 cursor-pointer flex-shrink-0" :class="{ 'ring-2 ring-blue-500': i === currentImageIndex }" @click="setGalleryIndex(i)">
                                            <img :src="img.path" class="w-20 h-20 object-cover rounded" alt="thumb">
                                        </div>
                                    </div>
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

    <Modal :show="confirmingSave" @close="closeSaveModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Deseás guardar los cambios del producto?
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Se actualizará la información y variantes del producto.
            </p>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeSaveModal">Cancelar</SecondaryButton>
                <DangerButton class="ms-3" @click="confirmSave" :disabled="form.processing">
                    Guardar cambios
                </DangerButton>
            </div>
        </div>
    </Modal>

    <AlertModal
        :show="showSaved"
        type="success"
        title="Producto actualizado"
        primary-text="Entendido"
        @primary="showSaved=false"
        @close="showSaved=false"
    />

    <AlertModal
        :show="showErrors"
        type="error"
        title="No pudimos actualizar el producto"
        message="Revisá los siguientes puntos:"
        :messages="errorMessages"
        primary-text="Entendido"
        @primary="showErrors=false"
        @close="showErrors=false"
    />
</template>