<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    cartItems: Array,
    store: Object,
});

// Calculamos el precio total (sigue igual)
const totalPrice = computed(() => {
    return props.cartItems.reduce((total, item) => {
        const price = item.variant?.price ?? item.product.price;
        return total + (price * item.quantity);
    }, 0);
});

// Creamos el formulario (sigue igual)
const form = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    customer_address: '',
});

// ===== ESTA ES LA FUNCIÓN QUE CAMBIA =====
const submitOrder = () => {
    // Usamos el 'post' de Inertia para enviar los datos del 'form'
    // a la ruta 'checkout.store' que creamos.
    form.post(route('checkout.store', { store: props.store.slug }), {
        onSuccess: () => {
            // El backend se encarga de redirigir a WhatsApp,
            // y el carrito ya se vació. ¡No hay que hacer nada más aquí!
            // Podríamos mostrar un mensaje de "Redirigiendo a WhatsApp..." si quisiéramos.
        },
        onError: (errors) => {
            // Si hay algún error de validación, Inertia lo maneja automáticamente
            console.error('Error al enviar la orden:', errors);
            alert('Hubo un error al procesar tu pedido. Por favor, revisa tus datos.');
        },
    });
};
</script>

<template>
    <Head title="Finalizar Compra" />

    <header class="bg-white shadow-sm sticky top-0">
        <nav class="container mx-auto px-6 py-4">
            <h1 class="text-2xl font-bold text-gray-900">{{ store.name }}</h1>
        </nav>
    </header>

    <main class="container mx-auto px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            
            <div>
                <h1 class="text-3xl font-bold mb-8">Datos de Envío</h1>
                <form @submit.prevent="submitOrder" class="space-y-6 bg-white p-8 shadow rounded-lg">
                    <div>
                        <label for="customer_name" class="block font-medium text-sm text-gray-700">Nombre Completo</label>
                        <input id="customer_name" v-model="form.customer_name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                    </div>
                    
                    <div>
                        <label for="customer_phone" class="block font-medium text-sm text-gray-700">Teléfono (WhatsApp)</label>
                        <input id="customer_phone" v-model="form.customer_phone" type="tel" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                    </div>

                    <div>
                        <label for="customer_email" class="block font-medium text-sm text-gray-700">Correo Electrónico</label>
                        <input id="customer_email" v-model="form.customer_email" type="email" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                    </div>

                    <div>
                        <label for="customer_address" class="block font-medium text-sm text-gray-700">Dirección Completa (con ciudad y detalles)</label>
                        <textarea id="customer_address" v-model="form.customer_address" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" rows="3" required></textarea>
                    </div>
                    
                    <button type="submit" :disabled="form.processing" class="w-full bg-green-600 text-white font-bold py-3 px-6 rounded-lg text-center hover:bg-green-700 disabled:opacity-50">
                        {{ form.processing ? 'Procesando...' : 'Realizar Pedido por WhatsApp' }}
                    </button>
                </form>
            </div>

            <div class="bg-gray-50 p-8 rounded-lg">
                <h2 class="text-2xl font-bold mb-6">Resumen de tu Pedido</h2>
                <div class="space-y-4">
                    <div v-for="item in cartItems" :key="item.id" class="flex justify-between items-center">
                        <div class="flex items-center">
                            <img :src="item.product.main_image_url" class="w-16 h-16 object-cover rounded mr-4">
                            <div>
                                <p class="font-semibold">{{ item.product.name }}</p>
                                <div v-if="item.variant" class="text-sm text-gray-500">
                                    <span v-for="(value, key) in item.variant.options" :key="key" class="mr-2">
                                        <strong>{{ key }}:</strong> {{ value }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">Cantidad: {{ item.quantity }}</p>
                            </div>
                        </div>
                        <p class="font-semibold">$ {{ ((item.variant?.price ?? item.product.price) * item.quantity).toFixed(2) }}</p>
                    </div>
                </div>
                
                <div class="border-t mt-6 pt-6">
                    <div class="flex justify-between font-bold text-xl">
                        <span>Total</span>
                        <span>$ {{ totalPrice.toFixed(2) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </main>
</template>