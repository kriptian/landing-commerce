<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import BarChart from '@/Components/BarChart.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    orders: Object,
    stats: Object,
    filters: Object,
    chartData: Object,
});

const activeTab = ref('chart');

const filterForm = useForm({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

const applyFilters = () => {
    filterForm.get(route('admin.reports.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Rangos rápidos de fechas
const formatYMD = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const getToday = () => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};

const setQuickRange = (range) => {
    const today = getToday();
    let start, end;
    switch (range) {
        case 'today':
            start = today;
            end = today;
            break;
        case 'last7':
            start = new Date(today);
            start.setDate(start.getDate() - 6);
            end = today;
            break;
        case 'last30':
            start = new Date(today);
            start.setDate(start.getDate() - 29);
            end = today;
            break;
        case 'thisMonth':
            start = new Date(today.getFullYear(), today.getMonth(), 1);
            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'lastMonth':
            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            end = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        default:
            return;
    }
    filterForm.start_date = formatYMD(start);
    filterForm.end_date = formatYMD(end);
    applyFilters();
};

const isRangeSelected = (range) => {
    const sd = filterForm.start_date;
    const ed = filterForm.end_date;
    if (!sd || !ed) return false;
    const today = getToday();
    const ymd = (d) => formatYMD(d);
    const equals = (a, b) => a === b;
    switch (range) {
        case 'today':
            return equals(sd, ymd(today)) && equals(ed, ymd(today));
        case 'last7': {
            const start = new Date(today);
            start.setDate(start.getDate() - 6);
            return equals(sd, ymd(start)) && equals(ed, ymd(today));
        }
        case 'last30': {
            const start = new Date(today);
            start.setDate(start.getDate() - 29);
            return equals(sd, ymd(start)) && equals(ed, ymd(today));
        }
        case 'thisMonth': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1);
            const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            return equals(sd, ymd(start)) && equals(ed, ymd(end));
        }
        case 'lastMonth': {
            const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const end = new Date(today.getFullYear(), today.getMonth(), 0);
            return equals(sd, ymd(start)) && equals(ed, ymd(end));
        }
        default:
            return false;
    }
};

const formattedChartData = computed(() => {
    return {
        labels: props.chartData.labels,
        datasets: [
            {
                label: 'Entregadas',
                backgroundColor: '#10B981',
                borderColor: '#059669',
                borderWidth: 1,
                borderRadius: 4,
                data: props.chartData.delivered,
                barPercentage: 0.6,
                categoryPercentage: 0.6,
            },
            {
                label: 'Canceladas',
                backgroundColor: '#EF4444',
                borderColor: '#DC2626',
                borderWidth: 1,
                borderRadius: 4,
                data: props.chartData.cancelled,
                barPercentage: 0.6,
                categoryPercentage: 0.6,
            },
        ],
    };
});

const formatDate = (datetime) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(datetime).toLocaleDateString('es-CO', options);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};
</script>

<template>
    <Head title="Reportes de Ventas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reportes de Ventas</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Ventas Totales (en el período)</h3>
                            <p class="mt-1 text-3xl font-semibold text-gray-900">{{ formatCurrency(stats.totalSales) }}</p>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Órdenes Totales (en el período)</h3>
                            <p class="mt-1 text-3xl font-semibold text-gray-900">{{ stats.totalOrders }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <form @submit.prevent="applyFilters">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700">Desde</label>
                                    <input type="date" id="start_date" v-model="filterForm.start_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                </div>
                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700">Hasta</label>
                                    <input type="date" id="end_date" v-model="filterForm.end_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                </div>
                                <div class="flex space-x-2">
                                    <button type="submit" :disabled="filterForm.processing" class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50">
                                        Filtrar
                                    </button>
                                    <Link :href="route('admin.reports.index')" class="w-full bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded hover:bg-gray-400 text-center">
                                        Limpiar
                                    </Link>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="text-sm text-gray-500 mr-2 self-center">Rangos rápidos:</span>
                                <button type="button" @click="setQuickRange('today')" :class="['px-3 py-1 rounded-full text-sm border', isRangeSelected('today') ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50']">Hoy</button>
                                <button type="button" @click="setQuickRange('last7')" :class="['px-3 py-1 rounded-full text-sm border', isRangeSelected('last7') ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50']">Últimos 7 días</button>
                                <button type="button" @click="setQuickRange('last30')" :class="['px-3 py-1 rounded-full text-sm border', isRangeSelected('last30') ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50']">Últimos 30 días</button>
                                <button type="button" @click="setQuickRange('thisMonth')" :class="['px-3 py-1 rounded-full text-sm border', isRangeSelected('thisMonth') ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50']">Este mes</button>
                                <button type="button" @click="setQuickRange('lastMonth')" :class="['px-3 py-1 rounded-full text-sm border', isRangeSelected('lastMonth') ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50']">Mes pasado</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mb-4 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button @click="activeTab = 'chart'" :class="[activeTab === 'chart' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                            Gráfico
                        </button>
                        <button @click="activeTab = 'table'" :class="[activeTab === 'table' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                            Tabla de Órdenes
                        </button>
                    </nav>
                </div>

                <div v-if="activeTab === 'chart'">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Ventas en el Período Seleccionado</h3>
                            <BarChart :chart-data="formattedChartData" />
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'table'">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Historial de Órdenes</h3>
                                <a :href="route('admin.reports.export', { start_date: filters.start_date, end_date: filters.end_date })"
                                   v-if="orders.data.length > 0"
                                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Exportar a Excel
                                </a>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orden #</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Productos</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidades</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">P. Unitario</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="order in orders.data" :key="order.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 hover:underline">
                                                <Link :href="route('admin.orders.show', order.id)">{{ order.sequence_number || order.sequence_number === 0 ? order.sequence_number : order.id }}</Link>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.customer_name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(order.created_at) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="{
                                                          'bg-green-100 text-green-800': order.status === 'entregado',
                                                          'bg-yellow-100 text-yellow-800': order.status === 'recibido',
                                                          'bg-blue-100 text-blue-800': order.status === 'en_preparacion',
                                                          'bg-purple-100 text-purple-800': order.status === 'despachado',
                                                          'bg-red-100 text-red-800': order.status === 'cancelado',
                                                      }">
                                                    {{ order.status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatCurrency(order.total_price) }}</td>
                                            <td class="px-6 py-4 whitespace-pre-line text-sm text-gray-600 max-w-xl"> 
                                                <template v-for="item in order.items" :key="item.id">
                                                    <div>
                                                        <span class="font-medium">{{ item.product_name || item.product?.name }}</span>
                                                        <span v-if="item.variant_options || item.variant?.options">
                                                            ({{ Object.entries(item.variant_options || item.variant?.options || {}).map(([k,v])=>`${k}: ${v}`).join(', ') }})
                                                        </span>
                                                    </div>
                                                </template>
                                            </td>
                                            <td class="px-6 py-4 whitespace-pre-line text-sm text-gray-600"> 
                                                <div v-for="item in order.items" :key="'q'+item.id">{{ item.quantity }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-pre-line text-sm text-gray-600"> 
                                                <div v-for="item in order.items" :key="'p'+item.id">{{ formatCurrency(item.unit_price) }}</div>
                                            </td>
                                        </tr>
                                        <tr v-if="orders.data.length === 0">
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                                No hay órdenes para mostrar en este período.
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
        </div>
    </AuthenticatedLayout>
</template>