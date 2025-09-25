<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const toast = useToast();

// ===== CAMBIO 1: AHORA RECIBIMOS EL OBJETO 'store' COMPLETO =====
const props = defineProps({
    cartItems: Array,
    store: Object, // <-- CAMBIAMOS storeSlug por el objeto completo
});
// =============================================================

const totalPrice = computed(() => {
    return props.cartItems.reduce((total, item) => {
        const price = item.variant?.price ?? item.product.price;
        return total + (price * item.quantity);
    }, 0);
});

const confirmingItemDeletion = ref(false);
const itemToDelete = ref(null);

const confirmItemDeletion = (id) => {
    itemToDelete.value = id;
    confirmingItemDeletion.value = true;
};

const closeModal = () => {
    confirmingItemDeletion.value = false;
    itemToDelete.value = null;
};

const deleteItem = () => {
    router.delete(route('cart.destroy', itemToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Producto eliminado del carrito.');
            closeModal();
        },
        onError: () => {
            toast.error('Hubo un error al eliminar el producto.');
            closeModal();
        }
    });
};
</script>

<template>
    <Head title="Mi Carrito" />

    <header class="bg-white shadow-sm sticky top-0 z-10">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover">
                <h1 class="text-2xl font-bold text-gray-900">{{ store.name }}</h1>
            </div>
            
            <div class="flex items-center space-x-4">
                 <a v-if="store.facebook_url" :href="store.facebook_url" target="_blank" class="text-gray-500 hover:text-blue-800">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
                </a>
                <a v-if="store.instagram_url" :href="store.instagram_url" target="_blank" class="text-gray-500 hover:text-pink-600">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.012-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.08 2.525c.636-.247 1.363-.416 2.427-.465C9.53 2.013 9.884 2 12.315 2zm-1.161 1.545a1.12 1.12 0 10-1.584 1.584 1.12 1.12 0 001.584-1.584zm-3.097 3.569a3.468 3.468 0 106.937 0 3.468 3.468 0 00-6.937 0z" clip-rule="evenodd" /><path d="M12 6.166a5.834 5.834 0 100 11.668 5.834 5.834 0 000-11.668zm0 1.545a4.289 4.289 0 110 8.578 4.289 4.289 0 010-8.578z" /></svg>
                </a>
                <a v-if="store.tiktok_url" :href="store.tiktok_url" target="_blank" class="text-gray-500 hover:text-black">
                     <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.525.02c1.31-.02 2.61-.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.01-1.58-.31-3.15-.82-4.7-.52-1.56-1.23-3.04-2.1-4.42a.1.1 0 00-.2-.04c-.02.13-.03.26-.05.39v7.24a.26.26 0 00.27.27c.82.04 1.63.16 2.42.37.04.83.16 1.66.36 2.47.19.82.49 1.6.86 2.33.36.73.81 1.41 1.32 2.02-.17.1-.34.19-.51.28a4.26 4.26 0 01-1.93.52c-1.37.04-2.73-.06-4.1-.23a9.8 9.8 0 01-3.49-1.26c-.96-.54-1.8-1.23-2.52-2.03-.72-.8-1.3-1.7-1.77-2.69-.47-.99-.8-2.06-1.02-3.13a.15.15 0 01.04-.15.24.24 0 01.2-.09c.64-.02 1.28-.04 1.92-.05.1 0 .19-.01.28-.01.07.01.13.02.2.04.19.04.38.09.57.14a5.2 5.2 0 005.02-5.22v-.02a.23.23 0 00-.23-.23.2.2 0 00-.2-.02c-.83-.06-1.66-.13-2.49-.22-.05-.01-.1-.01-.15-.02-1.12-.13-2.25-.26-3.37-.44a.2.2 0 01-.16-.24.22.22 0 01.23-.18c.41-.06.82-.12 1.23-.18C9.9.01 11.21 0 12.525.02z"/></svg>
                </a>
            </div>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-12">
        <div class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold">Mi Carrito de Compras</h1>
            <Link :href="route('catalogo.index', { store: store.slug })" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300">
                Seguir Comprando
            </Link>
        </div>

        <div v-if="cartItems.length === 0" class="bg-white shadow rounded-lg p-6 text-center">
            <p class="text-gray-600">Tu carrito está vacío.</p>
            <Link :href="route('catalogo.index', { store: store.slug })" class="mt-4 inline-block bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
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
                                <div v-if="item.variant" class="text-sm text-gray-500">
                                    <span v-for="(value, key) in item.variant.options" :key="key" class="mr-2">
                                        <strong>{{ key }}:</strong> {{ value }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-gray-700">{{ item.quantity }}</td>
                        <td class="p-4 text-gray-700">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(item.variant?.price ?? item.product.price) }}
                        </td>
                        <td class="p-4 text-gray-700">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format((item.variant?.price ?? item.product.price) * item.quantity) }}
                        </td>
                        <td class="p-4">
                            <button @click="confirmItemDeletion(item.id)" class="text-red-600 hover:underline">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="p-6 bg-gray-50 border-t flex justify-end">
                <div class="text-right">
                    <p class="text-xl font-bold text-gray-900">
                        Total: {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(totalPrice) }}
                    </p>
                    <Link :href="route('checkout.index', { store: store.slug })" class="inline-block mt-4 w-full bg-green-600 text-white font-bold py-2 px-4 rounded text-center hover:bg-green-700">
                        Proceder al Pago
                    </Link>
                </div>
            </div>
        </div>
    </main>

    <Modal :show="confirmingItemDeletion" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Estás seguro de que querés eliminar este producto?
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Una vez eliminado, tendrás que volver a añadirlo desde el catálogo.
            </p>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    @click="deleteItem"
                >
                    Eliminar Producto
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>