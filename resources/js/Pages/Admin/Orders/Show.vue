<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import { downloadPDF, sharePDF } from '@/Utils/pdfUtils';

const showInfo = ref(false);
const infoTitle = ref('');
const infoMessage = ref('');
const infoType = ref('success'); // 'success' | 'error'

const props = defineProps({
    order: Object,
    store: Object,
});

// Ensure items is always an array
const itemsList = computed(() => {
    if (!props.order || !props.order.items) return [];
    return Array.isArray(props.order.items) ? props.order.items : Object.values(props.order.items);
});

// --- Helper Functions ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

// --- PDF Generation Logic ---
const isGeneratingPDF = ref(false);

const downloadInvoicePDF = async () => {
    isGeneratingPDF.value = true;
    try {
        const element = document.getElementById('invoice-content-digital');
        if (element) {
            await downloadPDF(element, `factura-${props.order.sequence_number || props.order.id}.pdf`);
        }
    } catch (error) {
        console.error(error);
        alert('Error al generar el PDF');
    } finally {
        isGeneratingPDF.value = false;
    }
};

const shareInvoicePDF = async () => {
    isGeneratingPDF.value = true;
    try {
        const element = document.getElementById('invoice-content-digital');
        if (element) {
            await sharePDF(element, `factura-${props.order.sequence_number || props.order.id}.pdf`, `Factura #${props.order.sequence_number || props.order.id}`);
        }
    } catch (error) {
        console.error(error);
    } finally {
        isGeneratingPDF.value = false;
    }
};

const printInvoice = () => {
    window.print();
};

// --- Lógica de Formateo (sigue igual) ---
const formatDate = (datetime) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(datetime).toLocaleDateString('es-CO', options);
};

const statusInfo = computed(() => {
    const statuses = {
        recibido: { text: 'Recibido', class: 'bg-yellow-100 text-yellow-800' },
        en_preparacion: { text: 'En Preparación', class: 'bg-blue-100 text-blue-800' },
        despachado: { text: 'Despachado', class: 'bg-purple-100 text-purple-800' },
        entregado: { text: 'Entregado', class: 'bg-green-100 text-green-800' },
        cancelado: { text: 'Cancelado', class: 'bg-red-100 text-red-800' },
    };
    return statuses[props.order.status] || { text: props.order.status, class: 'bg-gray-100 text-gray-800' };
});
// --- FIN Lógica de Formateo ---


// --- Lógica para Actualizar Estado (¡Ahora con notificación!) ---
const statusForm = useForm({
    status: props.order.status,
});

const updateStatus = () => {
    const newStatus = statusForm.status;
    statusForm.put(route('admin.orders.update', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            infoType.value = 'success';
            if (newStatus === 'despachado' || newStatus === 'entregado') {
                infoTitle.value = 'Venta confirmada';
                infoMessage.value = 'Inventario actualizado correctamente.';
            } else if (newStatus === 'cancelado') {
                infoTitle.value = 'Pedido cancelado';
                infoMessage.value = 'El estado se actualizó y el inventario fue revertido si correspondía.';
            } else {
                infoTitle.value = 'Estado actualizado';
                infoMessage.value = 'El estado de la orden se guardó correctamente.';
            }
            showInfo.value = true;
        },
        onError: (errors) => {
            infoType.value = 'error';
            infoTitle.value = 'Error al actualizar';
            infoMessage.value = Array.isArray(errors)
                ? errors.join('\n')
                : (errors?.status || Object.values(errors || {}).join('\n') || 'Ocurrió un error inesperado.');
            showInfo.value = true;
        }
    });
};
// --- FIN Lógica de Estado ---

// --- Notificar estado actual por WhatsApp ---
const page = usePage();
const storeName = computed(() => page?.props?.auth?.user?.store?.name || 'nuestra tienda');
const storeUrl = computed(() => {
    const s = page?.props?.auth?.user?.store;
    if (!s) return '#';
    if (s.custom_domain) {
        const protocol = typeof window !== 'undefined' ? window.location.protocol : 'https:';
        return `${protocol}//${s.custom_domain}`;
    }
    return route('catalogo.index', { store: s.slug });
});

