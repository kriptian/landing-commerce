<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const page = usePage();
const store = computed(() => page.props.store);

const isVisible = ref(false);

const acceptCookies = () => {
    localStorage.setItem('cookie_consent', 'accepted');
    isVisible.value = false;
};

// Check if consent is active in store settings
const isActive = computed(() => {
    return store.value?.cookie_consent_active ?? false;
});

onMounted(() => {
    // Only show if store has enabled cookie consent
    if (!isActive.value) return;

    const consent = localStorage.getItem('cookie_consent');
    if (!consent) {
        isVisible.value = true;
    }
});
</script>

<template>
    <div v-if="isVisible && isActive" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] z-[100] p-4 md:p-6 transition-transform duration-300 ease-in-out transform">
        <div class="max-w-4xl mx-auto flex flex-col items-center text-center gap-4">
            <div class="text-sm sm:text-base text-gray-700 font-medium">
                <p>
                    Este sitio usa cookies para ofrecerte una mejor experiencia de navegación. Al continuar
                    navegando, aceptas nuestro uso de cookies.
                </p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto mt-1 sm:mt-0">
                <Link 
                    :href="route('store.privacy', { store: store.slug })"
                    class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-white bg-blue-500 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-400 uppercase tracking-wide text-center"
                >
                    Más Información
                </Link>
                <button 
                    @click="acceptCookies"
                    class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-white bg-amber-400 rounded-lg hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-300 uppercase tracking-wide"
                >
                    Aceptar
                </button>
            </div>
        </div>
    </div>
</template>
