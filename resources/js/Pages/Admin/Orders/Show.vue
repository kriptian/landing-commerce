<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const showInfo = ref(false);
const infoTitle = ref('');
const infoMessage = ref('');
const infoType = ref('success'); // 'success' | 'error'

const props = defineProps({
    order: Object,
});

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
        const items = Array.isArray(props.order?.items) ? props.order.items : [];
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
    const totalText = `$ ${Number(props.order.total_price).toLocaleString('es-CO')}`;
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
            <div class="flex items-center space-x-4">
                <Link :href="route('admin.orders.index')" class="text-blue-600 hover:text-blue-800">
                    &larr; Volver a Órdenes
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detalle de la Orden #{{ order.sequence_number ?? order.id }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold border-b pb-4 mb-4">Productos en la Orden</h3>
                            <div class="divide-y divide-gray-200">
                                <div v-for="item in order.items" :key="item.id" class="py-4 flex justify-between items-start">
                                    <div class="flex-grow">
                                        <p class="font-bold text-gray-900">{{ item.product_name }}</p>
                                        <div v-if="item.variant_options" class="text-sm text-gray-600 mt-1">
                                            <span v-for="(value, key) in item.variant_options" :key="key" class="mr-2 bg-gray-100 px-2 py-1 rounded">
                                                <span class="font-semibold">{{ key }}:</span> {{ value }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-2">
                                            {{ item.quantity }} x $ {{ Number(item.unit_price).toLocaleString('es-CO') }}
                                        </p>
                                    </div>
                                    <p class="font-semibold text-gray-800 shrink-0 ml-4">$ {{ Number(item.unit_price * item.quantity).toLocaleString('es-CO') }}</p>
                                </div>
                            </div>
                            <div class="border-t border-gray-200 pt-4 mt-4 text-right">
                                <p class="text-2xl font-bold">Total: $ {{ Number(order.total_price).toLocaleString('es-CO') }}</p>
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
</template>