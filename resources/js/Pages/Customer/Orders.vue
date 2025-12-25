<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    store: Object,
    customer: Object,
    orders: Object,
});

// Estados del pedido con iconos y colores
const orderStatuses = {
    'recibido': {
        label: 'Recibido por la tienda',
        icon: 'check',
        color: 'blue',
        step: 1,
    },
    'en_proceso': {
        label: 'En preparación',
        icon: 'package',
        color: 'yellow',
        step: 2,
    },
    'despachado': {
        label: 'Despachado',
        icon: 'truck',
        color: 'purple',
        step: 3,
    },
    'en_camino': {
        label: 'En camino',
        icon: 'delivery',
        color: 'orange',
        step: 4,
    },
    'entregado': {
        label: 'Entregado',
        icon: 'check-circle',
        color: 'green',
        step: 5,
    },
    'cancelado': {
        label: 'Cancelado',
        icon: 'x-circle',
        color: 'red',
        step: 0,
    },
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

const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(price);
};

const getStatusInfo = (status) => {
    return orderStatuses[status] || orderStatuses['recibido'];
};

const contactSeller = (order) => {
    if (!props.store?.phone) return;
    const phone = props.store.phone.replace(/[^0-9]/g, '');
    const message = encodeURIComponent(`Hola, tengo una consulta sobre mi pedido #${order.sequence_number}`);
    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
};
</script>

