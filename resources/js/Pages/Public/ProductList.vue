<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    products: Array,
    store: Object, // <-- AHORA RECIBE LA TIENDA ACTUAL
});
</script>

<template>
    <Head :title="`Catálogo de ${store.name}`" />

    <header class="bg-white shadow-sm sticky top-0 z-10">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-900">Landing-Commerce</h1>

            <Link :href="route('cart.index', { store: store.slug })" class="relative flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                <span>🛒</span>
                <span class="ml-2 font-semibold">Carrito</span>

                <span v-if="$page.props.cart.count > 0" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                    {{ $page.props.cart.count }}
                </span>
            </Link>
        </nav>
    </header>

    <main class="container mx-auto px-6 py-12">
        <h2 class="text-3xl font-bold mb-8">Nuestro Catálogo</h2>

        <div v-if="products.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div v-for="product in products" :key="product.id" class="product-card border rounded-lg shadow-md overflow-hidden">
                <img v-if="product.main_image_url" :src="product.main_image_url" alt="Imagen del producto" class="w-full h-64 object-cover">
                <div class="p-4">
                    <h3 class="text-xl font-semibold mb-2">{{ product.name }}</h3>
                    <p class="text-lg text-blue-600 font-bold mb-4">$ {{ Number(product.price).toFixed(2) }}</p>

                    <Link :href="route('catalogo.show', { store: store.slug, product: product.id })" class="w-full bg-gray-800 text-white font-bold py-2 px-4 rounded text-center block hover:bg-gray-700">
                        Ver Detalles
                    </Link>
                </div>
            </div>
        </div>
        <div v-else>
            <p>No hay productos disponibles en esta tienda.</p>
        </div>
    </main>

    <footer class="bg-white mt-16 border-t">
        <div class="container mx-auto px-6 py-4 text-center text-gray-500">
            <p>&copy; 2025 {{ store.name }}</p>
        </div>
    </footer>
</template>