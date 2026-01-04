<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';

const props = defineProps({
    sale: Object,
    store: Object,
});

const page = usePage();
const hasPhysicalSalesRole = computed(() => {
    const roles = page.props.auth?.roles || [];
    return roles.includes('physical-sales');
});

// Verificar si se debe imprimir
const shouldPrint = () => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('print') === 'true';
};

// Imprimir factura
const printInvoice = () => {
    window.print();
};

onMounted(() => {
    if (shouldPrint()) {
        // Esperar un momento para que el contenido se renderice
        setTimeout(() => {
            window.print();
        }, 500);
    }
});
</script>

<template>
    <Head title="Detalle de Venta" />

    <!-- Vista para usuarios con rol physical-sales: sin navegación -->
    <div v-if="hasPhysicalSalesRole" class="min-h-screen bg-gray-50">
        <!-- Header simple para usuarios physical-sales -->
        <div class="bg-white border-b border-gray-200 px-6 py-4 no-print">
            <div class="flex items-center justify-between max-w-4xl mx-auto">
                <h2 class="font-semibold text-xl text-gray-800">
                    Venta #{{ sale.sale_number }}
                </h2>
                <div class="flex gap-2">
                    <button
                        @click="printInvoice"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                        🖨️ Imprimir
                    </button>
                    <button
                        @click="$inertia.visit(route('admin.reports.index', { type: 'physical' }))"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
                    >
                        Volver
                    </button>
                </div>
            </div>
        </div>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Nombre de la tienda solo para impresión -->
                <div class="print-store-header">
                    <h1 v-if="store?.name" class="text-center text-2xl font-bold mb-6 pb-4 border-b-2 border-black">{{ store.name }}</h1>
                </div>

                <!-- Marca de agua -->
                <div v-if="store?.name" class="watermark-container">
                    <div class="watermark-text">{{ store.name }}</div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg relative z-10">
                    <div class="p-6">
                        <!-- Información de la venta -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Información de la Venta</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Número de Venta</p>
                                    <p class="font-medium">#{{ sale.sale_number }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Fecha</p>
                                    <p class="font-medium">{{ new Date(sale.created_at).toLocaleString() }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Vendedor</p>
                                    <p class="font-medium">{{ sale.user?.name || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Método de Pago</p>
                                    <p class="font-medium capitalize">{{ sale.payment_method }}</p>
                                </div>
                            </div>
                            <div v-if="sale.notes" class="mt-4">
                                <p class="text-sm text-gray-500">Notas</p>
                                <p class="font-medium">{{ sale.notes }}</p>
                            </div>
                        </div>

                        <!-- Items de la venta -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Productos Vendidos</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio Unit.</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descuento</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in sale.items" :key="item.id">
                                            <td class="px-4 py-3">
                                                <p class="font-medium">{{ item.product_name }}</p>
                                                <p v-if="item.variant_options" class="text-sm text-gray-500">
                                                    {{ Object.entries(item.variant_options).map(([k, v]) => `${k}: ${v}`).join(', ') }}
                                                </p>
                                            </td>
                                            <td class="px-4 py-3">{{ item.quantity }}</td>
                                            <td class="px-4 py-3">
                                                <div v-if="item.original_price && item.original_price > item.unit_price">
                                                    <p class="text-sm text-gray-400 line-through">${{ parseFloat(item.original_price).toFixed(2) }}</p>
                                                    <p class="font-medium">${{ parseFloat(item.unit_price).toFixed(2) }}</p>
                                                </div>
                                                <p v-else>${{ parseFloat(item.unit_price).toFixed(2) }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span v-if="item.discount_percent > 0" class="text-sm text-green-600 font-medium">
                                                    -{{ item.discount_percent }}%
                                                </span>
                                                <span v-else class="text-sm text-gray-400">-</span>
                                            </td>
                                            <td class="px-4 py-3 font-medium">${{ parseFloat(item.subtotal).toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Totales -->
                        <div class="border-t pt-4">
                            <div class="flex justify-end">
                                <div class="w-64 space-y-2">
                                    <div class="flex justify-between">
                                        <span>Subtotal:</span>
                                        <span>${{ parseFloat(sale.subtotal).toFixed(2) }}</span>
                                    </div>
                                    <!-- Descuento manual de la venta -->
                                    <div v-if="sale.discount > 0" class="flex justify-between text-red-600">
                                        <span>Descuento manual:</span>
                                        <span>-${{ parseFloat(sale.discount).toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                        <span>Total:</span>
                                        <span>${{ parseFloat(sale.total).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vista para usuarios normales: con AuthenticatedLayout -->
    <AuthenticatedLayout v-else>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Venta #{{ sale.sale_number }}
                </h2>
                <div class="flex gap-2">
                    <button
                        @click="printInvoice"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 no-print"
                    >
                        🖨️ Imprimir
                    </button>
                    <Link
                        :href="route('admin.reports.index', { type: 'physical' })"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 no-print"
                    >
                        Volver
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Nombre de la tienda solo para impresión -->
                <div class="print-store-header">
                    <h1 v-if="store?.name" class="text-center text-2xl font-bold mb-6 pb-4 border-b-2 border-black">{{ store.name }}</h1>
                </div>

                <!-- Marca de agua -->
                <div v-if="store?.name" class="watermark-container">
                    <div class="watermark-text">{{ store.name }}</div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg relative z-10">
                    <div class="p-6">
                        <!-- Información de la venta -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Información de la Venta</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Número de Venta</p>
                                    <p class="font-medium">#{{ sale.sale_number }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Fecha</p>
                                    <p class="font-medium">{{ new Date(sale.created_at).toLocaleString() }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Vendedor</p>
                                    <p class="font-medium">{{ sale.user?.name || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Método de Pago</p>
                                    <p class="font-medium capitalize">{{ sale.payment_method }}</p>
                                </div>
                            </div>
                            <div v-if="sale.notes" class="mt-4">
                                <p class="text-sm text-gray-500">Notas</p>
                                <p class="font-medium">{{ sale.notes }}</p>
                            </div>
                        </div>

                        <!-- Items de la venta -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">Productos Vendidos</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio Unit.</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descuento</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in sale.items" :key="item.id">
                                            <td class="px-4 py-3">
                                                <p class="font-medium">{{ item.product_name }}</p>
                                                <p v-if="item.variant_options" class="text-sm text-gray-500">
                                                    {{ Object.entries(item.variant_options).map(([k, v]) => `${k}: ${v}`).join(', ') }}
                                                </p>
                                            </td>
                                            <td class="px-4 py-3">{{ item.quantity }}</td>
                                            <td class="px-4 py-3">
                                                <div v-if="item.original_price && item.original_price > item.unit_price">
                                                    <p class="text-sm text-gray-400 line-through">${{ parseFloat(item.original_price).toFixed(2) }}</p>
                                                    <p class="font-medium">${{ parseFloat(item.unit_price).toFixed(2) }}</p>
                                                </div>
                                                <p v-else>${{ parseFloat(item.unit_price).toFixed(2) }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span v-if="item.discount_percent > 0" class="text-sm text-green-600 font-medium">
                                                    -{{ item.discount_percent }}%
                                                </span>
                                                <span v-else class="text-sm text-gray-400">-</span>
                                            </td>
                                            <td class="px-4 py-3 font-medium">${{ parseFloat(item.subtotal).toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Totales -->
                        <div class="border-t pt-4">
                            <div class="flex justify-end">
                                <div class="w-64 space-y-2">
                                    <div class="flex justify-between">
                                        <span>Subtotal:</span>
                                        <span>${{ parseFloat(sale.subtotal).toFixed(2) }}</span>
                                    </div>
                                    <!-- Descuento manual de la venta -->
                                    <div v-if="sale.discount > 0" class="flex justify-between text-red-600">
                                        <span>Descuento manual:</span>
                                        <span>-${{ parseFloat(sale.discount).toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                        <span>Total:</span>
                                        <span>${{ parseFloat(sale.total).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    /* Ocultar elementos de navegación y pestañas */
    .no-print,
    nav,
    header,
    aside,
    footer {
        display: none !important;
    }
    
    /* Ocultar pestañas/navegación específicamente */
    .tabs,
    [role="tablist"],
    [role="tab"],
    button[role="tab"],
    nav[role="navigation"],
    .nav-link,
    .navigation {
        display: none !important;
    }
    
    /* Ocultar header de la página pero mostrar el nombre de la tienda */
    .print-store-header {
        display: block !important;
    }
    
    /* Estilos básicos para impresión */
    body {
        background: white !important;
    }
    
    .bg-white {
        background: white !important;
    }
    
    .shadow-sm {
        box-shadow: none !important;
    }
}

/* Estilos de marca de agua */
.watermark-container {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    opacity: 0.12; /* Ajustado para mejor visibilidad */
    z-index: 50; /* Por encima del contenido */
    pointer-events: none;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.watermark-text {
    font-size: 8vw; /* Tamaño responsivo relativo al ancho */
    font-weight: 900;
    color: #000;
    text-transform: uppercase;
    white-space: nowrap;
}

@media print {
    .watermark-container {
        display: flex !important;
        opacity: 0.08 !important; /* Un poco más sutil en impresión */
    }
    
    .watermark-text {
        font-size: 6rem; /* Tamaño fijo para impresión A4/Carta */
        color: #000 !important;
        -webkit-text-stroke: 1px #ccc; /* Borde opcional para mejor definición */
    }
}
</style>

