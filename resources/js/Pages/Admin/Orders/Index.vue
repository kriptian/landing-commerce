<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    orders: Object,
    filters: Object,
});

const q = ref(props.filters?.q || '');
const start = ref(props.filters?.start || '');
const end = ref(props.filters?.end || '');

const statuses = [
    { value: '', label: 'Todos' },
    { value: 'recibido', label: 'Recibidos' },
    { value: 'en_preparacion', label: 'En preparacion' },
    { value: 'despachado', label: 'Despachados' },
    { value: 'entregado', label: 'Entregados' },
    { value: 'cancelado', label: 'Cancelados' },
];

const applyFilters = (status = props.filters?.status || '') => {
    router.get(route('admin.orders.index'), {
        status: status || undefined,
        q: q.value || undefined,
        start: start.value || undefined,
        end: end.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

const clearFilters = () => {
    q.value = '';
    start.value = '';
    end.value = '';
    applyFilters('');
};

const formatDate = (datetime) => new Date(datetime).toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const formatCurrency = (value) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const statusLabel = (status) => statuses.find((item) => item.value === status)?.label || status;

const statusClass = (status) => ({
    recibido: 'bg-amber-50 text-amber-700',
    en_preparacion: 'bg-blue-50 text-blue-700',
    despachado: 'bg-violet-50 text-violet-700',
    entregado: 'bg-emerald-50 text-emerald-700',
    cancelado: 'bg-rose-50 text-rose-700',
}[status] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <Head title="Pedidos" />

    <AuthenticatedLayout>
        <AdminPage>
            <PageHeader eyebrow="Ventas online" title="Pedidos" description="Consulta nuevas compras, coordina entregas y mantén informado a cada cliente." />

            <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Estados de pedidos">
                <button
                    v-for="item in statuses"
                    :key="item.value"
                    type="button"
                    class="min-h-10 shrink-0 rounded-full border px-4 text-sm font-bold transition"
                    :class="(filters?.status || '') === item.value ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-700'"
                    @click="applyFilters(item.value)"
                >
                    {{ item.label }}
                </button>
            </nav>

            <form class="ui-card grid gap-3 p-4 md:grid-cols-[minmax(240px,1fr)_170px_170px_auto] md:p-5" @submit.prevent="applyFilters()">
                <div><label for="order-search" class="ui-label">Cliente o telefono</label><input id="order-search" v-model="q" class="ui-input" placeholder="Ej. Maria o 300..." /></div>
                <div><label for="order-start" class="ui-label">Desde</label><input id="order-start" v-model="start" type="date" class="ui-input" /></div>
                <div><label for="order-end" class="ui-label">Hasta</label><input id="order-end" v-model="end" type="date" class="ui-input" /></div>
                <div class="flex items-end gap-2"><button type="submit" class="ui-primary-button flex-1">Aplicar</button><button type="button" class="ui-secondary-button" @click="clearFilters">Limpiar</button></div>
            </form>

            <div v-if="!orders.data?.length" class="ui-card p-10 text-center">
                <h2 class="text-lg font-bold text-slate-900">No hay pedidos con estos filtros</h2>
                <p class="mt-2 text-sm text-slate-500">Prueba otro estado, cliente o rango de fechas.</p>
                <button type="button" class="ui-secondary-button mt-5" @click="clearFilters">Ver todos los pedidos</button>
            </div>

            <div v-else class="space-y-3 lg:hidden">
                <article v-for="order in orders.data" :key="order.id" class="ui-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Pedido</p><h2 class="mt-1 text-lg font-extrabold text-slate-900">#{{ order.sequence_number ?? order.id }}</h2></div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(order.status)">{{ statusLabel(order.status) }}</span>
                    </div>
                    <div class="mt-4"><p class="font-bold text-slate-900">{{ order.customer_name }}</p><p class="text-sm text-slate-500">{{ order.customer_phone || 'Sin telefono' }}</p></div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm"><div><dt class="text-slate-500">Fecha</dt><dd class="mt-1 font-semibold text-slate-800">{{ formatDate(order.created_at) }}</dd></div><div><dt class="text-slate-500">Total</dt><dd class="mt-1 font-extrabold text-slate-900">{{ formatCurrency(order.total_price) }}</dd></div></dl>
                    <Link :href="route('admin.orders.show', order.id)" class="ui-primary-button mt-4 w-full">Gestionar pedido</Link>
                </article>
            </div>

            <div v-if="orders.data?.length" class="ui-card hidden overflow-hidden lg:block">
                <div class="overflow-x-auto"><table class="w-full"><thead><tr><th class="px-5 py-4 text-left">Pedido</th><th class="px-5 py-4 text-left">Cliente</th><th class="px-5 py-4 text-left">Fecha</th><th class="px-5 py-4 text-left">Productos</th><th class="px-5 py-4 text-left">Total</th><th class="px-5 py-4 text-left">Estado</th><th class="px-5 py-4 text-right">Accion</th></tr></thead><tbody><tr v-for="order in orders.data" :key="order.id"><td class="px-5 py-4 font-extrabold text-slate-900">#{{ order.sequence_number ?? order.id }}</td><td class="px-5 py-4"><p class="font-bold text-slate-900">{{ order.customer_name }}</p><p class="text-sm text-slate-500">{{ order.customer_phone || 'Sin telefono' }}</p></td><td class="px-5 py-4 text-sm text-slate-600">{{ formatDate(order.created_at) }}</td><td class="px-5 py-4 text-sm text-slate-600">{{ order.items_count }}</td><td class="px-5 py-4 font-extrabold text-slate-900">{{ formatCurrency(order.total_price) }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(order.status)">{{ statusLabel(order.status) }}</span></td><td class="px-5 py-4 text-right"><Link :href="route('admin.orders.show', order.id)" class="ui-secondary-button">Gestionar</Link></td></tr></tbody></table></div>
            </div>

            <Pagination v-if="orders.links" :links="orders.links" />
        </AdminPage>
    </AuthenticatedLayout>
</template>
