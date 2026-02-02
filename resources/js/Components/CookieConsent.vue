<script setup>
import { ref, onMounted } from 'vue';

const isVisible = ref(false);

const acceptCookies = () => {
    localStorage.setItem('cookie_consent', 'accepted');
    isVisible.value = false;
};

const rejectCookies = () => {
    // We just close the banner without setting the consent cookie
    // or set a 'rejected' status if we want to remember the choice but not track
    localStorage.setItem('cookie_consent', 'rejected'); // Optional: remember rejection
    isVisible.value = false;
};

onMounted(() => {
    const consent = localStorage.getItem('cookie_consent');
    if (!consent) {
        isVisible.value = true;
    }
});
</script>

<template>
    <div v-if="isVisible" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] z-50 p-4 md:p-6 transition-transform duration-300 ease-in-out transform">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-gray-600 flex-1">
                <p>
                    <span class="font-semibold text-gray-800">Este sitio usa cookies</span> para mejorar tu experiencia de navegación. 
                    Al continuar navegando, aceptas nuestra política de privacidad y el uso de tecnologías de rastreo.
                </p>
            </div>
            
            <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto">
                <button 
                    @click="rejectCookies"
                    class="flex-1 sm:flex-none justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
                >
                    Rechazar
                </button>
                <button 
                    @click="acceptCookies"
                    class="flex-1 sm:flex-none justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                    Aceptar
                </button>
            </div>
        </div>
    </div>
</template>
