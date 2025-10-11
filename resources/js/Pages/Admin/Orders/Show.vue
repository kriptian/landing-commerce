<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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