<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Le decimos al componente que va a recibir la información de la tienda
const props = defineProps({
    store: Object,
    metrics: Object,
});

// URL pública de la tienda y acción de copiar
const storeUrl = computed(() => {
    if (!props.store) return '#';
    if (props.store.custom_domain) {
        const protocol = typeof window !== 'undefined' ? window.location.protocol : 'https:';
        return `${protocol}//${props.store.custom_domain}`;
    }
    return route('catalogo.index', { store: props.store.slug });
});
const copied = ref(false);
const copyUrl = async () => {
    try {
        await navigator.clipboard.writeText(storeUrl.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch (e) {
        // Silencioso: algunos navegadores pueden bloquear clipboard sin HTTPS
    }
};

// Plan y CTA de upgrade para cards
const page = usePage();
const plan = computed(() => page.props.auth?.user?.store?.plan || 'emprendedor');
const isNegociante = computed(() => plan.value === 'negociante' || page.props.auth?.isSuperAdmin === true);
const openUpgrade = () => { try { window.dispatchEvent(new CustomEvent('open-upgrade-plan')); } catch(e) {} };
</script>

<template>
    <Head title="Dashboard">
        <template #default>
            <link rel="icon" type="image/png" href="/images/digitalsolution-logo.png">
        </template>
    </Head>

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
                <div v-if="store" class="hidden md:flex items-center gap-3 text-sm text-gray-600">
                    <span class="inline-flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Tienda activa</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-0.5 font-medium">
                        Plan: {{ plan === 'negociante' ? 'Negociante' : 'Emprendedor' }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-800 via-blue-700 to-cyan-600 text-white shadow-md sm:rounded-2xl">
                    <div class="relative z-10 p-6 md:p-8">
                        <h3 class="text-lg md:text-xl font-semibold">¡Bienvenido a tu panel de administración!</h3>
                        <div class="h-1 w-14 bg-orange-400 rounded-full mt-2"></div>
                        <p class="text-sm/6 md:text-base/6 text-white/90 mt-2">Gestioná productos, categorías, pedidos y más desde un solo lugar.</p>
                    </div>
                    <div class="pointer-events-none absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_20%_20%,white_0,transparent_35%),radial-gradient(circle_at_80%_0,white_0,transparent_35%)]"></div>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white/90 backdrop-blur-sm rounded-2xl p-5 shadow-md ring-1 ring-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="p-2 rounded-lg bg-blue-50 text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M3 12l6-6 4 4 7-7"/></svg>
                            </span>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Ventas de hoy</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(metrics?.salesToday || 0) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-sm rounded-2xl p-5 shadow-md ring-1 ring-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="p-2 rounded-lg bg-indigo-50 text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </span>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Entregas de hoy</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ metrics?.ordersToday || 0 }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-sm rounded-2xl p-5 shadow-md ring-1 ring-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="p-2 rounded-lg bg-cyan-50 text-cyan-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12 4.5v15M4.5 12h15"/></svg>
                            </span>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Ticket promedio</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(metrics?.avgTicketToday || 0) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="store" class="bg-white/90 backdrop-blur-sm rounded-2xl p-6 my-8 shadow-md ring-1 ring-blue-100">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="font-semibold text-blue-900">¡Compartí tu tienda con el mundo!</p>
                            <p class="text-sm text-blue-700 mt-1">Esta es la URL pública de tu catálogo:</p>
                        </div>
                        <div class="flex-1 md:max-w-2xl">
                            <div class="flex items-stretch gap-2">
                                <input :value="storeUrl" readonly class="flex-1 font-mono text-sm bg-blue-50 px-3 py-2 rounded-md border border-blue-200 text-blue-900" />
                                <button @click="copyUrl" type="button" class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition shadow">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M16.5 6.75V6A4.5 4.5 0 0012 1.5h-3A4.5 4.5 0 004.5 6v.75H6V6A3 3 0 019 3h3a3 3 0 013 3v.75h1.5z"/><path d="M6.75 6.75h10.5A2.25 2.25 0 0119.5 9v9.75A2.25 2.25 0 0117.25 21H6.75A2.25 2.25 0 014.5 18.75V9A2.25 2.25 0 016.75 6.75z"/></svg>
                                    <span v-if="!copied">Copiar</span>
                                    <span v-else>¡Copiado!</span>
                                </button>
                                <a :href="storeUrl" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-white text-blue-700 text-sm rounded-md border border-blue-200 hover:bg-blue-50 transition">
                                    Visitar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white border border-gray-100 rounded-xl p-6 shadow-sm hover:shadow-md transition group">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 rounded-lg bg-blue-50 text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M2.25 3A.75.75 0 013 2.25h6A.75.75 0 019.75 3v6A.75.75 0 019 9.75H3A.75.75 0 012.25 9V3zM14.25 3A.75.75 0 0115 2.25h6a.75.75 0 01.75.75v6a.75.75 0 01-.75.75h-6a.75.75 0 01-.75-.75V3zM2.25 14.25A.75.75 0 013 13.5h6a.75.75 0 01.75.75v6A.75.75 0 019 21H3a.75.75 0 01-.75-.75v-6zM14.25 14.25a.75.75 0 01.75-.75h6a.75.75 0 01.75.75v6a.75.75 0 01-.75.75h-6a.75.75 0 01-.75-.75v-6z"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold">Gestionar Productos</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Creá, editá y eliminá los productos de tu tienda.</p>
                        <Link :href="route('admin.products.index')" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800">
                            Ir a Productos
                            <span aria-hidden>→</span>
                        </Link>
                    </div>

                    <div class="bg-white border border-gray-100 rounded-xl p-6 shadow-sm hover:shadow-md transition group">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M3 4.5A1.5 1.5 0 014.5 3h15A1.5 1.5 0 0121 4.5V6H3V4.5zM3 7.5h18v12A1.5 1.5 0 0119.5 21h-15A1.5 1.5 0 013 19.5v-12z"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold">Gestionar Categorías</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Organizá tus productos en diferentes categorías.</p>
                        <Link :href="route('admin.categories.index')" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                            Ir a Categorías
                            <span aria-hidden>→</span>
                        </Link>
                    </div>
                    
                    <div class="bg-white border border-gray-100 rounded-xl p-6 shadow-sm hover:shadow-md transition group" :class="{ 'opacity-60': !isNegociante }">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 rounded-lg bg-purple-50 text-purple-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z"/><path d="M3.75 20.25a8.25 8.25 0 1116.5 0v.75H3.75v-.75z"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold">Gestionar Usuarios</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Administrá los usuarios y sus roles en el sistema.</p>
                        <template v-if="isNegociante">
                            <Link :href="route('admin.users.index')" class="inline-flex items-center gap-2 text-sm font-semibold text-purple-700 hover:text-purple-800">
                                Ir a Usuarios
                                <span aria-hidden>→</span>
                            </Link>
                        </template>
                        <template v-else>
                            <button type="button" @click="openUpgrade" class="inline-flex items-center gap-2 text-sm font-semibold text-purple-400 hover:text-purple-500">
                                Ir a Usuarios
                                <span aria-hidden>→</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>