<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="close"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Inventario para: <span class="text-blue-600">{{ variantName }}</span>
                            </h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stock Actual</label>
                                    <input 
                                        type="number" 
                                        v-model="form.stock" 
                                        min="0"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        placeholder="0"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Alerta de Stock Bajo</label>
                                    <input 
                                        type="number" 
                                        v-model="form.alert" 
                                        min="0"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        placeholder="Ej: 5"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">Dejar vacío para usar configuración general.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Precio de Compra</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input 
                                            type="number" 
                                            v-model="form.purchase_price" 
                                            step="0.01"
                                            class="block w-full pl-7 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Opcional. Útil para reportes de ganancias.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button 
                        type="button" 
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                        @click="save"
                    >
                        Guardar
                    </button>
                    <button 
                        type="button" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        @click="close"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    variantName: String,
    initialData: {
        type: Object,
        default: () => ({ stock: 0, alert: null, purchase_price: null })
    }
});

const emit = defineEmits(['close', 'save']);

const form = reactive({
    stock: 0,
    alert: null,
    purchase_price: null
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.stock = props.initialData.stock || 0;
        form.alert = props.initialData.alert;
        form.purchase_price = props.initialData.purchase_price;
    }
});

const close = () => {
    emit('close');
};

const save = () => {
    emit('save', { ...form });
    close();
};
</script>
