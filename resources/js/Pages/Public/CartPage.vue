<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3'; // <-- 1. IMPORTAMOS EL ROUTER

// Recibimos los items del carrito que nos mandó el CartController
const props = defineProps({
    cartItems: Array,
    storeSlug: String,
});

// Calculamos el precio total sumando (precio * cantidad) de cada item
const totalPrice = computed(() => {
    return props.cartItems.reduce((total, item) => {
        return total + (item.product.price * item.quantity);
    }, 0);
});

// --- 2. CREAMOS LA FUNCIÓN PARA ELIMINAR ---
const removeItem = (id) => {
    // Ponemos una confirmación por si las moscas
    if (confirm('¿Estás seguro de que querés eliminar este producto del carrito?')) {
        // Usamos el router para llamar a la ruta 'destroy' que creamos
        router.delete(route('cart.destroy', id), {
            preserveScroll: true // Para que la página no brinque al recargar
        });
    }
};
</script>

<template>
    <Head title="Mi Carrito" />

    <header class="bg-white shadow-sm sticky top-0">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-900">Landing-Commerce</h1>

            <Link 
                :href="storeSlug ? route('catalogo.index', { store: storeSlug }) : route('home')" 
                class="text-blue-600 hover:underline">
                Seguir Comprando
            </Link>
        </nav>
    </header>

    <main class="container mx-auto px-6 py-12">
        <h1 class="text-3xl font-bold mb-8">Mi Carrito de Compras</h1>

        <div v-if="cartItems.length === 0" class="bg-white shadow rounded-lg p-6 text-center">
            <p class="text-gray-600">Tu carrito está vacío.</p>
            <Link :href="route('catalogo.index', { store: storeSlug })" class="mt-4 inline-block bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                Ir a la tienda
            </Link>
        </div>

        <div v-else class="bg-white shadow rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-4 text-left font-semibold text-gray-600">Producto</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Cantidad</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Precio Unit.</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Total</th>
                        <th class="p-4 text-left font-semibold text-gray-600">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in cartItems" :key="item.id" class="border-t">
                        <td class="p-4 flex items-center space-x-4">
                            <img :src="item.product.main_image_url" alt="product image" class="w-16 h-16 object-cover rounded">
                            <div>
                                <p class="font-semibold">{{ item.product.name }}</p>
                            </div>
                        </td>
                        <td class="p-4 text-gray-700">{{ item.quantity }}</td>
                        <td class="p-4 text-gray-700">$ {{ Number(item.product.price).toFixed(2) }}</td>
                        <td class="p-4 text-gray-700">$ {{ (item.product.price * item.quantity).toFixed(2) }}</td>
                        <td class="p-4">
                            <button @click="removeItem(item.id)" class="text-red-600 hover:underline">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="p-6 bg-gray-50 border-t flex justify-end">
                <div class="text-right">
                    <p class="text-xl font-bold text-gray-900">
                        Total: $ {{ totalPrice.toFixed(2) }}
                    </p>
                    <button class="mt-4 w-full bg-green-600 text-white font-bold py-3 px-6 rounded-lg text-center hover:bg-green-700">
                        Proceder al Pago
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>