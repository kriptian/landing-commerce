<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    orders: Object,
    filters: Object,
});

// Buscador reactivo con debounce
const q = ref(props.filters?.q || '');
const start = ref(props.filters?.start || '');
const end = ref(props.filters?.end || '');

let t;
const pushFilters = () => {
    clearTimeout(t);
    t = setTimeout(() => {
        router.get(route('admin.orders.index'), {
            status: props.filters?.status || undefined,
            q: q.value || undefined,
            start: start.value || undefined,
            end: end.value || undefined,
        }, { preserveState: true, replace: true, preserveScroll: true });
    }, 350);
};

watch(q, pushFilters);
watch([start, end], pushFilters);

// Formateo fecha
const formatDate = (datetime) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(datetime).toLocaleDateString('es-CO', options);
};

// Scroll lateral + degradados + header sticky + filas cebra
const scrollBoxRef = ref(null);
const showLeftFade = ref(false);
const showRightFade = ref(false);
const updateFades = () => {
    const el = scrollBoxRef.value;
    if (!el) return;
    const maxScrollLeft = el.scrollWidth - el.clientWidth;
    const left = el.scrollLeft || 0;
    showLeftFade.value = left > 0;
    showRightFade.value = left < (maxScrollLeft - 1);
};
onMounted(() => {
    nextTick(() => updateFades());
    scrollBoxRef.value?.addEventListener('scroll', updateFades, { passive: true });
    window.addEventListener('resize', updateFades);
});
onBeforeUnmount(() => {
    scrollBoxRef.value?.removeEventListener('scroll', updateFades);
    window.removeEventListener('resize', updateFades);
});
</script>

<template>
    <Head title="Gestionar Órdenes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestionar Órdenes</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        
                        <div class="mb-4">
                            <div class="flex flex-wrap gap-2">
                                <Link 
                                    :href="route('admin.orders.index')" 
                                    class="px-3 py-2 text-sm font-medium rounded-md"
                                    :class="{
                                        'bg-blue-600 text-white': !filters.status,
                                        'bg-gray-200 text-gray-700 hover:bg-gray-300': filters.status
                                    }"
                                >
                                    Todos
                                </Link>
                                <Link 
                                    :href="route('admin.orders.index', { status: 'recibido' })"
                                    class="px-3 py-2 text-sm font-medium rounded-md"
                                    :class="{
                                        'bg-yellow-500 text-white': filters.status === 'recibido',
                                        'bg-gray-200 text-gray-700 hover:bg-gray-300': filters.status !== 'recibido'
                                    }"
                                >
                                    Recibidos
                                </Link>
                                <Link 
                                    :href="route('admin.orders.index', { status: 'en_preparacion' })"
                                    class="px-3 py-2 text-sm font-medium rounded-md"
                                    :class="{
                                        'bg-blue-500 text-white': filters.status === 'en_preparacion',
                                        'bg-gray-200 text-gray-700 hover:bg-gray-300': filters.status !== 'en_preparacion'
                                    }"
                                >
                                    En Preparación
                                </Link>
                                <Link 
                                    :href="route('admin.orders.index', { status: 'despachado' })"
                                    class="px-3 py-2 text-sm font-medium rounded-md"
                                    :class="{
                                        'bg-purple-500 text-white': filters.status === 'despachado',
                                        'bg-gray-200 text-gray-700 hover:bg-gray-300': filters.status !== 'despachado'
                                    }"
                                >
                                    Despachados
                                </Link>
                                <Link 
                                    :href="route('admin.orders.index', { status: 'entregado' })"
                                    class="px-3 py-2 text-sm font-medium rounded-md"
                                    :class="{
                                        'bg-green-500 text-white': filters.status === 'entregado',
                                        'bg-gray-200 text-gray-700 hover:bg-gray-300': filters.status !== 'entregado'
                                    }"
                                >
                                    Entregados
                                </Link>
                                <Link 
                                    :href="route('admin.orders.index', { status: 'cancelado' })"
                                    class="px-3 py-2 text-sm font-medium rounded-md"
                                    :class="{
                                        'bg-red-500 text-white': filters.status === 'cancelado',
                                        'bg-gray-200 text-gray-700 hover:bg-gray-300': filters.status !== 'cancelado'
                                    }"
                                >
                                    Cancelados
                                </Link>
                            </div>
                        </div>

                        <!-- Barra de búsqueda y rango de fechas (reactiva + responsive) -->
                        <div class="mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-center">
                                <div class="md:col-span-2">
                                    <div class="relative">
                                        <input v-model="q" type="text" placeholder="Buscar por nombre o teléfono" class="w-full pl-10 pr-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                        <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" viewBox="0 0 24 24" fill="currentColor"><path d="M10 2a8 8 0 105.293 14.707l3.5 3.5a1 1 0 001.414-1.414l-3.5-3.5A8 8 0 0010 2zm0 2a6 6 0 110 12A6 6 0 0110 4z"/></svg>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 md:col-span-2">
                                    <input v-model="start" type="date" class="w-full border rounded-md text-sm py-2 px-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                    <span class="text-gray-500 text-sm">a</span>
                                    <input v-model="end" type="date" class="w-full border rounded-md text-sm py-2 px-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click.prevent="() => { q=''; start=''; end=''; pushFilters(); }" class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-3 rounded-md w-full md:w-auto justify-center">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>


                        <div ref="scrollBoxRef" class="relative overflow-x-auto">
                            <div v-show="showLeftFade" class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-white to-transparent"></div>
                            <div v-show="showRightFade" class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-white to-transparent"></div>
                            <table class="min-w-[920px] sm:min-w-full divide-y divide-gray-200">
                                <thead class="sticky top-0 z-10 bg-gray-50">
                                    <tr>
                                        <th scope="col" class="sticky left-0 z-20 bg-gray-50 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Orden #</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Cliente</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Teléfono</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Fecha</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Items</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Total</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Estado</th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Ver</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(order, idx) in orders.data" :key="order.id" class="odd:bg-white even:bg-gray-100">
                                        <td class="sticky left-0 z-10 px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-r" :class="idx % 2 === 1 ? 'bg-gray-100' : 'bg-white'">{{ order.sequence_number || order.sequence_number === 0 ? order.sequence_number : order.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">{{ order.customer_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ order.customer_phone }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(order.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.items_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$ {{ Number(order.total_price).toLocaleString('es-CO') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="{
                                                      'bg-yellow-100 text-yellow-800': order.status === 'recibido',
                                                      'bg-blue-100 text-blue-800': order.status === 'en_preparacion',
                                                      'bg-purple-100 text-purple-800': order.status === 'despachado',
                                                      'bg-green-100 text-green-800': order.status === 'entregado',
                                                      'bg-red-100 text-red-800': order.status === 'cancelado',
                                                  }">
                                                {{ order.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('admin.orders.show', order.id)" class="text-indigo-600 hover:text-indigo-900">Ver</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="orders.data.length === 0">
                                        <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            No se encontraron órdenes con este filtro.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <Pagination class="mt-6" :links="orders.links" />

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>