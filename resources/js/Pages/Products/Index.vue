<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AlertModal from '@/Components/AlertModal.vue';
import Pagination from '@/Components/Pagination.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
    products: Object,
    categories: Array,
    store: Object,
    filters: Object,
});

const page = usePage();
const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || '');
const selectedStatus = ref(props.filters?.status || '');
const confirmingProductDeletion = ref(false);
const productToDelete = ref(null);
const showProductNotice = ref(page?.props?.flash?.success ? true : false);
const productNotice = ref(page?.props?.flash?.success || '');
const confirmProductDeletion = (id) => {
    productToDelete.value = id;
    confirmingProductDeletion.value = true;
};

const closeModal = () => {
    confirmingProductDeletion.value = false;
    productToDelete.value = null;
};

const deleteProduct = () => {
    router.delete(route('admin.products.destroy', productToDelete.value), {
        onSuccess: () => {
            closeModal();
            productNotice.value = '¡Producto eliminado con éxito!';
            showProductNotice.value = true;
        }
    });
};
const togglePromo = (product) => {
    router.put(route('admin.products.update', product.id), { promo_active: !product.promo_active, promo_discount_percent: product.promo_discount_percent }, { preserveScroll: true });
};
const updatePromoPercent = (product, val) => {
    const pct = Math.max(1, Math.min(90, Number(val) || 0));
    router.put(route('admin.products.update', product.id), { promo_discount_percent: pct, promo_active: true }, { preserveScroll: true });
};
const toggleStorePromo = () => {
    router.put(route('admin.products.store_promo'), { promo_active: !props.store.promo_active, promo_discount_percent: props.store.promo_discount_percent }, { preserveScroll: true });
};
const updateStorePromoPercent = (val) => {
    const pct = Math.max(1, Math.min(90, Number(val) || 0));
    router.put(route('admin.products.store_promo'), { promo_active: true, promo_discount_percent: pct }, { preserveScroll: true });
};
const applyFilters = () => {
    router.get(route('admin.products.index'), { 
        search: search.value, 
        category: selectedCategory.value, 
        status: selectedStatus.value 
    }, { 
        preserveState: true, 
        replace: true, 
        preserveScroll: true 
    });
};
const getDisplayStock = (product) => {
    if (product.variants && product.variants.length > 0 && product.variant_options && product.variant_options.length > 0) {
        return product.variants_sum_stock ?? product.variants.reduce((acc, v) => acc + (Number(v.stock) || 0), 0) ?? 0;
    }
    
    // Si es simple (o tiene datos inconsistentes), usar quantity del producto principal
    return product.quantity;
};

const clearFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const formatPrice = (price) => Number(price || 0).toLocaleString('es-CO');
</script>

