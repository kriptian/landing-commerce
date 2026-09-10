<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    categories: { type: Array, default: () => [] },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);
const page = usePage();
const creating = ref(false);
const saving = ref(false);
const newName = ref('');
const parentId = ref(null);
const createError = ref('');
const createdOptions = ref([]);
const invalidatedLeafIds = ref(new Set());

const flatten = (categories) => categories.flatMap((category) => [category, ...flatten(category.children)]);
const allCategories = computed(() => flatten(props.categories));
const leafOptions = computed(() => [
    ...allCategories.value.filter((category) => category.children_count === 0 && !invalidatedLeafIds.value.has(category.id)),
    ...createdOptions.value,
].sort((a, b) => a.path.localeCompare(b.path, 'es')));
const parentOptions = computed(() => allCategories.value.filter((category) => category.can_have_children));
const canCreate = computed(() => page.props.auth?.permissions?.includes('crear categorias')
    || page.props.auth?.permissions?.includes('gestionar categorias'));

const createCategory = async () => {
    const name = newName.value.trim();
    if (!name) {
        createError.value = 'Escribe el nombre de la nueva categoria.';
        return;
    }

    saving.value = true;
    createError.value = '';
    try {
        const response = parentId.value
            ? await window.axios.post(route('admin.categories.storeSubcategory', parentId.value), { name }, { headers: { Accept: 'application/json' } })
            : await window.axios.post(route('admin.categories.store'), { name }, { headers: { Accept: 'application/json' } });
        const parent = allCategories.value.find((category) => category.id === parentId.value);
        const category = {
            ...response.data.category,
            path: parent ? `${parent.path} > ${name}` : name,
        };

        if (parent && parent.children_count === 0) invalidatedLeafIds.value = new Set([...invalidatedLeafIds.value, parent.id]);
        createdOptions.value.push(category);
        emit('update:modelValue', category.id);
        creating.value = false;
        newName.value = '';
        parentId.value = null;
    } catch (error) {
        const errors = error.response?.data?.errors;
        createError.value = errors?.name?.[0] || errors?.['subcategories.0.name']?.[0] || 'No se pudo crear la categoria. Intenta de nuevo.';
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div>
        <div class="flex items-end justify-between gap-3">
            <label for="product-category" class="block text-sm font-medium text-slate-700">Categoria del producto</label>
            <button v-if="canCreate" type="button" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900" @click="creating = !creating">
                {{ creating ? 'Cancelar' : '+ Crear categoria' }}
            </button>
        </div>
        <select
            id="product-category"
            :value="modelValue"
            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            :class="{ 'border-red-500': error }"
            required
            @change="emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)"
        >
            <option value="">Selecciona una categoria</option>
            <option v-for="category in leafOptions" :key="category.id" :value="category.id">{{ category.path }}</option>
        </select>
        <p class="mt-1 text-xs text-slate-500">Solo aparecen categorias finales, listas para recibir productos.</p>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>

        <div v-if="creating" class="mt-3 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
            <p class="text-sm font-semibold text-slate-900">Nueva categoria rapida</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="quick-category-name" class="block text-xs font-medium text-slate-700">Nombre</label>
                    <input id="quick-category-name" v-model="newName" type="text" maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ej. Camisetas" @keydown.enter.prevent="createCategory" />
                </div>
                <div>
                    <label for="quick-category-parent" class="block text-xs font-medium text-slate-700">Dentro de (opcional)</label>
                    <select id="quick-category-parent" v-model="parentId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option :value="null">Categoria principal</option>
                        <option v-for="category in parentOptions" :key="category.id" :value="category.id">{{ category.path }}</option>
                    </select>
                </div>
            </div>
            <p v-if="createError" class="mt-2 text-sm text-red-700">{{ createError }}</p>
            <div class="mt-3 flex justify-end">
                <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60" :disabled="saving" @click="createCategory">
                    {{ saving ? 'Creando...' : 'Crear y seleccionar' }}
                </button>
            </div>
        </div>
    </div>
</template>
