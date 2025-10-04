<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { safeRoute } from '@/app';
import Modal from '@/Components/Modal.vue';
import AlertModal from '@/Components/AlertModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { ref, onMounted } from 'vue';

const props = defineProps({
    category: Object, // Recibimos la categoría con sus hijos o padre ya cargado
});

// Formulario para editar el nombre de la categoría actual
const form = useForm({
    name: props.category.name,
});

// Formulario para añadir una NUEVA subcategoría
const newSubcategoryForm = useForm({
    name: '',
});

const updateCategoryName = () => {
    form.put(route('admin.categories.update', { category: props.category.id }));
};

const addNewSubcategory = () => {
    const url = safeRoute('admin.categories.storeSubcategory', { parentCategory: props.category.id }, `/admin/categories/${props.category.id}/subcategories`);
    newSubcategoryForm.post(url, {
        onSuccess: () => { newSubcategoryForm.reset(); loadChildren(); },
        preserveScroll: true,
    });
};
const cancelNewSubcategory = () => {
    newSubcategoryForm.name = '';
};

// Confirmación elegante para eliminar subcategorías
const confirmingDeletion = ref(false);
const categoryToDelete = ref(null);

// (Se redefine más abajo con parentId para refrescos anidados)

const closeDeleteModal = () => {
    confirmingDeletion.value = false;
    categoryToDelete.value = null;
};

const deleteCategory = () => {
    if (!categoryToDelete.value) return;
    router.delete(route('admin.categories.destroy', { category: categoryToDelete.value }), {
        preserveScroll: true,
        onSuccess: () => {
            const deletedId = categoryToDelete.value;
            closeDeleteModal();
            const pid = deleteContextParentId.value;
            // Limpiar estados locales asociados al elemento borrado
            if (deletedId) {
                delete expandedByParent.value[deletedId];
                delete showAddByParent.value[deletedId];
                delete newChildName.value[deletedId];
                delete errorByParent.value[deletedId];
                delete childrenByParent.value[deletedId];
            }
            if (pid && pid !== props.category.id) {
                loadChildrenFor(pid);
            } else {
                loadChildren();
            }
            // También refrescar counters del padre inmediato
            loadChildren();
        },
        onError: (errors) => {
            // Si algo sale mal, recargamos la lista actual para evitar estados inconsistentes
            const pid = deleteContextParentId.value;
            if (pid && pid !== props.category.id) {
                loadChildrenFor(pid);
            } else {
                loadChildren();
            }
            errorText.value = (errors && (errors.delete || errors.message)) || 'Existen productos asociados a esta categoría y no se puede borrar.';
            showError.value = true;
        },
        onFinish: () => closeDeleteModal(),
    });
};

// Carga perezosa de hijos para cualquier nivel (vista auxiliar)
const isLoadingChildren = ref(false);
const children = ref(props.category.children || []);
const loadChildren = async () => {
    try {
        isLoadingChildren.value = true;
        const url = safeRoute('admin.categories.children', { category: props.category.id }, `/admin/categories/${props.category.id}/children`);
        const res = await fetch(url);
        if (!res.ok) return;
        const json = await res.json();
        children.value = Array.isArray(json.data) ? json.data : [];
    } finally {
        isLoadingChildren.value = false;
    }
};
onMounted(() => {
    // Siempre refrescamos hijos desde el endpoint (para contar y data fresca)
    loadChildren();
});

// UI inline: añadir subnivel a cada subcategoría sin navegar
const showAddByParent = ref({});
const newChildName = ref({});
const creatingChild = ref({});
const errorByParent = ref({});

const toggleAddChild = (parentId) => {
    showAddByParent.value[parentId] = !showAddByParent.value[parentId];
};

const submitNewChild = (parentId) => {
    const name = (newChildName.value[parentId] || '').trim();
    if (!name) return;
    creatingChild.value[parentId] = true;
    errorByParent.value[parentId] = null;
    const url = safeRoute('admin.categories.storeSubcategory', { parentCategory: parentId }, `/admin/categories/${parentId}/subcategories`);
    router.post(url, { name }, {
        preserveScroll: true,
        onSuccess: () => {
            newChildName.value[parentId] = '';
            creatingChild.value[parentId] = false;
            // Si el formulario estaba en un hijo expandido, asegurar expansión y recarga de ese bloque
            expandedByParent.value[parentId] = true;
            loadChildrenFor(parentId);
            // Además, refrescar el nivel raíz para actualizar counters
            loadChildren();
        },
        onError: () => {
            creatingChild.value[parentId] = false;
            errorByParent.value[parentId] = 'No se pudo crear el subnivel';
        }
    });
};

// Cancelar formulario de nuevo subnivel
const cancelAddChild = (parentId) => {
    newChildName.value[parentId] = '';
    errorByParent.value[parentId] = null;
    showAddByParent.value[parentId] = false;
};

