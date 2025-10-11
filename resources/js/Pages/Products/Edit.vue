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
        purchase_price: variant.purchase_price ?? null,
        retail_price: variant.retail_price ?? null,
        stock: variant.stock,
        alert: variant.alert ?? null,
    };
});
// --- FIN Lógica Variantes ---


const form = useForm({
    _method: 'PUT', 
    name: props.product.name,
    price: props.product.price,
    purchase_price: props.product.purchase_price ?? null,
    // retail_price eliminado
    track_inventory: props.product.track_inventory ?? true,
    quantity: props.product.quantity,
    alert: props.product.alert ?? null,
    category_id: props.product.category_id,
    short_description: props.product.short_description,
    long_description: props.product.long_description,
    specifications: props.product.specifications ? JSON.parse(props.product.specifications).join(', ') : '',
    new_gallery_files: [],
    images_to_delete: [],
    variants: initialVariants, 
    variants_to_delete: [], 
    variant_attributes: [],
});

// Atributos de variantes (igual que en Crear)
const attributes = ref([{ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }]);
const addAttribute = () => { attributes.value.push({ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }); };
const removeAttribute = (idx) => { attributes.value.splice(idx, 1); };
const parseCsv = (text) => String(text || '').split(',').map(s => s.trim()).filter(Boolean);
const cartesian = (arrays) => arrays.reduce((a, b) => a.flatMap(x => b.map(y => x.concat([y]))), [[]]);
function isRuleChecked(attr, pv, cv) {
    const arr = (attr.rulesSelected && Array.isArray(attr.rulesSelected[pv])) ? attr.rulesSelected[pv] : [];
    return arr.includes(cv);
}
function toggleRule(attr, pv, cv, event) {
    const checked = !!event?.target?.checked;
    if (!attr.rulesSelected) attr.rulesSelected = {};
    const base = Array.isArray(attr.rulesSelected[pv]) ? [...attr.rulesSelected[pv]] : [];
    const idx = base.indexOf(cv);
    if (checked && idx === -1) base.push(cv);
    if (!checked && idx !== -1) base.splice(idx, 1);
    attr.rulesSelected[pv] = base;
}
// Hidratar atributos desde variantes existentes
const hydrateAttributesFromVariants = () => {
    try {
        const vs = Array.isArray(props.product.variants) ? props.product.variants : [];
        if (vs.length === 0) return;
        const keys = new Set();
        vs.forEach(v => { Object.keys(v?.options || {}).forEach(k => keys.add(k)); });
        const next = [];
        keys.forEach((k) => {
            const valuesSet = new Set();
            vs.forEach(v => { const val = (v?.options || {})[k]; if (val != null && String(val).trim() !== '') valuesSet.add(String(val)); });
            const valuesText = Array.from(valuesSet).join(', ');
            next.push({ name: k, valuesText, dependsOn: '', rules: {}, rulesSelected: {} });
        });
        if (next.length > 0) attributes.value = next;
    } catch (_) { /* noop */ }
};

// Preferir hidratar desde lo guardado en product.variant_attributes (fuente de verdad)
const hydrateAttributesFromSaved = () => {
    try {
        const saved = props.product?.variant_attributes;
        const arr = Array.isArray(saved) ? saved : [];
        if (arr.length === 0) return false;
        const next = arr.map(sa => {
            const values = Array.isArray(sa?.values) ? sa.values : parseCsv(sa?.valuesText || '');
            const dependsOn = String(sa?.dependsOn || '').trim();
            const rulesSelected = typeof sa?.rulesSelected === 'object' && sa.rulesSelected ? sa.rulesSelected : {};
            const rules = typeof sa?.rules === 'object' && sa.rules ? sa.rules : {};
            const shouldShow = !!(dependsOn || Object.keys(rulesSelected).length || Object.keys(rules).length);
            return {
                name: String(sa?.name || ''),
                valuesText: values.join(', '),
                dependsOn,
                rulesSelected,
                rules,
                __showDependency: shouldShow,
            };
        }).filter(a => a.name);
        if (next.length > 0) {
            attributes.value = next;
            return true;
        }
    } catch (e) {}
    return false;
};

