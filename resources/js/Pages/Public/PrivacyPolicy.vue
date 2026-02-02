<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    store: Object,
    privacyPolicy: String,
});
</script>

<template>
    <Head :title="`Política de Privacidad - ${store.name}`" />
    
    <div class="min-h-screen bg-gray-50 flex flex-col font-sans">
        <!-- Header / Nav -->
        <header class="bg-white shadow-sm sticky top-0 z-20 transition-all duration-300">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between">
                <!-- Logo & Name -->
                <Link :href="route('catalogo.index', { store: store.slug })" class="flex items-center gap-3 sm:gap-4 group">
                    <div class="relative w-10 h-10 sm:w-12 sm:h-12 flex-shrink-0 transition-transform transform group-hover:scale-105">
                        <img 
                            v-if="store.logo_url" 
                            :src="store.logo_url.startsWith('http') || store.logo_url.startsWith('/') ? store.logo_url : `/storage/${store.logo_url}`" 
                            :alt="store.name"
                            class="w-full h-full object-contain rounded-full border border-gray-100 shadow-sm bg-white"
                        />
                        <div v-else class="w-full h-full rounded-full bg-gray-100 flex items-center justify-center border border-gray-200 text-gray-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Tienda</span>
                        <h1 class="text-lg sm:text-xl font-bold text-gray-900 leading-tight truncate max-w-[150px] sm:max-w-xs transition-colors group-hover:text-blue-600">
                            {{ store.name }}
                        </h1>
                    </div>
                </Link>

                <!-- Action Button -->
                <Link 
                    :href="route('catalogo.index', { store: store.slug })" 
                    class="inline-flex items-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 rounded-full font-semibold text-xs sm:text-sm shadow-sm hover:shadow-md transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0"
                    :style="{
                        backgroundColor: store.catalog_use_default ? '#ffffff' : store.catalog_button_bg_color,
                        color: store.catalog_use_default ? '#374151' : store.catalog_button_text_color,
                        border: store.catalog_use_default ? '1px solid #e5e7eb' : '1px solid transparent',
                    }"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="hidden sm:inline">Volver a la tienda</span>
                    <span class="sm:hidden">Volver</span>
                </Link>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <!-- Title Section -->
                <div class="px-6 py-8 sm:px-10 sm:py-10 text-center bg-gradient-to-b from-white to-gray-50 border-b border-gray-100">
                    <span class="inline-block p-3 rounded-full bg-blue-50 text-blue-600 mb-4 ring-4 ring-blue-50/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                        Política de Privacidad
                    </h2>
                    <p class="mt-2 text-sm text-gray-500">
                        Última actualización: {{ new Date().toLocaleDateString() }}
                    </p>
                </div>

                <!-- Text Content -->
                <div class="px-6 py-8 sm:px-10 sm:py-12 bg-white">
                    <div v-if="privacyPolicy" class="prose prose-blue prose-lg max-w-none text-gray-600 leading-relaxed whitespace-pre-line selection:bg-blue-100 selection:text-blue-700">
                        {{ privacyPolicy }}
                    </div>
                    
                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-12 px-4 text-center rounded-xl bg-gray-50 border-2 border-dashed border-gray-200">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Sin información disponible</h3>
                        <p class="mt-1 text-gray-500 max-w-sm">
                            El administrador de la tienda aún no ha publicado los detalles de su política de privacidad.
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Minimal Footer -->
        <footer class="py-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-2">
                <div class="h-px w-8 bg-gray-300"></div>
                <span class="text-gray-400 text-xs uppercase tracking-widest">{{ store.name }}</span>
                <div class="h-px w-8 bg-gray-300"></div>
            </div>
            <p class="text-xs text-gray-400">
                &copy; {{ new Date().getFullYear() }} Todos los derechos reservados.
            </p>
        </footer>
    </div>
</template>
