<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref, computed, watch } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import SectionTour from '@/Components/SectionTour.vue';
import { useSectionTour } from '@/utils/useSectionTour.js';

const props = defineProps({
    categories: Array, 
});

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const showSaved = ref(page?.props?.flash?.success ? true : false);
const showErrors = ref(false);
const errorMessages = ref([]);

// Tour de sección para crear productos
const { showTour, steps, handleTourComplete } = useSectionTour('products-create');

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

// --- Selects en cascada multi-nivel ---
const levels = ref([ { options: props.categories, selected: null } ]);

const addLevel = () => {
    levels.value.push({ options: [], selected: null });
};
const removeDeeperLevels = (levelIndex) => {
    levels.value.splice(levelIndex + 1);
};

const onSelectAtLevel = async (levelIndex) => {
    const selectedId = levels.value[levelIndex].selected;
    // Reset niveles inferiores
    removeDeeperLevels(levelIndex);
    form.category_id = selectedId; // por defecto, si no hay hijos, esto será la categoría elegida
    if (!selectedId) return;
    // Cargar hijos
    const res = await fetch(route('admin.categories.children', selectedId));
    if (!res.ok) return;
    const json = await res.json();
    const children = Array.isArray(json.data) ? json.data : [];
    if (children.length > 0) {
        // Añadir nuevo nivel con los hijos
        levels.value.push({ options: children, selected: null });
        // Limpiar category_id para forzar selección de subcategoría
        form.category_id = null;
    }
};
// --- FIN Selects en cascada ---

// Validar que si hay niveles inferiores disponibles, se debe seleccionar una hoja
const requiresSubcategory = computed(() => {
    // Si hay más de un nivel y el último nivel tiene opciones pero no está seleccionado
    if (levels.value.length > 1) {
        const lastLevel = levels.value[levels.value.length - 1];
        return lastLevel.options.length > 0 && !lastLevel.selected;
    }
    return false;
});

const form = useForm({
    name: '',
    price: '',
    purchase_price: null,
    // wholesale_price eliminado
    retail_price: null,
    track_inventory: true,
    quantity: 0,
    alert: null,
    category_id: null, 
    short_description: '',
    long_description: '',
    specifications: '',
    gallery_files: [],
    variants: [], 
    variant_attributes: [],
});

// --- Lógica de Variantes (Sigue igual) ---
const addVariant = () => {
    form.variants.push({ 
        options_text: '', 
        price: '', 
        purchase_price: null,
        retail_price: null,
        stock: 0,
        alert: null,
    });
};
const removeVariant = (index) => {
    form.variants.splice(index, 1);
};
// --- FIN Lógica Variantes ---

// Sincronizar precio detal con precio principal cuando se controla inventario
watch(() => form.track_inventory, (active) => {
    if (active) {
        form.retail_price = form.price;
    }
});
watch(() => form.price, (newPrice) => {
    if (form.track_inventory) {
        form.retail_price = newPrice;
    }
});

