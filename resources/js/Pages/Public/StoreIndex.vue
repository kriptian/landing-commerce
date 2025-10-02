<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stores: Array,
});
</script>

<template>
    <Head title="Nuestras Tiendas">
        <template #default>
            <link rel="icon" type="image/png" href="/images/digitalsolution-logo.png">
        </template>
    </Head>
    <div class="bg-gray-100 min-h-screen">
        <header class="bg-white shadow-sm">
            <nav class="container mx-auto px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-900">Landing-Commerce</h1>
            </nav>
        </header>

        <main class="container mx-auto px-6 py-12">
            <h2 class="text-3xl font-bold mb-8 text-center">Nuestras Tiendas</h2>

            <div v-if="stores.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div v-for="store in stores" :key="store.id" class="store-card border rounded-lg shadow-md overflow-hidden bg-white">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold mb-2">{{ store.name }}</h3>
                        <p class="text-gray-600 mb-4">Explorá los productos de esta tienda.</p>
                        
                        <Link :href="route('catalogo.index', { store: store.slug })" class="w-full bg-gray-800 text-white font-bold py-2 px-4 rounded text-center block hover:bg-gray-700">
                            Ver Catálogo
                        </Link>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-gray-500">
                <p>No hay tiendas disponibles en este momento.</p>
            </div>
        </main>
    </div>
</template>