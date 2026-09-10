<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
    customers: Object,
    stats: Object,
    filters: Object,
});

const activeTab = computed(() => {
    return route().current('admin.coupons.*') ? 'coupons' : 'customers';
});

const searchQuery = ref(props.filters?.search || '');

const search = () => {
    router.get(route('admin.customers.index'), {
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(price);
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <AdminPage>
            <PageHeader eyebrow="Relacion comercial" title="Clientes" description="Conoce quienes compran, cuanto han invertido y consulta rapidamente su historial." />
                <!-- Tabs -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <Link
                            :href="route('admin.customers.index')"
                            :class="[
                                activeTab === 'customers'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Listado de Clientes
                        </Link>
                        <Link
                            :href="route('admin.coupons.index')"
                            :class="[
                                activeTab === 'coupons'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Cupones de Descuento
                        </Link>
                    </nav>
                </div>

                <!-- Contenido de Clientes -->
                <div v-if="activeTab === 'customers'">
                <!-- Estadísticas -->
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="ui-card p-5">
                        <p class="text-sm font-semibold text-slate-500">Clientes registrados</p>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.total_customers }}</p>
                    </div>
                    <div class="ui-card p-5">
                        <p class="text-sm font-semibold text-slate-500">Pedidos realizados</p>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.total_orders }}</p>
                    </div>
                    <div class="ui-card p-5">
                        <p class="text-sm font-semibold text-slate-500">Ingresos acumulados</p>
                        <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ formatPrice(stats.total_revenue) }}</p>
                    </div>
                </div>

                <!-- Búsqueda -->
                <div class="ui-card mb-6 p-4 sm:p-5">
                        <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="search">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por nombre, email o teléfono..."
                                class="ui-input mt-0 flex-1"
                            />
                            <button
                                type="submit"
                                class="ui-primary-button"
                            >
                                Buscar
                            </button>
                        </form>
                </div>

                <div class="mb-4 space-y-3 md:hidden"><article v-for="customer in customers.data" :key="customer.id" class="ui-card p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate font-bold text-slate-900">{{ customer.name }}</h2><p class="truncate text-sm text-slate-500">{{ customer.email }}</p></div><p class="font-extrabold text-slate-900">{{ formatPrice(customer.orders?.reduce((sum, order) => sum + parseFloat(order.total_price || 0), 0) || 0) }}</p></div><div class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 p-3 text-sm"><span>{{ customer.orders_count }} pedidos</span><span>{{ customer.phone || 'Sin telefono' }}</span></div><Link :href="route('admin.customers.show', customer.id)" class="ui-secondary-button mt-4 w-full">Ver historial</Link></article><div v-if="!customers.data.length" class="ui-card p-8 text-center text-sm text-slate-500">No se encontraron clientes.</div></div>

                <!-- Lista de clientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contacto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pedidos</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Gastado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ customer.name }}</div>
                                        <div class="text-sm text-gray-500">{{ customer.email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ customer.phone || 'N/A' }}</div>
                                        <div v-if="customer.default_address" class="text-xs text-gray-500">
                                            {{ customer.default_address.city }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ customer.orders_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatPrice(customer.orders?.reduce((sum, order) => sum + parseFloat(order.total_price || 0), 0) || 0) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Link
                                            :href="route('admin.customers.show', customer.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Ver detalles
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No se encontraron clientes
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        <Pagination :links="customers.links" />
                    </div>
                </div>
                </div>
        </AdminPage>
    </AuthenticatedLayout>
</template>