// Hidratar dependencias guardadas
const hydrateDependenciesFromProduct = () => {
    try {
        const saved = props.product?.variant_attributes || [];
        if (!Array.isArray(saved) || saved.length === 0) return;
        const map = Object.fromEntries(attributes.value.map(a => [a.name, a]));
        for (const sa of saved) {
            const local = map[sa.name];
            if (!local) continue;
            if (sa.dependsOn) {
                local.dependsOn = sa.dependsOn;
                local.__showDependency = true;
            }
            if (sa.rulesSelected && typeof sa.rulesSelected === 'object') {
                local.rulesSelected = sa.rulesSelected;
                if (Object.keys(local.rulesSelected || {}).length > 0) local.__showDependency = true;
            } else if (sa.rules && typeof sa.rules === 'object') {
                local.rules = sa.rules;
                if (Object.keys(local.rules || {}).length > 0) local.__showDependency = true;
            }
        }
    } catch (_) {}
};
// Generador: reemplaza variantes actuales con combinaciones nuevas
const generateVariantsFromAttributes = () => {
    const attrs = attributes.value.map(a => ({
        name: String(a.name || '').trim(),
        values: parseCsv(a.valuesText),
        dependsOn: String(a.dependsOn || '').trim(),
        rules: a.rules || {},
        rulesSelected: a.rulesSelected || {},
    })).filter(a => a.name && a.values.length > 0);
    if (attrs.length === 0) return;
    const nameToAttr = Object.fromEntries(attrs.map(a => [a.name, a]));
    const ordered = [];
    const visited = new Set();
    function visit(attr) {
        if (visited.has(attr.name)) return;
        if (attr.dependsOn && nameToAttr[attr.dependsOn]) visit(nameToAttr[attr.dependsOn]);
        visited.add(attr.name);
        ordered.push(attr);
    }
    attrs.forEach(visit);
    const results = [];
    function backtrack(index, chosen) {
        if (index === ordered.length) { results.push({ ...chosen }); return; }
        const attr = ordered[index];
        let allowed = attr.values;
        if (attr.dependsOn) {
            const parentVal = chosen[attr.dependsOn];
            if (parentVal != null) {
                const ruleRaw = attr.rulesSelected?.[parentVal] ?? attr.rules?.[parentVal] ?? [];
                const ruleVals = Array.isArray(ruleRaw) ? ruleRaw : parseCsv(ruleRaw);
                if (ruleVals.length > 0) allowed = attr.values.filter(v => ruleVals.includes(v));
            }
        }
        for (const val of allowed) {
            chosen[attr.name] = val;
            backtrack(index + 1, chosen);
        }
        delete chosen[attr.name];
    }
    backtrack(0, {});
    form.variant_attributes = attrs;
    form.variants = results.map(sel => {
        const parts = ordered.map(a => `${a.name}:${sel[a.name]}`);
        return { id: null, options_text: parts.join(', '), price: '', purchase_price: null, stock: 0, alert: null };
    });
};
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
    // Hidratar atributos desde la definición guardada; si no hay, caer a variantes
    const usedSaved = hydrateAttributesFromSaved();
    if (!usedSaved) {
        hydrateAttributesFromVariants();
        hydrateDependenciesFromProduct();
    }
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
// (Se elimina la edición manual de variantes en esta vista)
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

// Eliminado cálculo de stock mínimo

// Sincronizar precio detal con precio principal cuando se controla inventario
// (retail_price eliminado)

