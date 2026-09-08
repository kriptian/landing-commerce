<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

defineProps({
    enabled: { type: Boolean, default: false },
});

const emit = defineEmits(['apply']);
const context = ref('');
const image = ref(null);
const imagePreview = ref(null);
const generating = ref(false);
const error = ref('');
const draft = ref(null);
const selected = ref({
    name: true,
    short_description: true,
    long_description: true,
    specifications: true,
    meta_keywords: true,
});

const canGenerate = computed(() => Boolean(image.value || context.value.trim()));
const fields = [
    { key: 'name', label: 'Nombre' },
    { key: 'short_description', label: 'Descripción corta' },
    { key: 'long_description', label: 'Descripción completa' },
    { key: 'specifications', label: 'Especificaciones' },
    { key: 'meta_keywords', label: 'Palabras clave SEO' },
];

const selectImage = event => {
    const file = event.target.files?.[0] || null;
    error.value = '';
    if (file && (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024)) {
        error.value = 'Usa una imagen JPG, PNG o WebP de máximo 2 MB.';
        event.target.value = '';
        return;
    }
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    image.value = file;
    imagePreview.value = file ? URL.createObjectURL(file) : null;
};

const generate = async () => {
    if (!canGenerate.value || generating.value) return;
    generating.value = true;
    error.value = '';
    draft.value = null;
    const payload = new FormData();
    if (image.value) payload.append('image', image.value);
    if (context.value.trim()) payload.append('context', context.value.trim());

    try {
        const response = await window.axios.post(route('admin.products.ai-draft'), payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        draft.value = response.data.draft;
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'No pudimos generar el borrador. Intenta nuevamente.';
    } finally {
        generating.value = false;
    }
};

const applyDraft = () => {
    const values = {};
    fields.forEach(field => {
        if (selected.value[field.key]) values[field.key] = draft.value[field.key] || '';
    });
    emit('apply', values);
};

onBeforeUnmount(() => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
});
</script>

<template>
    <section v-if="enabled" class="mb-6 overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-sky-50 shadow-sm md:col-span-2">
        <div class="border-b border-violet-100 px-5 py-4 sm:px-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-violet-600">Asistente Gemini</p>
                    <h3 class="mt-1 text-xl font-bold text-slate-900">Convierte una foto en un borrador</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Gemini sugiere contenido. Tú revisas y decides qué campos aplicar.</p>
                </div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-violet-700 ring-1 ring-violet-200">Vista previa</span>
            </div>
        </div>

        <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[15rem_1fr]">
            <label class="group flex min-h-48 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-violet-200 bg-white text-center transition hover:border-violet-400">
                <img v-if="imagePreview" :src="imagePreview" alt="Vista previa para Gemini" class="h-full min-h-48 w-full object-cover">
                <template v-else>
                    <svg class="h-8 w-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 16.5V6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25v-.75Zm0 0 5.25-5.25a2.25 2.25 0 0 1 3.182 0L15 14.818m0 0 1.318-1.318a2.25 2.25 0 0 1 3.182 0L21 15m-3.75-6.75h.008v.008h-.008V8.25Z" /></svg>
                    <span class="mt-3 text-sm font-bold text-slate-800">Añadir foto</span>
                    <span class="mt-1 px-4 text-xs text-slate-500">JPG, PNG o WebP, máximo 2 MB</span>
                </template>
                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="selectImage">
            </label>

            <div>
                <label class="block text-sm font-bold text-slate-800" for="ai-product-context">¿Qué debería saber Gemini?</label>
                <textarea id="ai-product-context" v-model="context" rows="4" maxlength="2000" class="mt-2 block w-full rounded-2xl border-slate-200 bg-white shadow-sm focus:border-violet-500 focus:ring-violet-500" placeholder="Ejemplo: camiseta deportiva para mujer, tela liviana, colección verano. No incluyas datos personales." />
                <p class="mt-2 text-xs leading-5 text-slate-500">No envíes documentos, rostros ni información privada. Precio, costo, inventario y códigos siempre se diligencian manualmente.</p>
                <p v-if="error" class="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>
                <button type="button" :disabled="!canGenerate || generating" class="mt-4 inline-flex h-11 items-center justify-center rounded-full bg-violet-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40" @click="generate">
                    {{ generating ? 'Gemini está analizando...' : 'Generar borrador con IA' }}
                </button>
            </div>
        </div>

        <div v-if="draft" class="border-t border-violet-100 bg-white/75 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h4 class="font-bold text-slate-900">Borrador generado</h4><p class="text-sm text-slate-500">Selecciona los campos que quieres llevar al formulario.</p></div>
                <p v-if="draft.category" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700">Categoría sugerida: {{ draft.category.name }}</p>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label v-for="field in fields" :key="field.key" class="flex cursor-pointer gap-3 rounded-2xl border border-slate-200 bg-white p-4" :class="field.key === 'long_description' ? 'md:col-span-2' : ''">
                    <input v-model="selected[field.key]" type="checkbox" class="mt-1 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                    <span class="min-w-0"><span class="block text-xs font-extrabold uppercase tracking-wide text-slate-400">{{ field.label }}</span><span class="mt-1 block whitespace-pre-line text-sm leading-6 text-slate-700">{{ draft[field.key] || 'Sin sugerencia' }}</span></span>
                </label>
            </div>
            <button type="button" class="mt-5 inline-flex h-11 items-center rounded-full bg-slate-950 px-5 text-sm font-bold text-white hover:bg-slate-800" @click="applyDraft">Aplicar campos seleccionados</button>
        </div>
    </section>
</template>
