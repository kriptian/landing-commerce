<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import MetricCard from '@/Components/Admin/MetricCard.vue';
import SurfaceCard from '@/Components/Admin/SurfaceCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    store: Object,
    metrics: Object,
});

const page = usePage();
const copied = ref(false);
const copyError = ref(false);
const capabilities = computed(() => page.props.auth?.capabilities || {});
const plan = computed(() => props.store?.plan || 'emprendedor');
const planLabel = computed(() => ({
    emprendedor: 'Emprendedor',
    negociante: 'Negociante',
    creador_pdf: 'Creador PDF',
})[plan.value] || plan.value);
const storeUrl = computed(() => {
    if (! props.store) return '#';
    if (props.store.custom_domain) {
        const protocol = typeof window !== 'undefined' ? window.location.protocol : 'https:';
        return `${protocol}//${props.store.custom_domain}`;
    }
    return route('catalogo.index', { store: props.store.slug });
});
const formatCurrency = (value) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format(value || 0);
const openUpgrade = () => window.dispatchEvent(new CustomEvent('open-upgrade-plan'));
const copyUrl = async () => {
    copied.value = false;
    copyError.value = false;
    try {
        await navigator.clipboard.writeText(storeUrl.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1800);
    } catch (error) {
        copyError.value = true;
    }
};

const quickActions = computed(() => [
    {
        id: 'products',
        title: 'Productos',
        description: 'Publica, organiza y actualiza tu oferta.',
        routeName: 'admin.products.index',
        color: 'bg-cyan-50 text-cyan-700',
    },
    {
        id: 'categories',
        title: 'Categorias',
        description: 'Ordena el catalogo para que sea facil comprar.',
        routeName: 'admin.categories.index',
        color: 'bg-emerald-50 text-emerald-700',
    },
    {
        id: 'users',
        title: 'Equipo',
        description: 'Administra usuarios, roles y permisos.',
        routeName: 'admin.users.index',
        color: 'bg-violet-50 text-violet-700',
    },
].filter((action) => capabilities.value[action.id] !== 'hidden'));
</script>

<template>
    <Head title="Inicio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">Panel de comercio</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Buenos dias, {{ page.props.auth?.user?.name }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Tienda activa
                    </span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">{{ planLabel }}</span>
                </div>
            </div>
        </template>

        <AdminPage dusk="admin-dashboard">
            <section class="relative overflow-hidden rounded-3xl bg-slate-950 px-5 py-7 text-white shadow-xl shadow-slate-200 sm:px-8 sm:py-9">
                <div class="relative z-10 max-w-2xl">
                    <span class="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-semibold text-cyan-200">Tu negocio, en movimiento</span>
                    <h2 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Todo lo que necesitas para vender, en un solo lugar.</h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300 sm:text-base">Revisa el pulso de la tienda y continua con las tareas que hacen crecer tu catalogo.</p>
                </div>
                <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-cyan-400/20 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 right-24 h-40 w-40 rounded-full bg-emerald-400/10 blur-3xl"></div>
            </section>

            <section class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <MetricCard label="Ventas online entregadas" :value="formatCurrency(metrics?.salesToday)" hint="Pedidos creados y entregados hoy" tone="cyan">
                    <template #icon><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 19V9M12 19V5M19 19v-7" stroke-linecap="round" /></svg></template>
                </MetricCard>
                <MetricCard label="Entregas online" :value="metrics?.ordersToday || 0" hint="Pedidos con estado entregado hoy" tone="violet">
                    <template #icon><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 7h14v12H5zM8 4h8v3M8 12l2.5 2.5L16 9" stroke-linecap="round" stroke-linejoin="round" /></svg></template>
                </MetricCard>
                <MetricCard label="Ticket promedio online" :value="formatCurrency(metrics?.avgTicketToday)" hint="Promedio de las entregas de hoy" tone="emerald">
                    <template #icon><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M16 7.5c0-1.4-1.8-2.5-4-2.5S8 6.1 8 7.5 9.8 10 12 10s4 1.1 4 2.5S14.2 15 12 15s-4-1.1-4-2.5" stroke-linecap="round" /></svg></template>
                </MetricCard>
            </section>

            <SurfaceCard v-if="store" class="mt-6">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-sm font-bold text-slate-950">Comparte tu tienda</p>
                        <p class="mt-1 text-sm text-slate-500">Tu catalogo esta listo para recibir clientes.</p>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row xl:max-w-3xl">
                        <div class="min-w-0 flex-1 truncate rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 font-mono text-sm text-slate-600" :title="storeUrl">{{ storeUrl }}</div>
                        <button type="button" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800" @click="copyUrl">{{ copied ? 'Copiado' : 'Copiar enlace' }}</button>
                        <a :href="storeUrl" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Visitar tienda</a>
                    </div>
                </div>
                <p v-if="copied" class="mt-3 text-sm font-medium text-emerald-700" role="status">Enlace copiado al portapapeles.</p>
                <p v-if="copyError" class="mt-3 text-sm font-medium text-rose-700" role="alert">No pudimos copiar el enlace. Puedes seleccionarlo manualmente.</p>
            </SurfaceCard>

            <section v-if="quickActions.length" class="mt-8">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-950">Accesos rapidos</h2>
                    <p class="mt-1 text-sm text-slate-500">Continua donde tu operacion lo necesita.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <SurfaceCard v-for="action in quickActions" :key="action.id" class="group transition hover:-translate-y-0.5 hover:shadow-lg">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-sm font-black" :class="action.color">{{ action.title.slice(0, 2).toUpperCase() }}</span>
                        <h3 class="mt-5 font-bold text-slate-950">{{ action.title }}</h3>
                        <p class="mt-2 min-h-10 text-sm leading-5 text-slate-500">{{ action.description }}</p>
                        <button v-if="capabilities[action.id] === 'locked'" type="button" class="mt-5 text-sm font-bold text-amber-600 hover:text-amber-700" @click="openUpgrade">Disponible en Negociante →</button>
                        <Link v-else :href="route(action.routeName)" class="mt-5 inline-flex text-sm font-bold text-cyan-700 hover:text-cyan-800">Abrir modulo →</Link>
                    </SurfaceCard>
                </div>
            </section>
        </AdminPage>
    </AuthenticatedLayout>
</template>