<template>
    <Head title="Gestionar Productos" />

    <AuthenticatedLayout>
        <AdminPage>
            <PageHeader eyebrow="Catalogo" title="Productos" description="Controla disponibilidad, inventario y promociones desde un solo lugar.">
                <template #actions><Link :href="route('admin.products.create')" class="ui-primary-button">+ Nuevo producto</Link></template>
            </PageHeader>

            <section class="ui-card p-4 sm:p-5" aria-label="Filtros de productos">
                <form class="grid gap-3 md:grid-cols-[minmax(220px,1fr)_220px_170px_auto]" @submit.prevent="applyFilters">
                    <div><label for="product-search" class="ui-label">Buscar</label><input id="product-search" v-model="search" class="ui-input" placeholder="Nombre del producto" /></div>
                    <div><label for="product-category" class="ui-label">Categoria</label><select id="product-category" v-model="selectedCategory" class="ui-input"><option value="">Todas</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
                    <div><label for="product-status" class="ui-label">Estado</label><select id="product-status" v-model="selectedStatus" class="ui-input"><option value="">Todos</option><option value="active">Activos</option><option value="inactive">Inactivos</option></select></div>
                    <div class="flex items-end gap-2"><button type="submit" class="ui-primary-button flex-1">Aplicar</button><button type="button" class="ui-secondary-button" @click="clearFilters">Limpiar</button></div>
                </form>
            </section>

            <section class="ui-card p-4 sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-base font-bold text-slate-900">Promocion general</h2><p class="text-sm text-slate-500">Aplica el mismo descuento a todo el catalogo.</p></div><div class="flex items-center gap-3"><label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" :checked="store.promo_active" class="rounded border-slate-300" @change="toggleStorePromo">Activa</label><label class="sr-only" for="store-promo">Porcentaje</label><input id="store-promo" type="number" min="1" max="90" :value="store.promo_discount_percent ?? ''" class="ui-input mt-0 w-24" placeholder="%" @change="updateStorePromoPercent($event.target.value)"></div></div>
            </section>

            <div v-if="!products.data?.length" class="ui-card p-10 text-center"><h2 class="text-lg font-bold text-slate-900">Tu catalogo esta vacio</h2><p class="mt-2 text-sm text-slate-500">Crea el primer producto para comenzar a vender.</p><Link :href="route('admin.products.create')" class="ui-primary-button mt-5">Crear producto</Link></div>

            <div v-else class="space-y-3 lg:hidden">
                <article v-for="product in products.data" :key="product.id" class="ui-card p-4">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="truncate font-bold text-slate-900">{{ product.name }}</h2><span :class="product.is_active ? 'ui-badge-success' : 'ui-badge-neutral'">{{ product.is_active ? 'Activo' : 'Inactivo' }}</span></div><p class="mt-1 text-sm text-slate-500">{{ product.category?.name || 'Sin categoria' }}</p></div><p class="shrink-0 font-extrabold text-slate-900">$ {{ formatPrice(product.price) }}</p></div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm"><div><dt class="text-slate-500">Inventario</dt><dd class="font-bold text-slate-900">{{ getDisplayStock(product) }} unidades</dd></div><div><dt class="text-slate-500">Promocion</dt><dd class="font-bold text-slate-900">{{ product.promo_active ? `${product.promo_discount_percent || 0}%` : 'Sin descuento' }}</dd></div></dl>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><div class="flex gap-4"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" :checked="product.is_active" @change="router.put(route('admin.products.update', product.id), { is_active: !product.is_active }, { preserveScroll: true })">Visible</label><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" :checked="product.promo_active" @change="togglePromo(product)">Promo</label></div><div class="flex gap-2"><Link :href="route('admin.products.edit', product.id)" class="ui-secondary-button">Editar</Link><button class="rounded-xl px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-50" @click="confirmProductDeletion(product.id)">Eliminar</button></div></div>
                </article>
            </div>

            <div v-if="products.data?.length" class="ui-card hidden overflow-hidden lg:block"><div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="px-5 py-4 text-left">Producto</th><th class="px-5 py-4 text-left">Precio</th><th class="px-5 py-4 text-left">Inventario</th><th class="px-5 py-4 text-left">Estado</th><th class="px-5 py-4 text-left">Promocion</th><th class="px-5 py-4 text-right">Acciones</th></tr></thead><tbody><tr v-for="product in products.data" :key="product.id"><td class="px-5 py-4"><p class="font-bold text-slate-900">{{ product.name }}</p><p class="text-sm text-slate-500">{{ product.category?.name || 'Sin categoria' }}</p></td><td class="px-5 py-4 font-semibold">$ {{ formatPrice(product.price) }}</td><td class="px-5 py-4">{{ getDisplayStock(product) }}</td><td class="px-5 py-4"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" :checked="product.is_active" @change="router.put(route('admin.products.update', product.id), { is_active: !product.is_active }, { preserveScroll: true })">{{ product.is_active ? 'Activo' : 'Inactivo' }}</label></td><td class="px-5 py-4"><div class="flex items-center gap-2"><input type="checkbox" :checked="product.promo_active" @change="togglePromo(product)"><input type="number" min="1" max="90" :value="product.promo_discount_percent ?? ''" class="ui-input mt-0 w-20" aria-label="Porcentaje de promocion" @change="updatePromoPercent(product, $event.target.value)"></div></td><td class="px-5 py-4"><div class="flex justify-end gap-2"><Link :href="route('admin.products.edit', product.id)" class="ui-secondary-button">Editar</Link><button class="rounded-xl px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-50" @click="confirmProductDeletion(product.id)">Eliminar</button></div></td></tr></tbody></table></div></div>

            <div v-if="products.links" class="mt-6"><Pagination :links="products.links" /></div>
        </AdminPage>
    </AuthenticatedLayout>
    
    <Modal :show="confirmingProductDeletion" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Estás seguro de que querés eliminar este producto?
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Esta acción es irreversible. Se borrará el producto con todas sus variantes e imágenes.
            </p>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    @click="deleteProduct"
                >
                    Sí, Eliminar Producto
                </DangerButton>
            </div>
        </div>
    </Modal>
    
    <AlertModal
        :show="showProductNotice"
        type="success"
        title="Producto"
        :message="productNotice"
        primary-text="Entendido"
        @primary="showProductNotice=false"
        @close="showProductNotice=false"
    />
</template>