// Resumen breve de ítems comprados
const buildItemsSummary = () => {
    try {
        const items = itemsList.value;
        if (!items.length) return '';
        const lines = items.map((it) => {
            const qty = Number(it.quantity || 0);
            let opts = '';
            if (it.variant_options) {
                try {
                    const parts = Object.entries(it.variant_options).map(([k, v]) => `${k}: ${v}`);
                    opts = parts.length ? ` (${parts.join(', ')})` : '';
                } catch (e) { /* noop */ }
            }
            return ` - ${qty} x ${it.product_name}${opts}`;
        });
        return `\nTu orden incluye:\n${lines.join('\n')}`;
    } catch (e) {
        return '';
    }
};

const buildStatusMessage = () => {
    const orderNumber = props.order.sequence_number ?? props.order.id;
    const dateText = formatDate(props.order.created_at);
    const totalText = formatCurrency(props.order.total_price);
    const name = (props.order?.customer_name || '').trim();
    const greeting = name ? `Hola ${name},` : 'Hola,';
    const address = (props.order?.customer_address || '').trim();
    const itemsBlock = buildItemsSummary();

    switch (props.order.status) {
        case 'recibido':
            return `${greeting}\n\n¡Gracias por comprar en ${storeName.value}!\n\nTu pedido No. ${orderNumber} fue recibido correctamente.${itemsBlock ? `\n${itemsBlock}` : ''}\n\nFecha del pedido: ${dateText}.\nTotal del pedido: ${totalText}${address ? `\nDirección: ${address}` : ''}\n\nTe avisaremos cuando esté en preparación.`;
        case 'en_preparacion':
            return `${greeting}\n\nEstamos preparando tu pedido No. ${orderNumber} en ${storeName.value}.${itemsBlock ? `\n${itemsBlock}` : ''}\n\nFecha del pedido: ${dateText}.\nTotal del pedido: ${totalText}${address ? `\nDirección: ${address}` : ''}\n\nTe notificaremos cuando salga a despacho.`;
        case 'despachado':
            return `${greeting}\n\nTu pedido No. ${orderNumber} fue despachado desde ${storeName.value}.${itemsBlock ? `\n${itemsBlock}` : ''}\n\nTotal del pedido: ${totalText}${address ? `\nDirección de entrega: ${address}` : ''}\n\nSi necesitas coordinar algo de la entrega, responde este mensaje.`;
        case 'entregado':
            return `${greeting}\n\n¡Qué alegría! Ya entregamos tu pedido No. ${orderNumber}.${itemsBlock ? `\n${itemsBlock}` : ''}\n\nTotal del pedido: ${totalText}\n\nEsperamos que lo disfrutes. Si quieres descubrir más productos, visita nuestro catálogo: ${storeUrl.value}`;
        case 'cancelado':
            return `${greeting}\n\nTu pedido No. ${orderNumber} fue cancelado. Si no fuiste tú quien lo solicitó, por favor contáctanos para ayudarte.${itemsBlock ? `\n${itemsBlock}` : ''}\n\nCuando desees, puedes volver a comprar aquí: ${storeUrl.value}`;
        default:
            return `${greeting}\n\nEstado actual de tu pedido No. ${orderNumber}: ${statusInfo.value?.text ?? props.order.status}.`;
    }
};

const notifyCurrentStatus = () => {
    try {
        const rawPhone = (props.order?.customer_phone ?? '').toString();
        const phone = rawPhone.replace(/[^0-9]/g, '');
        if (!phone) {
            infoType.value = 'error';
            infoTitle.value = 'Sin teléfono del cliente';
            infoMessage.value = 'No encontramos un número de WhatsApp válido para el cliente.';
            showInfo.value = true;
            return;
        }

        const message = buildStatusMessage();

        const encoded = encodeURIComponent(message);
        const waUrl = `https://wa.me/${phone}?text=${encoded}`;
        if (typeof window !== 'undefined') {
            window.open(waUrl, '_blank');
        }
    } catch (e) {
        infoType.value = 'error';
        infoTitle.value = 'No se pudo abrir WhatsApp';
        infoMessage.value = 'Intenta nuevamente en unos segundos.';
        showInfo.value = true;
    }
};
// --- FIN Notificar estado ---

</script>