// (retail por variante eliminado)
const submit = () => {
    // Siempre derivar variant_attributes desde el estado actual del builder (aunque no se toquen inputs)
    try {
        const attrs = attributes.value.map(a => ({
            name: String(a.name || '').trim(),
            values: parseCsv(a.valuesText),
            dependsOn: String(a.dependsOn || '').trim(),
            rules: a.rules || {},
            rulesSelected: a.rulesSelected || {},
        })).filter(a => a.name && a.values.length > 0);
        form.variant_attributes = attrs;
        // Regenerar variantes para mantener coherencia con la definición actual
        if (attrs.length > 0) {
            const nameToAttr = Object.fromEntries(attrs.map(a => [a.name, a]));
            const ordered = [];
            const visited = new Set();
            function visit(attr) { if (visited.has(attr.name)) return; if (attr.dependsOn && nameToAttr[attr.dependsOn]) visit(nameToAttr[attr.dependsOn]); visited.add(attr.name); ordered.push(attr); }
            attrs.forEach(visit);
            const results = [];
            function backtrack(index, chosen) {
                if (index === ordered.length) { results.push({ ...chosen }); return; }
                const attr = ordered[index];
                let allowed = attr.values;
                if (attr.dependsOn) {
                    const parentVal = chosen[attr.dependsOn];
                    if (parentVal != null) {
                        const ruleRaw = attr.rulesSelected?.[parentVal] ?? attr.rules?.[parentVal] ?? [];
                        const ruleVals = Array.isArray(ruleRaw) ? ruleRaw : parseCsv(ruleRaw);
                        if (ruleVals.length > 0) allowed = attr.values.filter(v => ruleVals.includes(v));
                    }
                }
                for (const val of allowed) { chosen[attr.name] = val; backtrack(index + 1, chosen); }
                delete chosen[attr.name];
            }
            backtrack(0, {});
            form.variants = results.map(sel => ({
                id: null,
                options_text: ordered.map(a => `${a.name}:${sel[a.name]}`).join(', '),
                price: '', purchase_price: null, stock: 0, alert: null,
            }));
        }
    } catch (_) {}
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
                                    <div v-if="form.track_inventory" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                         <div>
                                             <label for="quantity" class="block font-medium text-sm text-gray-700">Inventario (Total)</label>
                                             <input 
                                                 id="quantity" 
                                                 v-model="form.quantity" 
                                                 type="number" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                                 :class="{ 'bg-gray-100': form.track_inventory === false }"
                                                 :disabled="form.track_inventory === false" 
                                                 required
                                                 title="Stock si no hay variantes. Con variantes, se suma automáticamente.">
                                         </div>
                                          <div>
                                             <label for="alert_general" class="block font-medium text-sm text-gray-700">Alerta (Opcional)</label>
                                             <input 
                                                 id="alert_general" 
                                                 v-model="form.alert" 
                                                 type="number" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                 :class="{ 'bg-gray-100': form.track_inventory === false }"
                                                 :disabled="form.track_inventory === false"
                                                 title="Se considera bajo stock cuando cantidad ≤ alerta."
                                             />
                                         </div>
                                         <div>
                                             <label for="purchase_price" class="block font-medium text-sm text-gray-700">Precio de compra</label>
                                             <input id="purchase_price" v-model="form.purchase_price" type="number" step="0.01" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" />
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
                            
                        <!-- Atributos de variantes (generador) -->
                            <div class="md:col-span-2 mt-6 border-t pt-6">
                            <h3 class="text-lg font-medium text-gray-900">Atributos de variantes</h3>
                            <p class="text-sm text-gray-600 mb-4">Definí atributos como "Color" y "Talla" y sus valores separados por comas. Podés regenerar combinaciones.</p>
                            <div class="mb-4 p-4 border rounded-md bg-gray-50">
                                <div class="space-y-2">
                                    <div v-for="(attr, ai) in attributes" :key="`attr-${ai}`" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-center">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                            <input v-model="attr.name" type="text" placeholder="Color" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                        </div>
                                        <div class="md:col-span-3">
                                            <label class="block text-sm font-medium text-gray-700">Valores (separados por coma)</label>
                                            <input v-model="attr.valuesText" type="text" placeholder="Rojo, Verde, Azul" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                                        </div>
                                        <!-- Toggle dependencia para no saturar la vista -->
                                        <div class="md:col-span-5">
                                            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" v-model="attr.__showDependency" class="rounded"><span>Configurar dependencia</span></label>
                                </div>
                                        <div v-if="attr.__showDependency" class="md:col-span-5 grid grid-cols-1 md:grid-cols-5 gap-3 items-start">
                                    <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Depende de</label>
                                                <select v-model="attr.dependsOn" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                                    <option value="">(sin dependencia)</option>
                                                    <option v-for="(other, oi) in attributes" :key="`dep-${ai}-${oi}`" :value="other.name" :disabled="other === attr || !other.name">{{ other.name || '(sin nombre)' }}</option>
                                                </select>
                                    </div>
                                            <div v-if="attr.dependsOn" class="md:col-span-3">
                                                <label class="block text-sm font-medium text-gray-700">Permitir valores del hijo según el valor del padre</label>
                                                <div class="mt-2 space-y-3">
                                                    <div v-for="pv in parseCsv((attributes.find(a => a.name === attr.dependsOn)?.valuesText) || '')" :key="`pv-${ai}-${pv}`" class="border rounded p-2">
                                                        <div class="text-xs font-semibold text-gray-700 mb-2">{{ attr.dependsOn }} = <strong>{{ pv }}</strong></div>
                                                        <div class="flex flex-wrap gap-3">
                                                            <label v-for="cv in parseCsv(attr.valuesText)" :key="`cv-${ai}-${pv}-${cv}`" class="inline-flex items-center gap-1 text-sm">
                                                                <input type="checkbox" :checked="isRuleChecked(attr, pv, cv)" @change="toggleRule(attr, pv, cv, $event)" class="rounded">
                                                                <span>{{ cv }}</span>
                                        </label>
                                                        </div>
                                                    </div>
                                    </div>
                                                <p class="text-xs text-gray-500 mt-2">Si no seleccionas nada para un valor del padre, se permiten todos los valores del hijo.</p>
                                    </div>
                                    </div>
                                        <div class="md:col-span-5 flex gap-2">
                                            <button type="button" class="text-sm text-blue-600 hover:text-blue-800" @click="addAttribute">+ Añadir atributo</button>
                                            <button v-if="attributes.length > 1" type="button" class="text-sm text-red-600 hover:text-red-800" @click="removeAttribute(ai)">Quitar</button>
                                    </div>
                                    </div>
                                </div>

                            </div>
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