// Builder de Atributos (nombre + valores CSV) -> genera variantes
const attributes = ref([{ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }]);
const addAttribute = () => { attributes.value.push({ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }); };
const removeAttribute = (idx) => { attributes.value.splice(idx, 1); };
const parseCsv = (text) => String(text || '').split(',').map(s => s.trim()).filter(Boolean);
const cartesian = (arrays) => arrays.reduce((a, b) => a.flatMap(x => b.map(y => x.concat([y]))), [[]]);
// Helpers para selección múltiple segura en reglas
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
// Alternar UI de dependencia y limpiar estado si se oculta
function toggleDependencyUi(attr) {
    attr.__showDependency = !attr.__showDependency;
    if (!attr.__showDependency) {
        attr.dependsOn = '';
        attr.rulesSelected = {};
        attr.rules = {};
    }
}
const generateVariantsFromAttributes = () => {
    const attrs = attributes.value.map(a => ({
        name: String(a.name || '').trim(),
        values: parseCsv(a.valuesText),
        dependsOn: String(a.dependsOn || '').trim(),
        rules: a.rules || {}, // { parentValue: 'a,c,d' }
        rulesSelected: a.rulesSelected || {},
    })).filter(a => a.name && a.values.length > 0);
    if (attrs.length === 0) return;

    // Mapa rápido por nombre
    const nameToAttr = Object.fromEntries(attrs.map(a => [a.name, a]));

    // Ordenar para que todo hijo quede después de su padre
    const ordered = [];
    const visited = new Set();
    function visit(attr) {
        if (visited.has(attr.name)) return;
        if (attr.dependsOn && nameToAttr[attr.dependsOn]) visit(nameToAttr[attr.dependsOn]);
        visited.add(attr.name);
        ordered.push(attr);
    }
    attrs.forEach(visit);

    // Generar recursivo respetando reglas
    const results = [];
    function backtrack(index, chosen) {
        if (index === ordered.length) {
            results.push({ ...chosen });
            return;
        }
        const attr = ordered[index];
        let allowed = attr.values;
        if (attr.dependsOn) {
            const parentVal = chosen[attr.dependsOn];
            if (parentVal != null) {
                const ruleRaw = attr.rulesSelected?.[parentVal] ?? attr.rules?.[parentVal] ?? [];
                const ruleVals = Array.isArray(ruleRaw) ? ruleRaw : parseCsv(ruleRaw);
                if (ruleVals.length > 0) {
                    allowed = attr.values.filter(v => ruleVals.includes(v));
                }
            }
        }
        for (const val of allowed) {
            chosen[attr.name] = val;
            backtrack(index + 1, chosen);
        }
        delete chosen[attr.name];
    }
    backtrack(0, {});

    // persistir definición para backend
    form.variant_attributes = attrs;

    form.variants = results.map(sel => {
        const parts = ordered.map(a => `${a.name}:${sel[a.name]}`);
        return {
            options_text: parts.join(', '),
            price: '',
            purchase_price: null,
            retail_price: null,
            stock: 0,
            alert: null,
        };
    });
};


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
// Eliminado cálculo de mínimo: ahora no se usa

// Quitamos la regla que limitaba stock actual <= stock mínimo