// Renombrar subcategoría/subnivel inline
const renamingById = ref({});
const renameValueById = ref({});
const startRename = (child) => {
    renamingById.value[child.id] = true;
    renameValueById.value[child.id] = child.name;
};
const cancelRename = (id) => {
    renamingById.value[id] = false;
    renameValueById.value[id] = '';
};
const submitRename = (id, parentId = null) => {
    const val = (renameValueById.value[id] || '').trim();
    if (!val) return;
    const url = route('admin.categories.update', { category: id });
    // Evitamos navegación de Inertia para que no reordene visualmente; actualizamos por axios
    if (window.axios) {
        // Usamos POST + _method=PUT para máxima compatibilidad con la ruta resource
        window.axios.post(url, { name: val, _method: 'PUT' }, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(() => {
                cancelRename(id);
                if (parentId && parentId !== props.category.id) {
                    loadChildrenFor(parentId);
                } else {
                    loadChildren();
                }
            })
            .catch(() => cancelRename(id));
        return;
    }
    // Fallback si no existe axios
    router.put(url, { name: val }, { preserveScroll: true, onSuccess: () => { cancelRename(id); parentId && parentId !== props.category.id ? loadChildrenFor(parentId) : loadChildren(); } });
};

// Expandir/colapsar y cargar subniveles de un hijo
const expandedByParent = ref({});
const loadingByParent = ref({});
const childrenByParent = ref({});

const loadChildrenFor = async (parentId) => {
    loadingByParent.value[parentId] = true;
    try {
        const url = safeRoute('admin.categories.children', { category: parentId }, `/admin/categories/${parentId}/children`);
        const res = await fetch(url);
        if (!res.ok) return;
        const json = await res.json();
        childrenByParent.value[parentId] = Array.isArray(json.data) ? json.data : [];
    } finally {
        loadingByParent.value[parentId] = false;
    }
};

const toggleExpandChildren = async (parentId) => {
    const willExpand = !expandedByParent.value[parentId];
    expandedByParent.value[parentId] = willExpand;
    if (willExpand && !Array.isArray(childrenByParent.value[parentId])) {
        await loadChildrenFor(parentId);
    }
};

// Contexto para refrescar correctamente tras eliminar
const deleteContextParentId = ref(null);
const askDeleteCategory = (categoryId, parentId = null) => {
    categoryToDelete.value = categoryId;
    deleteContextParentId.value = parentId;
    confirmingDeletion.value = true;
};

// Modal de error
const showError = ref(false);
const errorText = ref('');
</script>

<template>
    <Head :title="`Editar: ${category.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Editando Categoría: {{ category.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium">Editar Nombre</h3>
                        <form @submit.prevent="updateCategoryName" class="mt-4 flex items-center gap-4">
                            <input v-model="form.name" type="text" class="block w-full rounded-md shadow-sm border-gray-300">
                            <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                Actualizar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium">Gestionar Subcategorías</h3>
                            <div class="flex items-center gap-2">
                                <button type="button" class="w-7 h-7 inline-flex items-center justify-center rounded border border-gray-300 hover:bg-gray-100 text-gray-700" title="Expandir todo" @click="() => { children.forEach(c => expandedByParent[c.id] = true); children.forEach(c => { if (!Array.isArray(childrenByParent[c.id])) loadChildrenFor(c.id); }); }">+
                                </button>
                                <button type="button" class="w-7 h-7 inline-flex items-center justify-center rounded border border-gray-300 hover:bg-gray-100 text-gray-700" title="Colapsar todo" @click="() => { children.forEach(c => expandedByParent[c.id] = false); }">−
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 border rounded-lg divide-y">
                            <p v-if="isLoadingChildren" class="text-sm text-gray-500">Cargando subcategorías…</p>
                            <p v-else-if="!children || children.length === 0" class="text-sm text-gray-500">
                                Esta categoría aún no tiene subcategorías.
                            </p>
                            <div v-for="child in children" :key="child.id" class="p-3 hover:bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <div class="flex-1">
                                        <div v-if="!renamingById[child.id]" class="flex items-center gap-2">
                                            <!-- Toggle izquierda: > / v -->
                                            <button type="button" class="w-6 h-6 inline-flex items-center justify-center rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100" :title="expandedByParent[child.id] ? 'Colapsar subniveles' : 'Expandir subniveles'" @click="toggleExpandChildren(child.id)">
                                                <span v-if="!expandedByParent[child.id]">&gt;</span>
                                                <span v-else>v</span>
                                            </button>
                                            <span class="font-medium">{{ child.name }}</span>
                                            <span v-if="typeof child.children_count !== 'undefined'" class="text-xs bg-gray-100 text-gray-700 rounded px-2 py-0.5 align-middle">({{ child.children_count }})</span>
                                        </div>
                                        <div v-else class="flex items-center gap-2">
                                            <input v-model="renameValueById[child.id]" type="text" class="block w-full rounded-md shadow-sm border-gray-300" />
                                            <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded bg-blue-500 text-white hover:bg-blue-600" title="Guardar" @click="submitRename(child.id, category.id)">✔</button>
                                            <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100" title="Cancelar" @click="cancelRename(child.id)">✖</button>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-indigo-600" title="Renombrar" @click="startRename(child)">
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                        </button>
                                        <!-- Botón añadir subnivel con estilo sutil -->
                                        <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded border border-green-500 text-green-600 hover:bg-green-50" title="Añadir subnivel" @click="toggleAddChild(child.id)">
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <rect x="4" y="3" width="11" height="15" rx="2" ry="2" stroke-width="1.5" />
                                                <circle cx="18" cy="18" r="3" stroke-width="1.5" />
                                                <path stroke-linecap="round" stroke-width="1.5" d="M18 16.5v3M16.5 18h3" />
                                            </svg>
                                        </button>
                                        <button @click="askDeleteCategory(child.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-red-600" title="Eliminar">
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="showAddByParent[child.id]" class="mt-2 pl-2 bg-gray-50 rounded-md p-2">
                                    <label class="block text-xs text-gray-600 mb-1">Añadir Nueva Subcategoría</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model="newChildName[child.id]" type="text" class="block w-full rounded-md shadow-sm border-gray-300" placeholder="Ej: Baños" />
                                        <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded bg-green-500 text-white hover:bg-green-600" :disabled="creatingChild[child.id]" @click="submitNewChild(child.id)">✔</button>
                                        <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-200 text-gray-800 hover:bg-gray-300" @click="cancelAddChild(child.id)">✖</button>
                                    </div>
                                    <p v-if="errorByParent[child.id]" class="text-xs text-red-600 mt-1">{{ errorByParent[child.id] }}</p>
                                </div>
                                <div v-if="expandedByParent[child.id]" class="mt-2 pl-4 border-l">
                                    <p v-if="loadingByParent[child.id]" class="text-sm text-gray-500">Cargando…</p>
                                    <div v-for="gchild in (childrenByParent[child.id] || [])" :key="gchild.id" class="py-1 flex justify-between items-center">
                                        <div class="flex-1">
                                            <div v-if="!renamingById[gchild.id]">{{ gchild.name }}</div>
                                            <div v-else class="flex items-center gap-2">
                                                <input v-model="renameValueById[gchild.id]" type="text" class="block w-full rounded-md shadow-sm border-gray-300" />
                                                <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded bg-blue-500 text-white hover:bg-blue-600" title="Guardar" @click="submitRename(gchild.id, child.id)">✔</button>
                                                <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100" title="Cancelar" @click="cancelRename(gchild.id)">✖</button>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-indigo-600" title="Renombrar" @click="startRename(gchild)">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                            </button>
                                            
                                            <!-- Nivel 3: ya no permitir crear otro nivel debajo (límite) -->
                                            <button @click="askDeleteCategory(gchild.id, child.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-red-600" title="Eliminar">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/></svg>
                                            </button>
                                        </div>
                                        <div v-if="showAddByParent[gchild.id]" class="mt-2 pl-2 bg-gray-50 rounded-md p-2 w-full">
                                            <label class="block text-xs text-gray-600 mb-1">Nombre del subnivel</label>
                                            <div class="flex items-center gap-2">
                                                <input v-model="newChildName[gchild.id]" type="text" class="block w-full rounded-md shadow-sm border-gray-300" placeholder="Ej: Accesorios" />
                                                <button type="button" class="bg-green-500 text-white font-bold py-1 px-3 rounded hover:bg-green-600" :disabled="creatingChild[gchild.id]" @click="submitNewChild(gchild.id)">Añadir</button>
                                            </div>
                                            <p v-if="errorByParent[gchild.id]" class="text-xs text-red-600 mt-1">{{ errorByParent[gchild.id] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="addNewSubcategory" class="mt-6 border-t pt-4">
                            <label class="block font-medium text-sm text-gray-700">Añadir Nueva Subcategoría</label>
                            <div class="mt-2 flex items-center gap-2">
                                <input v-model="newSubcategoryForm.name" type="text" class="block w-full rounded-md shadow-sm border-gray-300" placeholder="Nombre de la nueva subcategoría">
                                <button type="submit" :disabled="newSubcategoryForm.processing" class="w-8 h-8 inline-flex items-center justify-center rounded bg-green-500 text-white hover:bg-green-600" title="Añadir">✔</button>
                                <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-200 text-gray-800 hover:bg-gray-300" @click="cancelNewSubcategory" title="Cancelar">✖</button>
                            </div>
                            <p v-if="newSubcategoryForm.errors.name" class="text-sm text-red-600 mt-2">{{ newSubcategoryForm.errors.name }}</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="confirmingDeletion" @close="closeDeleteModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Querés eliminar esta subcategoría?
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Esta acción es irreversible y removerá la subcategoría seleccionada.
            </p>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeDeleteModal">Cancelar</SecondaryButton>
                <DangerButton class="ms-3" @click="deleteCategory">Sí, eliminar</DangerButton>
            </div>
        </div>
    </Modal>

    <AlertModal
        :show="showError"
        type="error"
        title="No se puede eliminar"
        :messages="[errorText]"
        primary-text="Entendido"
        @primary="showError=false"
        @close="showError=false"
    />

</template>