<script setup>
import Modal from '@/Components/Modal.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    message: {
        type: String,
        default: 'Debes iniciar sesión para realizar esta acción.',
    },
    store: {
        type: Object,
        required: false, // Make it optional to avoid breaking other usages if any
    }
});

const emit = defineEmits(['close']);
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="sm">
        <div class="p-6 text-center">
            <div class="mb-4 mx-auto w-12 h-12 flex items-center justify-center bg-blue-100 rounded-full text-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
            </div>
            
            <h3 class="text-lg font-medium text-gray-900 mb-2">
                Iniciar Sesión Requerido
            </h3>
            
            <p class="text-sm text-gray-600 mb-6">
                {{ message }}
            </p>
            
            <div class="flex flex-col gap-3">
                <Link
                    :href="route('customer.login', { store: store?.slug || store?.id })"
                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    Iniciar Sesión
                </Link>
                
                <Link
                    :href="route('customer.register', { store: store?.slug || store?.id })"
                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                >
                    Registrarse
                </Link>
                
                <button
                    @click="emit('close')"
                    class="mt-2 text-xs text-gray-500 hover:text-gray-700 underline"
                >
                    Cancelar
                </button>
            </div>
        </div>
    </Modal>
</template>
