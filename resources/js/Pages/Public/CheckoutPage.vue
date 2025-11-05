<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    cartItems: Array,
    store: Object,
});

// Precio base: priorizar precio de variante si existe, sino usar precio principal del producto
// IMPORTANTE: Ahora usamos el precio de variante incluso si track_inventory está desactivado
// porque las variant_options pueden tener precios independientes
const getBaseUnitPrice = (item) => {
    const v = item?.variant || null;
    const p = item?.product || null;
    
    // Si hay variante seleccionada, usar precio de variante si existe
    if (v && p) {
        const variantPrice = v.price != null && v.price !== '' ? Number(v.price) : null;
        if (variantPrice !== null) {
            return variantPrice;
        }
    }
    
    // Si no hay variante o la variante no tiene precio: usar precio principal del producto
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

    <header class="bg-white shadow-sm sticky top-0 z-40">
        <nav class="container mx-auto px-6 py-4 flex items-center gap-3">
            <img v-if="store?.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover ring-2 ring-gray-100">
            <h1 class="text-lg font-medium text-gray-600">{{ store.name }}</h1>
        </nav>
    </header>

    <main class="container mx-auto px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
            
            <div>
                <h1 class="text-xl sm:text-2xl font-medium text-gray-600 mb-6">Datos de Envío</h1>
                <form @submit.prevent="submitOrder" class="space-y-6 bg-white p-8 lg:p-10 shadow-2xl rounded-2xl border border-gray-100 backdrop-blur-sm transform transition-all hover:shadow-3xl">
                    <div>
                        <label for="customer_name" class="block font-medium text-sm text-gray-700 mb-2">Nombre Completo</label>
                        <input id="customer_name" v-model="form.customer_name" type="text" class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4 bg-gray-50 hover:bg-white" required>
                    </div>
                    
                    <div>
                        <label for="customer_phone" class="block font-medium text-sm text-gray-700 mb-2">Teléfono (WhatsApp)</label>
                        <input id="customer_phone" v-model="form.customer_phone" type="tel" class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4 bg-gray-50 hover:bg-white" required>
                    </div>

                    <div>
                        <label for="customer_email" class="block font-medium text-sm text-gray-700 mb-2">Correo Electrónico</label>
                        <input id="customer_email" v-model="form.customer_email" type="email" class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4 bg-gray-50 hover:bg-white" required>
                    </div>

                    <div>
                        <label for="customer_address" class="block font-medium text-sm text-gray-700 mb-2">Dirección Completa (con ciudad y detalles)</label>
                        <textarea id="customer_address" v-model="form.customer_address" class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4 bg-gray-50 hover:bg-white resize-none" rows="3" required></textarea>
                    </div>
                    
                    <button type="submit" :disabled="form.processing" class="w-full bg-green-600 text-white font-bold py-4 px-6 rounded-xl text-center hover:bg-green-700 disabled:opacity-50 transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl">
                        {{ form.processing ? 'Procesando...' : 'Realizar Pedido por WhatsApp' }}
                    </button>
                </form>
            </div>

            <div class="bg-white/95 backdrop-blur-sm p-8 lg:p-10 rounded-2xl shadow-2xl border border-gray-100 sticky top-24 transform transition-all hover:shadow-3xl">
                <h2 class="text-xl sm:text-2xl font-medium text-gray-600 mb-6">Resumen de tu Pedido</h2>
                <div class="space-y-4">
                    <div v-for="item in cartItems" :key="item.id" class="flex flex-col sm:flex-row sm:items-start sm:justify-between sm:gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <img :src="item.product.main_image_url" class="w-16 h-16 object-cover rounded-lg shadow-sm ring-1 ring-gray-200">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 break-words">{{ item.product.name }}</p>
                                <div v-if="item.variant" class="text-sm text-gray-500 break-words mt-1">
                                    <span v-for="(value, key) in item.variant.options" :key="key" class="mr-2 inline-block">
                                        <strong>{{ key }}:</strong> {{ value }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">Cantidad: {{ item.quantity }}</p>
                            </div>
                        </div>
                        <p class="font-semibold text-gray-900 whitespace-nowrap shrink-0 sm:text-right mt-2 sm:mt-0">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(getDisplayUnitPrice(item) * item.quantity) }}
                        </p>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 mt-6 pt-6">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-medium text-gray-600">Total</span>
                        <span class="text-2xl font-bold text-gray-900">{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(totalPrice) }}</span>
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