<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue'; // <-- Importamos ref
import { useToast } from 'vue-toastification';
// --- 1. IMPORTAMOS LOS COMPONENTES NUEVOS ---
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
// ---------------------------------------------

const toast = useToast();

// Recibimos los items del carrito que nos mandó el CartController
const props = defineProps({
    cartItems: Array,
    storeSlug: String,
});

// Calculamos el precio total (ya estaba corregido)
const totalPrice = computed(() => {
    return props.cartItems.reduce((total, item) => {
        const price = item.variant?.price ?? item.product.price;
        return total + (price * item.quantity);
    }, 0);
});


// --- 2. LÓGICA NUEVA PARA EL MODAL DE CONFIRMACIÓN ---
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
// ----------------------------------------------------
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
            <Link :href="storeSlug ? route('catalogo.index', { store: storeSlug }) : route('home')" class="mt-4 inline-block bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
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
                        <td class="p-4 text-gray-700">$ {{ Number(item.variant?.price ?? item.product.price).toFixed(2) }}</td>
                        <td class="p-4 text-gray-700">$ {{ ((item.variant?.price ?? item.product.price) * item.quantity).toFixed(2) }}</td>
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
                        Total: $ {{ totalPrice.toFixed(2) }}
                    </p>
                    <Link :href="route('checkout.index', { store: storeSlug })" class="inline-block mt-4 w-full bg-green-600 text-white font-bold py-2 px-4 rounded text-center hover:bg-green-700">
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