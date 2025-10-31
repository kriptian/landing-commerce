<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    cartItems: Array,
    store: Object,
});

// Precio base: Simplificamos para usar la misma lógica que el catálogo y detalle del producto
// Siempre priorizamos el campo 'price' principal del producto, igual que en ProductList y ProductPage
const getBaseUnitPrice = (item) => {
    const v = item?.variant || null;
    const p = item?.product || null;
    
    // Si hay variante seleccionada y tiene precio, usamos el precio de la variante
    // PERO solo si el producto tiene track_inventory activo (lógica del ProductPage)
    if (v && p && p.track_inventory !== false) {
        // Con inventario activo: usar precio de variante si existe, sino precio del producto
        const variantPrice = v.price != null && v.price !== '' ? Number(v.price) : null;
        if (variantPrice !== null) {
            return variantPrice;
        }
    }
    
    // En todos los demás casos (sin variante, inventario desactivado, o variante sin precio):
    // Usar directamente el precio principal del producto (igual que ProductList y ProductPage)
    if (p && p.price != null) {
        return Number(p.price);
    }
    
    return 0;
};

// Promoción efectiva (prioridad tienda > producto)
const promoPercent = (item) => {
    try {
        if (props.store?.promo_active && Number(props.store?.promo_discount_percent || 0) > 0) {
            return Number(props.store.promo_discount_percent);
        }
        if (item?.product?.promo_active && Number(item?.product?.promo_discount_percent || 0) > 0) {
            return Number(item.product.promo_discount_percent);
        }
        return 0;
    } catch (e) { return 0; }
};
const getDisplayUnitPrice = (item) => {
    const base = getBaseUnitPrice(item);
    const percent = promoPercent(item);
    return percent > 0 ? Math.round((base * (100 - percent)) / 100) : base;
};

// Calculamos el precio total con promoción si aplica
const totalPrice = computed(() => props.cartItems.reduce((t, item) => t + (getDisplayUnitPrice(item) * item.quantity), 0));

// Creamos el formulario (sigue igual)
const form = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    customer_address: '',
});

// ===== ESTA ES LA FUNCIÓN QUE CAMBIA =====
const showError = ref(false);
const errorMessage = ref('');

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
            errorMessage.value = 'Hubo un error al procesar tu pedido. Por favor verificá tus datos.';
            showError.value = true;
        },
    });
};
</script>

<template>
    <Head title="Finalizar Compra">
        <template #default>
            <link v-if="store?.logo_url" rel="icon" type="image/png" :href="store.logo_url">
        </template>
    </Head>

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
                    <div v-for="item in cartItems" :key="item.id" class="flex flex-col sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <img :src="item.product.main_image_url" class="w-16 h-16 object-cover rounded">
                            <div class="min-w-0">
                                <p class="font-semibold break-words">{{ item.product.name }}</p>
                                <div v-if="item.variant" class="text-sm text-gray-500 break-words">
                                    <span v-for="(value, key) in item.variant.options" :key="key" class="mr-2 inline-block">
                                        <strong>{{ key }}:</strong> {{ value }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">Cantidad: {{ item.quantity }}</p>
                            </div>
                        </div>
                        <p class="font-semibold whitespace-nowrap shrink-0 sm:text-right mt-2 sm:mt-0">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(getDisplayUnitPrice(item) * item.quantity) }}
                        </p>
                    </div>
                </div>
                
                <div class="border-t mt-6 pt-6">
                    <div class="flex justify-between font-bold text-xl">
                        <span>Total</span>
                        <span>{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(totalPrice) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <AlertModal
        :show="showError"
        type="error"
        title="No pudimos procesar tu pedido"
        :message="errorMessage"
        primary-text="Entendido"
        @primary="showError=false"
        @close="showError=false"
    />
</template>