const submit = () => {
    // Validar que se haya seleccionado una categoría hoja si hay subcategorías disponibles
    if (requiresSubcategory.value) {
        errorMessages.value = ['Debés seleccionar una subcategoría. La categoría principal tiene subcategorías disponibles y es obligatorio elegir una de ellas.'];
        showErrors.value = true;
        // Hacer scroll al campo de categorías
        nextTick(() => {
            const firstCategorySelect = document.querySelector('.space-y-3 select');
            if (firstCategorySelect) {
                firstCategorySelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstCategorySelect.focus();
            }
        });
        return;
    }
    
    // Derivar siempre variant_attributes desde la UI antes de enviar
    try {
        const attrs = attributes.value.map(a => ({
            name: String(a.name || '').trim(),
            values: parseCsv(a.valuesText),
            dependsOn: String(a.dependsOn || '').trim(),
            rules: a.rules || {},
            rulesSelected: a.rulesSelected || {},
        })).filter(a => a.name && a.values.length > 0);
        form.variant_attributes = attrs;
        // Regenerar variantes con dependencias para persistencia coherente
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
            form.variants = results.map(sel => ({ options_text: ordered.map(a => `${a.name}:${sel[a.name]}`).join(', '), price: '', purchase_price: null, stock: 0, alert: null }));
        }
    } catch (_) {}

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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear producto</h2>
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
                                    <h4 v-if="form.track_inventory" class="font-semibold text-gray-800 mb-2">Inventario y Precios</h4>
                                    <p v-if="form.track_inventory" class="text-xs text-gray-500 mb-4">
                                        Si creas variantes, estos totales se calcularán solos.
                                    </p>
                                    <div v-if="form.track_inventory" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="quantity" class="block font-medium text-sm text-gray-700 flex items-center">Inventario (Total)
                                                <InfoTip class="ml-1 md:hidden" text="Stock si no hay variantes. Con variantes, se suma automáticamente." />
                                            </label>
                                            <input 
                                                id="quantity" 
                                                v-model="form.quantity" 
                                                type="number" 
                                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                                :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                :disabled="form.track_inventory === false || form.variants.length > 0"  
                                                title="Stock si no hay variantes. Con variantes, se suma automáticamente."
                                            />
                                            <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">{{ form.errors.quantity }}</p>
                                        </div>
                                        <div>
                                            <label for="alert" class="block font-medium text-sm text-gray-700 flex items-center">Alerta (Opcional)
                                                <InfoTip class="ml-1 md:hidden" text="Se considera bajo stock cuando cantidad ≤ alerta." />
                                            </label>
                                            <input 
                                                id="alert" 
                                                v-model="form.alert" 
                                                type="number" 
                                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                :class="{ 'bg-gray-100': form.variants.length > 0 && form.track_inventory }"
                                                :disabled="form.variants.length > 0 && form.track_inventory"
                                                title="Se considera bajo stock cuando cantidad ≤ alerta."
                                            />
                                        </div>
                                        <div>
                                            <label for="purchase_price" class="block font-medium text-sm text-gray-700 flex items-center">Precio de compra
                                                <InfoTip class="ml-1 md:hidden" text="Costo de adquisición del producto." />
                                            </label>
                                            <input id="purchase_price" v-model="form.purchase_price" type="number" step="0.01" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" />
                                        </div>
                                        
                                    </div>
                                </div>

                                <div class="mt-4 mb-2">
                                    <label class="block font-medium text-sm text-gray-700">Categorías</label>
                                    <p v-if="requiresSubcategory" class="mt-1 text-sm text-amber-600 font-medium">
                                        ⚠️ Es obligatorio seleccionar una subcategoría
                                    </p>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="(lvl, idx) in levels" :key="idx" class="mb-1">
                                        <select 
                                            class="block w-full rounded-md shadow-sm border-gray-300"
                                            :class="{ 'border-red-500': requiresSubcategory && idx === levels.length - 1 && !lvl.selected }"
                                            v-model="lvl.selected"
                                            @change="onSelectAtLevel(idx)"
                                            :required="idx === levels.length - 1 && lvl.options.length > 0"
                                        >
                                            <option :value="null">Seleccione una categoría</option>
                                            <option v-for="opt in lvl.options" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <p v-if="form.errors.category_id" class="mt-1 text-sm text-red-600">{{ form.errors.category_id }}</p>
                                <p v-if="requiresSubcategory && !form.errors.category_id" class="mt-1 text-sm text-red-600">
                                    Debés seleccionar una subcategoría. La categoría principal tiene subcategorías disponibles.
                                </p>
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
                                    Cada fila representa una combinación única. Si usás inventario, el stock total se suma automáticamente.
                                </p>
                                <!-- Atributos de variantes -->
                                <div class="mb-4 p-4 border rounded-md bg-gray-50">
                                    <h4 class="font-semibold text-gray-800 mb-2">Atributos de variantes</h4>
                                    <p class="text-xs text-gray-500 mb-3">Ejemplos: Nombre "Color" y Valores "Rojo, Verde, Azul". Podés agregar más filas como "Talla" con "S, M, L".</p>
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
                                        <div class="md:col-span-5 flex items-center gap-2">
                                            <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-blue-200 text-blue-600 hover:bg-blue-50 hover:border-blue-300" @click="toggleDependencyUi(attr)" :title="attr.__showDependency ? 'Ocultar dependencia' : 'Configurar dependencia'" :aria-label="attr.__showDependency ? 'Ocultar dependencia' : 'Configurar dependencia'">
                                                <svg v-if="!attr.__showDependency" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M11 11V5a1 1 0 1 1 2 0v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6z"/></svg>
                                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M5 12a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1z"/></svg>
                                            </button>
                                            <span class="text-xs text-blue-600 md:hidden" v-text="attr.__showDependency ? 'Ocultar' : 'Depend.'"></span>
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
                                            <div class="md:col-span-5 flex items-center gap-4">
                                                <div class="flex items-center gap-2">
                                                    <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-blue-200 text-blue-600 hover:bg-blue-50 hover:border-blue-300" @click="addAttribute" title="Añadir atributo" aria-label="Añadir atributo">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M11 11V5a1 1 0 1 1 2 0v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6z"/></svg>
                                                    </button>
                                                    <span class="text-xs md:hidden">Añadir</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-full border border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300" @click="removeAttribute(ai)" title="Quitar atributo" aria-label="Quitar atributo">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M7 7h10l-1 12a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2L7 7zm9-3a1 1 0 0 1 1 1v1H7V5a1 1 0 0 1 1-1h8zM9 5V4a3 3 0 0 1 3-3 3 3 0 0 1 3 3v1H9z"/></svg>
                                                    </button>
                                                    <span class="text-xs md:hidden">Quitar</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>

                                
                                
                                
                            </div>
                            
                            <div class="md:col-span-2 flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm9-1.5V7h4.5L13 2.5z"/><path d="M8 13h8v2H8zM8 9h5v2H8z"/></svg>
                                    <span>Crear</span>
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

    <!-- Tour de sección para crear productos -->
    <SectionTour 
        :show="showTour" 
        section="products-create"
        :steps="steps"
        @complete="handleTourComplete"
    />
</template>