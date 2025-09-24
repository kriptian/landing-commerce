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

const formattedChartData = computed(() => {
    return {
        labels: props.chartData.labels,
        datasets: [
            {
                label: 'Ventas por Día',
                backgroundColor: '#3B82F6',
                borderColor: '#1D4ED8',
                borderWidth: 1,
                borderRadius: 4,
                data: props.chartData.data,
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
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="order in orders.data" :key="order.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 hover:underline">
                                                <Link :href="route('admin.orders.show', order.id)">{{ order.id }}</Link>
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