<template>
    <Head :title="`Mis Pedidos - ${store.name}`" />

    <div class="min-h-screen" :style="store?.catalog_body_bg_color ? { backgroundColor: store.catalog_body_bg_color } : { backgroundColor: '#F9FAFB' }">
        <!-- Header -->
        <header class="bg-white shadow-sm sticky top-0 z-40">
            <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img v-if="store?.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover ring-2 ring-gray-100">
                    <h1 class="text-lg font-medium text-gray-600">{{ store.name }}</h1>
                </div>
                <div class="flex items-center gap-4">
                    <Link :href="route('catalogo.index', { store: store.slug })" class="text-sm text-gray-600 hover:text-gray-800">
                        Volver al catálogo
                    </Link>
                    <Link :href="route('customer.account', { store: store.slug })" class="text-sm text-gray-600 hover:text-gray-800">
                        Mi Cuenta
                    </Link>
                    <form @submit.prevent="router.post(route('customer.logout', { store: store.slug }))">
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </nav>
        </header>

        <main class="container mx-auto px-6 py-12">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Mis Pedidos</h2>

                <div v-if="orders.data && orders.data.length > 0" class="space-y-6">
                    <div v-for="order in orders.data" :key="order.id" class="bg-white rounded-lg shadow-md overflow-hidden">
                        <!-- Header del pedido -->
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Pedido #{{ order.sequence_number }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ formatDate(order.created_at) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xl font-bold text-gray-900">{{ formatPrice(order.total_price) }}</p>
                                    <button
                                        @click="contactSeller(order)"
                                        class="mt-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        Contactar vendedor
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline de estado -->
                        <div class="px-6 py-6">
                            <div class="relative">
                                <!-- Línea de progreso -->
                                <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                                
                                <!-- Estados -->
                                <div class="space-y-6">
                                    <!-- Estado: Recibido -->
                                    <div class="relative flex items-start gap-4">
                                        <div class="relative z-10 flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                :class="{
                                                    'bg-blue-500 text-white': order.status === 'recibido' || ['en_preparacion', 'en_proceso', 'despachado', 'en_camino', 'entregado'].includes(order.status),
                                                    'bg-gray-200 text-gray-400': order.status === 'cancelado',
                                                }"
                                            >
                                                <svg v-if="order.status === 'cancelado'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 pt-1">
                                            <p class="font-semibold text-gray-900">Recibido por la tienda</p>
                                            <p class="text-sm text-gray-600">Tu pedido ha sido recibido y está siendo procesado</p>
                                        </div>
                                    </div>

                                    <!-- Estado: En proceso -->
                                    <div v-if="['en_preparacion', 'en_proceso', 'despachado', 'en_camino', 'entregado'].includes(order.status)" class="relative flex items-start gap-4">
                                        <div class="relative z-10 flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                :class="{
                                                    'bg-yellow-500 text-white': ['en_preparacion', 'en_proceso'].includes(order.status) || ['despachado', 'en_camino', 'entregado'].includes(order.status),
                                                    'bg-gray-200 text-gray-400': false,
                                                }"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 pt-1">
                                            <p class="font-semibold text-gray-900">En preparación</p>
                                            <p class="text-sm text-gray-600">Estamos preparando tu pedido</p>
                                        </div>
                                    </div>

                                    <!-- Estado: Despachado -->
                                    <div v-if="['despachado', 'en_camino', 'entregado'].includes(order.status)" class="relative flex items-start gap-4">
                                        <div class="relative z-10 flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                :class="{
                                                    'bg-purple-500 text-white': order.status === 'despachado' || ['en_camino', 'entregado'].includes(order.status),
                                                    'bg-gray-200 text-gray-400': false,
                                                }"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 pt-1">
                                            <p class="font-semibold text-gray-900">Despachado</p>
                                            <p class="text-sm text-gray-600">Tu pedido ha sido enviado</p>
                                        </div>
                                    </div>

                                    <!-- Estado: En camino -->
                                    <div v-if="['en_camino', 'entregado'].includes(order.status)" class="relative flex items-start gap-4">
                                        <div class="relative z-10 flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center bg-orange-500 text-white">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 pt-1">
                                            <p class="font-semibold text-gray-900">En camino</p>
                                            <p class="text-sm text-gray-600">Tu pedido está en camino a tu dirección</p>
                                        </div>
                                    </div>

                                    <!-- Estado: Entregado -->
                                    <div v-if="order.status === 'entregado'" class="relative flex items-start gap-4">
                                        <div class="relative z-10 flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center bg-green-500 text-white">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 pt-1">
                                            <p class="font-semibold text-gray-900">Entregado</p>
                                            <p class="text-sm text-gray-600">Tu pedido ha sido entregado</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Productos del pedido -->
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <h4 class="font-semibold text-gray-900 mb-3">Productos</h4>
                            <div class="space-y-3">
                                <div v-for="item in order.items" :key="item.id" class="flex items-center gap-4">
                                    <img v-if="item.product?.main_image_url" :src="item.product.main_image_url" class="w-16 h-16 object-cover rounded-lg">
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">{{ item.product_name }}</p>
                                        <div v-if="item.variant_options" class="text-sm text-gray-600 mt-1">
                                            <span v-for="(value, key) in item.variant_options" :key="key" class="mr-2">
                                                <strong>{{ key }}:</strong> {{ value }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600">Cantidad: {{ item.quantity }}</p>
                                    </div>
                                    <p class="font-semibold text-gray-900">
                                        {{ formatPrice(item.unit_price * item.quantity) }}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Descuento si aplica -->
                            <div v-if="order.discount_amount > 0" class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex justify-between items-center text-green-600">
                                    <span class="font-medium">Descuento (Cupón{{ order.coupon ? ': ' + order.coupon.code : '' }})</span>
                                    <span class="font-bold">-{{ formatPrice(order.discount_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Dirección de envío -->
                        <div v-if="order.address" class="px-6 py-4 border-t border-gray-200">
                            <h4 class="font-semibold text-gray-900 mb-2">Dirección de Envío</h4>
                            <p class="text-sm text-gray-600">
                                {{ order.address.address_line_1 }}{{ order.address.address_line_2 ? ', ' + order.address.address_line_2 : '' }}, 
                                {{ order.address.city }}{{ order.address.state ? ', ' + order.address.state : '' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white rounded-lg shadow-md p-12 text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <p class="text-gray-500 text-lg">Aún no has realizado ningún pedido</p>
                    <Link :href="route('catalogo.index', { store: store.slug })" class="mt-4 inline-block text-blue-600 hover:text-blue-700 font-medium">
                        Ver catálogo
                    </Link>
                </div>

                <!-- Paginación -->
                <div v-if="orders.links && orders.links.length > 3" class="mt-6 flex justify-center">
                    <nav class="flex gap-2">
                        <Link 
                            v-for="link in orders.links" 
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="px-3 py-2 rounded-md text-sm"
                            :class="{
                                'bg-blue-600 text-white': link.active,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200': !link.active && link.url,
                                'bg-gray-50 text-gray-400 cursor-not-allowed': !link.url,
                            }"
                        />
                    </nav>
                </div>
            </div>
        </main>
    </div>
</template>