<template>
    <Head :title="`Orden #${order.sequence_number ?? order.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detalle de la Orden #{{ order.sequence_number ?? order.id }}
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
                        :href="route('admin.reports.index')" 
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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold border-b pb-4 mb-4">Productos en la Orden</h3>
                            <div class="divide-y divide-gray-200">
                                <div v-for="item in itemsList" :key="item.id" class="py-4 flex justify-between items-start">
                                    <div class="flex-grow">
                                        <p class="font-bold text-gray-900">{{ item.product_name }}</p>
                                        <div v-if="item.variant_options" class="text-sm text-gray-600 mt-1">
                                            <span v-for="(value, key) in item.variant_options" :key="key" class="mr-2 bg-gray-100 px-2 py-1 rounded">
                                                <span class="font-semibold">{{ key }}:</span> {{ value }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-2">
                                            {{ item.quantity }} x {{ formatCurrency(item.unit_price) }}
                                        </p>
                                    </div>
                                    <p class="font-semibold text-gray-800 shrink-0 ml-4">{{ formatCurrency(item.unit_price * item.quantity) }}</p>
                                </div>
                            </div>
                            <div class="border-t border-gray-200 pt-4 mt-4 text-right">
                                <p class="text-2xl font-bold">Total: {{ formatCurrency(order.total_price) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold border-b pb-4 mb-4">Datos del Cliente</h3>
                            <div class="mt-4 space-y-4 text-gray-700">
                                <div>
                                    <p class="font-semibold text-sm text-gray-500">Nombre:</p>
                                    <p>{{ order.customer_name }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-sm text-gray-500">Teléfono:</p>
                                    <p>{{ order.customer_phone }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-sm text-gray-500">Correo:</p>
                                    <p>{{ order.customer_email }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-sm text-gray-500">Dirección:</p>
                                    <p>{{ order.customer_address }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Estado del Pedido</h3>
                            <div class="text-center mb-4">
                                <span class="px-4 py-2 inline-flex text-lg leading-5 font-semibold rounded-full" :class="statusInfo.class">
                                    {{ statusInfo.text }}
                                </span>
                            </div>
                            <p class="text-sm text-center text-gray-500">
                                Pedido realizado el: {{ formatDate(order.created_at) }}
                            </p>

                            <form @submit.prevent="updateStatus">
                                <div class="mt-6 border-t pt-6">
                                    <label for="status" class="block font-medium text-sm text-gray-700 mb-2">Cambiar Estado</label>
                                    <select id="status" v-model="statusForm.status" class="block w-full rounded-md shadow-sm border-gray-300">
                                        <option value="recibido">Recibido</option>
                                        <option value="en_preparacion">En Preparación</option>
                                        <option value="despachado">Despachado</option>
                                        <option value="entregado">Entregado</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                    <button 
                                        type="submit"
                                        :disabled="statusForm.processing"
                                        class="mt-4 w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50">
                                        Actualizar Estado
                                    </button>
                                    <button
                                        type="button"
                                        @click="notifyCurrentStatus"
                                        class="mt-3 w-full bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700">
                                        Notificar estado actual
                                    </button>
                                </div>
                            </form>
                            </div>
                    </div>

                    <!-- Botón verde eliminado: confirmación se ejecuta automáticamente desde 'Actualizar Estado' -->
                </div>

            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="confirmingSale" @close="closeSaleModal">
        <div class="p-6">
            
            <div class="mt-6 flex justify-end">
                
            </div>
        </div>
    </Modal>

    <AlertModal
        :show="showInfo"
        :type="infoType"
        :title="infoTitle"
        :message="infoMessage"
        primary-text="Entendido"
        @primary="showInfo=false"
        @close="showInfo=false"
    />

    <!-- Hidden invoice container for PDF generation -->
    <div id="invoice-content-digital" class="fixed top-0 left-0 w-[80mm] bg-white z-[-100] opacity-0 pointer-events-none">
        <div class="bg-white text-black p-2 font-mono text-[11px] leading-tight" style="width: 80mm; margin: 0 auto; font-family: 'Courier New', Courier, monospace;">
            <!-- Watermark for PDF -->
             <div v-if="order.store?.name || $page.props.auth.user.store?.name" class="absolute inset-0 flex items-center justify-center pointer-events-none z-0 opacity-[0.12]">
                <div class="text-[60px] font-[900] text-black uppercase whitespace-nowrap -rotate-45 transform select-none">
                    {{ order.store?.name || $page.props.auth.user.store?.name }}
                </div>
            </div>

            <div class="relative z-10">
                <!-- Header with Logo -->
                 <div class="text-center mb-4">
                     <img 
                        v-if="order.store?.logo_url || $page.props.auth.user.store?.logo_url" 
                        :src="order.store?.logo_url || $page.props.auth.user.store?.logo_url" 
                        alt="Logo" 
                        class="h-12 w-auto object-contain mx-auto mb-2 grayscale"
                        crossorigin="anonymous"
                    />
                    <h2 class="font-bold text-base uppercase mb-1">{{ order.store?.name || $page.props.auth.user.store?.name }}</h2>
                    <!-- Intentar mostrar info de la tienda si está disponible en 'store' prop o similar -->
                    <div v-if="store" class="text-[10px] space-y-0.5" style="font-size: 10px;">
                        <p v-if="store.nit">NIT: {{ store.nit }}</p>
                        <p v-if="store.phone" class="whitespace-normal">Tel: {{ store.phone }}</p>
                        <p v-if="store.address" class="whitespace-normal">{{ store.address }}</p>
                        <p v-if="store.email" class="whitespace-normal break-words">{{ store.email }}</p>
                    </div>
                </div>

                <div class="border-b-2 border-dashed border-black my-2"></div>

                <!-- Info Grid -->
                <div class="mb-3 text-[10px] grid grid-cols-2 gap-x-2 gap-y-1">
                    <div class="col-span-2 text-center mb-1">
                        <p class="text-sm font-bold">Orden #{{ order.sequence_number ?? order.id }}</p>
                        <p class="text-[9px] text-gray-500">{{ new Date(order.created_at).toLocaleString('es-CO') }}</p>
                    </div>
                    
                    <div>
                        <span class="font-bold block text-gray-600">Canal:</span>
                        <span>Tienda Online</span>
                    </div>
                    <div class="text-right">
                        <span class="font-bold block text-gray-600">Método de Pago:</span>
                        <span class="capitalize">{{ order.payment_method || 'N/A' }}</span>
                    </div>

                    <div v-if="order.customer_name" class="col-span-2 mt-1 border-t border-dotted border-gray-300 pt-1">
                        <p><span class="font-bold text-gray-600">Cliente:</span> {{ order.customer_name }}</p>
                        <p v-if="order.customer_phone"><span class="font-bold text-gray-600">Tel:</span> {{ order.customer_phone }}</p>
                        <p v-if="order.customer_address" class="leading-tight"><span class="font-bold text-gray-600">Dir:</span> {{ order.customer_address }}</p>
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
                             <span v-for="(value, key) in item.variant_options" :key="key" class="mr-1">
                                {{ value }}
                            </span>
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
                                        Precio Normal: {{ formatCurrency(item.original_price || (item.unit_price * 100 / (100 - (item.discount_percent || 0)))) }}
                                    </span>
                                    
                                    <!-- Discount Tag -->
                                    <span class="text-[9px] font-bold text-gray-800">
                                        Desc: {{ item.discount_percent || Math.round((1 - item.unit_price/item.original_price)*100) }}%
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Right Col: Line Total -->
                            <div class="font-bold text-[11px] mt-0.5">
                                {{ formatCurrency(item.unit_price * item.quantity) }}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-black my-2 dashed"></div>

                <!-- Totals -->
                <div class="text-right text-[11px] space-y-1">
                     <div class="flex justify-between text-base font-black pt-1">
                        <span>TOTAL</span>
                        <span>{{ formatCurrency(order.total_price) }}</span>
                    </div>

                    <div class="flex justify-between text-[10px] mt-2 pt-1 border-dotted border-t border-gray-400">
                        <span>Método Pago</span>
                        <span class="capitalize">{{ order.payment_method || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between text-[10px]">
                        <span>Estado</span>
                        <span class="capitalize">{{ order.status }}</span>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-center mt-6 text-[10px] space-y-1 mb-4">
                    <p class="font-medium">¡Gracias por su compra!</p>
                    <p class="italic">Para consultas sobre su envío, contáctenos.</p>
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
    #invoice-content-digital, 
    #invoice-content-digital * {
        visibility: visible;
    }

    /* Posicionar la factura correctamente */
    #invoice-content-digital {
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