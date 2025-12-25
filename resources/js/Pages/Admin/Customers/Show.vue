<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    customer: Object,
    stats: Object,
    recentOrders: Array,
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(price);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusColor = (status) => {
    const colors = {
        'recibido': 'bg-yellow-100 text-yellow-800',
        'en_proceso': 'bg-blue-100 text-blue-800',
        'entregado': 'bg-green-100 text-green-800',
        'cancelado': 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head :title="`Cliente: ${customer.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cliente: {{ customer.name }}</h2>
                <Link :href="route('admin.customers.index')" class="text-sm text-gray-600 hover:text-gray-800">
                    ← Volver a clientes
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Información del cliente -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Información del Cliente</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Nombre</p>
                                <p class="text-base font-medium text-gray-900">{{ customer.name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="text-base font-medium text-gray-900">{{ customer.email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Teléfono</p>
                                <p class="text-base font-medium text-gray-900">{{ customer.phone || 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Fecha de Registro</p>
                                <p class="text-base font-medium text-gray-900">{{ formatDate(customer.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Direcciones -->
                <div v-if="customer.addresses && customer.addresses.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Direcciones</h3>
                        <div class="space-y-3">
                            <div v-for="address in customer.addresses" :key="address.id" class="border rounded-lg p-4" :class="{ 'border-blue-500 bg-blue-50': address.is_default }">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="font-semibold text-gray-900">{{ address.label }}</span>
                                            <span v-if="address.is_default" class="text-xs bg-blue-500 text-white px-2 py-1 rounded">Predeterminada</span>
                                        </div>
                                        <p class="text-sm text-gray-600">{{ address.address_line_1 }}</p>
                                        <p v-if="address.address_line_2" class="text-sm text-gray-600">{{ address.address_line_2 }}</p>
                                        <p class="text-sm text-gray-600">{{ address.city }}{{ address.state ? ', ' + address.state : '' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm text-gray-600 mb-1">Total de Pedidos</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.total_orders }}</p>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm text-gray-600 mb-1">Total Gastado</p>
                        <p class="text-3xl font-bold text-gray-900">{{ formatPrice(stats.total_spent) }}</p>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm text-gray-600 mb-1">Ticket Promedio</p>
                        <p class="text-3xl font-bold text-gray-900">{{ formatPrice(stats.average_order_value || 0) }}</p>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p class="text-sm text-gray-600 mb-1">Último Pedido</p>
                        <p class="text-sm font-medium text-gray-900">{{ stats.last_order_date ? formatDate(stats.last_order_date) : 'N/A' }}</p>
                    </div>
                </div>

                <!-- Pedidos recientes -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Pedidos Recientes</h3>
                        <div v-if="recentOrders && recentOrders.length > 0" class="space-y-4">
                            <div v-for="order in recentOrders" :key="order.id" class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">Pedido #{{ order.sequence_number }}</p>
                                        <p class="text-sm text-gray-600">{{ formatDate(order.created_at) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-gray-900">{{ formatPrice(order.total_price) }}</p>
                                        <span class="inline-block px-2 py-1 rounded text-xs font-medium mt-1" :class="getStatusColor(order.status)">
                                            {{ order.status }}
                                        </span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between text-sm">
                                        <span class="text-gray-700">{{ item.product_name }} x{{ item.quantity }}</span>
                                        <span class="text-gray-900 font-medium">{{ formatPrice(item.unit_price * item.quantity) }}</span>
                                    </div>
                                </div>
                                <div v-if="order.address" class="mt-3 pt-3 border-t text-sm text-gray-600">
                                    <p><strong>Envío a:</strong> {{ order.address.address_line_1 }}, {{ order.address.city }}</p>
                                </div>
                                <div v-if="order.coupon" class="mt-2 text-sm text-green-600">
                                    <p><strong>Cupón aplicado:</strong> {{ order.coupon.code }} (Descuento: {{ formatPrice(order.discount_amount || 0) }})</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 text-center py-8">
                            Este cliente aún no ha realizado ningún pedido
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

