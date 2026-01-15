<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted, computed, ref } from 'vue';
import { downloadPDF, sharePDF } from '@/Utils/pdfUtils';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const props = defineProps({
    sale: Object,
    store: Object,
});

const page = usePage();
const hasPhysicalSalesRole = computed(() => {
    const roles = page.props.auth?.roles || [];
    return roles.includes('physical-sales');
});

// Ensure items is always an array
const itemsList = computed(() => {
    if (!props.sale || !props.sale.items) return [];
    return Array.isArray(props.sale.items) ? props.sale.items : Object.values(props.sale.items);
});

// PDF Generation
const isGeneratingPDF = ref(false);

const downloadInvoicePDF = async () => {
    if (!props.sale) return;
    isGeneratingPDF.value = true;
    try {
        const element = document.getElementById('invoice-content-show');
        if (element) {
            await downloadPDF(element, `factura-${props.sale.sale_number}.pdf`);
        }
    } catch (error) {
        console.error(error);
        alert('Error al generar el PDF');
    } finally {
        isGeneratingPDF.value = false;
    }
};

const shareInvoicePDF = async () => {
    if (!props.sale) return;
    isGeneratingPDF.value = true;
    try {
        const element = document.getElementById('invoice-content-show');
        if (element) {
            await sharePDF(element, `factura-${props.sale.sale_number}.pdf`, `Factura #${props.sale.sale_number}`);
        }
    } catch (error) {
        console.error(error);
    } finally {
        isGeneratingPDF.value = false;
    }
};

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
                        @click="downloadInvoicePDF"
                        class="px-3 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm flex items-center gap-1"
                        :disabled="isGeneratingPDF"
                        title="Descargar PDF"
                    >
                        <span v-if="isGeneratingPDF">⏳</span>
                        <span v-else>⬇️</span>
                        <span class="hidden sm:inline">Descargar</span>
                    </button>
                    <button
                        @click="shareInvoicePDF"
                        class="px-3 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm flex items-center gap-1"
                        :disabled="isGeneratingPDF"
                        title="Compartir Factura"
                    >
                        <span v-if="isGeneratingPDF">⏳</span>
                        <span v-else>🔗</span>
                        <span class="hidden sm:inline">Compartir</span>
                    </button>
                    <button
                        @click="printInvoice"
                        class="px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-1"
                        title="Imprimir"
                    >
                        <span>🖨️</span>
                        <span class="hidden sm:inline">Imprimir</span>
                    </button>
                    <button
                        @click="$inertia.visit(route('admin.reports.index', { type: 'physical' }))"
                        class="px-3 sm:px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 flex items-center gap-1"
                        title="Volver"
                    >
                        <span>⬅️</span>
                        <span class="hidden sm:inline">Volver</span>
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
                                    <p class="font-medium">{{ sale.user?.name || 'Administrador' }}</p>
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
                                        <tr v-for="item in itemsList" :key="item.id">
                                            <td class="px-4 py-3">
                                                <p class="font-medium">{{ item.product_name }}</p>
                                                <p v-if="item.variant_options" class="text-sm text-gray-500">
                                                    {{ Object.entries(item.variant_options).map(([k, v]) => `${k}: ${v}`).join(', ') }}
                                                </p>
                                            </td>
                                            <td class="px-4 py-3">{{ item.quantity }}</td>
                                            <td class="px-4 py-3">
                                                <div v-if="item.original_price && item.original_price > item.unit_price">
                                                    <p class="text-sm text-gray-400 line-through">{{ formatCurrency(item.original_price) }}</p>
                                                    <p class="font-medium">{{ formatCurrency(item.unit_price) }}</p>
                                                </div>
                                                <p v-else>{{ formatCurrency(item.unit_price) }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span v-if="item.discount_percent > 0" class="text-sm text-green-600 font-medium">
                                                    -{{ item.discount_percent }}%
                                                </span>
                                                <span v-else class="text-sm text-gray-400">-</span>
                                            </td>
                                            <td class="px-4 py-3 font-medium">{{ formatCurrency(item.subtotal) }}</td>
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
                        @click="downloadInvoicePDF"
                        class="px-3 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm flex items-center gap-1 no-print"
                        :disabled="isGeneratingPDF"
                        title="Descargar PDF"
                    >
                        <span v-if="isGeneratingPDF">⏳</span>
                        <span v-else>⬇️</span>
                        <span class="hidden sm:inline">Descargar</span>
                    </button>
                    <button
                        @click="shareInvoicePDF"
                        class="px-3 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm flex items-center gap-1 no-print"
                        :disabled="isGeneratingPDF"
                        title="Compartir Factura"
                    >
                        <span v-if="isGeneratingPDF">⏳</span>
                        <span v-else>🔗</span>
                        <span class="hidden sm:inline">Compartir</span>
                    </button>
                    <button
                        @click="printInvoice"
                        class="px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 no-print flex items-center gap-1"
                        title="Imprimir"
                    >
                        <span>🖨️</span>
                        <span class="hidden sm:inline">Imprimir</span>
                    </button>
                    <Link
                        :href="route('admin.reports.index', { type: 'physical' })"
                        class="px-3 sm:px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 no-print flex items-center gap-1"
                        title="Volver"
                    >
                        <span>⬅️</span>
                        <span class="hidden sm:inline">Volver</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Nombre de la tienda solo para impresión -->
                <div class="print-store-header">
                    <div class="flex items-center justify-center gap-4 mb-6 pb-4 border-b-2 border-black">
                        <img 
                            v-if="store?.logo_url" 
                            :src="store.logo_url" 
                            alt="Logo" 
                            class="h-20 w-auto object-contain"
                        />
                        <h1 v-if="store?.name" class="text-3xl font-bold">{{ store.name }}</h1>
                    </div>
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
                                                    <p class="text-sm text-gray-400 line-through">{{ formatCurrency(item.original_price) }}</p>
                                                    <p class="font-medium">{{ formatCurrency(item.unit_price) }}</p>
                                                </div>
                                                <p v-else>{{ formatCurrency(item.unit_price) }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span v-if="item.discount_percent > 0" class="text-sm text-green-600 font-medium">
                                                    -{{ item.discount_percent }}%
                                                </span>
                                                <span v-else class="text-sm text-gray-400">-</span>
                                            </td>
                                            <td class="px-4 py-3 font-medium">{{ formatCurrency(item.subtotal) }}</td>
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
                                        <span>{{ formatCurrency(sale.subtotal) }}</span>
                                    </div>
                                    <!-- Descuento manual de la venta -->
                                    <div v-if="sale.discount > 0" class="flex justify-between text-red-600">
                                        <span>Descuento manual:</span>
                                        <span>-{{ formatCurrency(sale.discount) }}</span>
                                    </div>
                                    <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                        <span>Total:</span>
                                        <span>{{ formatCurrency(sale.total) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    
    <!-- Hidden invoice container for PDF generation -->
    <div id="invoice-content-show" class="fixed top-0 left-0 w-[80mm] bg-white z-[-100] opacity-0 pointer-events-none">
        <div class="bg-white text-black p-2 font-mono text-[11px] leading-tight" style="width: 80mm; margin: 0 auto; font-family: 'Courier New', Courier, monospace;">
            <!-- Header -->
            <div class="text-center mb-4">
                 <img 
                    v-if="store?.logo_url" 
                    :src="store.logo_url" 
                    alt="Logo" 
                    class="h-12 w-auto object-contain mx-auto mb-2 grayscale"
                    crossorigin="anonymous"
                />
                <h2 class="font-bold text-base uppercase mb-1">{{ store?.name }}</h2>
                <div class="text-[10px] space-y-0.5" style="font-size: 10px;">
                    <p v-if="store?.nit">NIT: {{ store.nit }}</p>
                    <p v-if="store?.address" class="whitespace-normal">{{ store.address }}</p>
                    <p v-if="store?.phone" class="whitespace-normal">Tel: {{ store.phone?.startsWith('57') ? store.phone.substring(2) : store.phone }}</p>
                    <p v-if="store?.email" class="whitespace-normal break-words">{{ store.email }}</p>
                    <p v-if="store?.custom_domain" class="whitespace-normal break-words">{{ store.custom_domain }}</p>
                </div>
            </div>

            <div class="border-b-2 border-dashed border-black my-2"></div>

            <div v-if="sale">
                <!-- Info Grid -->
                <div class="mb-3 text-[10px] grid grid-cols-2 gap-x-2 gap-y-1">
                    <div class="col-span-2 text-center mb-1">
                        <p class="text-sm font-bold">Venta #{{ sale.sale_number }}</p>
                        <p class="text-[9px] text-gray-500">{{ new Date(sale.created_at).toLocaleString('es-CO') }}</p>
                    </div>
                    
                    <div>
                        <span class="font-bold block text-gray-600">Vendedor:</span>
                        <span>{{ sale.user?.name || $page.props.auth.user.name }}</span>
                    </div>
                    <div class="text-right">
                        <span class="font-bold block text-gray-600">Método de Pago:</span>
                        <span class="capitalize">{{ sale.payment_method }}</span>
                    </div>

                    <div v-if="sale.customer_name" class="col-span-2 mt-1 border-t border-dotted border-gray-300 pt-1">
                        <p><span class="font-bold text-gray-600">Cliente:</span> {{ sale.customer_name }}</p>
                        <p v-if="sale.customer_nit"><span class="font-bold text-gray-600">NIT/CC:</span> {{ sale.customer_nit }}</p>
                    </div>
                </div>

                <div class="border-b border-black my-2"></div>

                <!-- Items -->
                <div class="mb-4">
                     <!-- Simplified Header -->
                     <div class="flex justify-between text-[9px] font-bold mb-2 uppercase text-gray-800">
                        <span>Descripción</span>
                        <span>Total</span>
                    </div>

                    <div v-for="item in itemsList" :key="item.id" class="mb-3 border-b border-gray-200 last:border-0 pb-2">
                        <!-- Top Row: Product Name -->
                        <div class="font-bold text-[11px] leading-tight mb-0.5">
                            {{ item.product_name }}
                        </div>
                        
                        <!-- Variant Info -->
                        <div v-if="item.variant_options" class="text-[9px] text-gray-500 italic mb-1">
                            {{ Object.values(item.variant_options).join(' / ') }}
                        </div>

                        <!-- Price / Calculation Row -->
                        <div class="flex justify-between items-start text-[10px] mt-1">
                             <!-- Left Col: Quantity x Price -->
                            <div class="flex flex-col">
                                <!-- Standard calculation line -->
                                <span class="text-gray-800">{{ item.quantity }} x {{ formatCurrency(item.unit_price) }}</span>
                                
                                <!-- Extended Discount Info -->
                                <div v-if="item.discount_percent > 0 || (item.original_price && item.original_price > item.unit_price)" 
                                     class="flex flex-col mt-0.5"
                                >
                                    <!-- Original Price (Strikethrough) -->
                                    <span style="text-decoration: line-through; color: #9ca3af;" class="text-[9px]">
                                        Precio Normal: {{ formatCurrency(item.original_price || (item.unit_price * 100 / (100 - item.discount_percent))) }}
                                    </span>
                                    
                                    <!-- Discount Tag -->
                                    <span class="text-[9px] font-bold text-gray-800">
                                        Desc: {{ item.discount_percent || Math.round((1 - item.unit_price/item.original_price)*100) }}%
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Right Col: Line Total -->
                            <div class="font-bold text-[11px] mt-0.5">
                                {{ formatCurrency(item.subtotal) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-black my-2 dashed"></div>

                <!-- Totals -->
                <div class="text-right text-[11px] space-y-1">
                     <div v-if="sale.discount > 0" class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>{{ formatCurrency(sale.subtotal) }}</span>
                    </div>
                     <div v-if="sale.discount > 0" class="flex justify-between text-gray-600">
                        <span>Descuento</span>
                        <span>-{{ formatCurrency(sale.discount) }}</span>
                    </div>
                     <div class="flex justify-between text-base font-black pt-1">
                        <span>TOTAL</span>
                        <span>{{ formatCurrency(sale.total) }}</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-6 text-[10px] space-y-1 mb-4">
                    <p class="font-medium">¡Gracias por su compra!</p>
                     <div v-if="sale.notes" class="mt-2 pt-2 border-t border-dotted border-gray-300 text-left">
                        <p class="font-bold text-[9px] text-gray-500">Notas:</p>
                        <p class="italic">{{ sale.notes }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    /* Ocultar todo el contenido normal */
    body * {
        visibility: hidden;
    }

    /* Mostrar solo el contenido de la factura nueva */
    #invoice-content-show, 
    #invoice-content-show * {
        visibility: visible;
    }

    /* Posicionar la factura correctamente */
    #invoice-content-show {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm !important;
        opacity: 1 !important;
        z-index: 9999;
        margin: 0;
        padding: 0;
        background: white !important;
    }
    
    /* Asegurar que no haya márgenes extraños en la página */
    @page {
        margin: 0;
        size: auto; 
    }
}
</style>

