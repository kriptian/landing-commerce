<script setup>
import AdminPage from '@/Components/Admin/AdminPage.vue';
import CategoryTreeNode from '@/Components/Admin/CategoryTreeNode.vue';
import SurfaceCard from '@/Components/Admin/SurfaceCard.vue';
import AlertModal from '@/Components/AlertModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const query = ref('');
const expandedIds = reactive(new Set(props.categories.map((category) => category.id)));
const categoryToDelete = ref(null);
const deleting = ref(false);
const notice = ref({ show: false, type: 'error', message: '' });

const normalize = (value) => value.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');

const filterTree = (categories, term) => categories.reduce((results, category) => {
    const children = filterTree(category.children, term);
    if (normalize(category.path).includes(term)) {
        results.push(category);
    } else if (children.length) {
        results.push({ ...category, children });
    }
    return results;
}, []);

const filteredCategories = computed(() => {
    const term = normalize(query.value.trim());
    return term ? filterTree(props.categories, term) : props.categories;
});

const totals = computed(() => {
    const walk = (categories) => categories.reduce((result, category) => ({
        categories: result.categories + 1 + walk(category.children).categories,
        products: result.products + category.direct_products_count + walk(category.children).products,
    }), { categories: 0, products: 0 });

    return walk(props.categories);
});

const toggle = (id) => {
    if (expandedIds.has(id)) expandedIds.delete(id);
    else expandedIds.add(id);
};

const closeDeleteModal = () => {
    if (deleting.value) return;
    categoryToDelete.value = null;
};

const deleteCategory = () => {
    deleting.value = true;
    router.delete(route('admin.categories.destroy', categoryToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            categoryToDelete.value = null;
            notice.value = { show: true, type: 'success', message: 'La categoria se elimino correctamente.' };
        },
        onError: (errors) => {
            categoryToDelete.value = null;
            notice.value = {
                show: true,
                type: 'error',
                message: errors?.delete || 'No se pudo eliminar la categoria. Revisa si tiene productos asociados.',
            };
        },
        onFinish: () => { deleting.value = false; },
    });
};
</script>

<template>
    <Head title="Categorias" />

    <AuthenticatedLayout>
        <AdminPage>
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">Catalogo</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Categorias</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600 sm:text-base">
                        Organiza tus productos en hasta tres niveles para que tus clientes los encuentren facilmente.
                    </p>
                </div>
                <Link
                    :href="route('admin.categories.create')"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Nueva categoria
                </Link>
            </div>

            <div class="mb-5 grid grid-cols-2 gap-3 sm:max-w-md">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <p class="text-2xl font-bold text-slate-950">{{ totals.categories }}</p>
                    <p class="text-xs font-medium text-slate-500">Categorias creadas</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <p class="text-2xl font-bold text-slate-950">{{ totals.products }}</p>
                    <p class="text-xs font-medium text-slate-500">Productos organizados</p>
                </div>
            </div>

            <SurfaceCard>
                <div class="mb-5 flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900">Organizacion de la tienda</h2>
                        <p class="mt-1 text-sm text-slate-500">Abre cada grupo para ver sus subcategorias.</p>
                    </div>
                    <label class="relative block sm:w-80">
                        <span class="sr-only">Buscar una categoria</span>
                        <svg class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                        <input v-model="query" type="search" class="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Buscar por nombre o ruta" />
                    </label>
                </div>

                <div v-if="!categories.length" class="rounded-xl border-2 border-dashed border-slate-200 px-6 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">
                        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.5 5.75A2.75 2.75 0 0 1 5.25 3h3.086c.73 0 1.43.29 1.945.805l.664.665c.235.234.553.366.884.366h2.921a2.75 2.75 0 0 1 2.75 2.75v6.664A2.75 2.75 0 0 1 14.75 17h-9.5a2.75 2.75 0 0 1-2.75-2.75v-8.5Z" /></svg>
                    </div>
                    <h2 class="mt-4 font-semibold text-slate-900">Crea tu primera categoria</h2>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Empieza con un grupo general como Ropa, Hogar o Accesorios. Luego puedes agregar subcategorias.</p>
                    <Link :href="route('admin.categories.create')" class="mt-5 inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Crear categoria</Link>
                </div>

                <div v-else-if="!filteredCategories.length" class="px-4 py-10 text-center">
                    <h2 class="font-semibold text-slate-900">No encontramos esa categoria</h2>
                    <p class="mt-1 text-sm text-slate-500">Prueba con otro nombre o borra la busqueda.</p>
                    <button type="button" class="mt-4 text-sm font-semibold text-indigo-700 hover:text-indigo-900" @click="query = ''">Limpiar busqueda</button>
                </div>

                <ul v-else class="space-y-2">
                    <CategoryTreeNode
                        v-for="category in filteredCategories"
                        :key="category.id"
                        :category="category"
                        :expanded-ids="expandedIds"
                        :searching="Boolean(query.trim())"
                        @toggle="toggle"
                        @delete="categoryToDelete = $event"
                    />
                </ul>
            </SurfaceCard>
        </AdminPage>
    </AuthenticatedLayout>

    <Modal :show="Boolean(categoryToDelete)" @close="closeDeleteModal">
        <div v-if="categoryToDelete" class="p-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-rose-600">Eliminar categoria</p>
            <h2 class="mt-2 text-lg font-semibold text-slate-950">¿Eliminar {{ categoryToDelete.name }}?</h2>
            <p class="mt-2 text-sm text-slate-600">
                Tambien se eliminaran {{ categoryToDelete.descendants_count }} subcategorias. Esta accion no se puede deshacer.
            </p>
            <div v-if="categoryToDelete.subtree_products_count" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                No podras eliminarla mientras tenga {{ categoryToDelete.subtree_products_count }} producto(s) asociados. Muevelos primero a otra categoria.
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton :disabled="deleting" @click="closeDeleteModal">Cancelar</SecondaryButton>
                <DangerButton :disabled="deleting || categoryToDelete.subtree_products_count > 0" @click="deleteCategory">
                    {{ deleting ? 'Eliminando...' : 'Eliminar categoria' }}
                </DangerButton>
            </div>
        </div>
    </Modal>

    <AlertModal
        :show="notice.show"
        :type="notice.type"
        title="Categorias"
        :message="notice.message"
        primary-text="Entendido"
        @primary="notice.show = false"
        @close="notice.show = false"
    />
</template>
