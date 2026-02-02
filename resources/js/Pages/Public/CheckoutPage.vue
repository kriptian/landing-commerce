<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import AuthRequiredModal from '@/Components/Public/AuthRequiredModal.vue';
import CookieConsent from '@/Components/CookieConsent.vue';

const props = defineProps({
    cartItems: Array,
    store: Object,
    customer: Object,
    addresses: Array,
});

// Colores personalizados del catálogo
const catalogUseDefault = computed(() => props.store?.catalog_use_default ?? true);
const bodyBgColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_body_bg_color || '#FFFFFF';
});
const bodyTextColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_body_text_color || '#1F2937';
});
const inputBgColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_input_bg_color || '#FFFFFF';
});
const inputTextColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_input_text_color || '#1F2937';
});
const buttonBgColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_button_bg_color || '#2563EB';
});
const buttonTextColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_button_text_color || '#FFFFFF';
});

const bodyStyleObj = computed(() => {
    if (catalogUseDefault.value) return {}; // Modo por defecto: no aplicar estilos personalizados
    if (!bodyBgColor.value || !bodyTextColor.value) return {};
    return {
        backgroundColor: bodyBgColor.value,
        color: bodyTextColor.value,
    };
});

const inputStyleObj = computed(() => {
    if (catalogUseDefault.value) return {}; // Modo por defecto: no aplicar estilos personalizados
    if (!inputBgColor.value || !inputTextColor.value) return {};
    return {
        backgroundColor: inputBgColor.value,
        color: inputTextColor.value,
    };
});

const buttonStyleObj = computed(() => {
    if (catalogUseDefault.value) return {};
    if (!buttonBgColor.value || !buttonTextColor.value) return {};
    return {
        backgroundColor: buttonBgColor.value,
        color: buttonTextColor.value,
    };
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
const subtotal = computed(() => props.cartItems.reduce((t, item) => t + (getDisplayUnitPrice(item) * item.quantity), 0));

// Cupón de descuento
const couponCode = ref('');
const coupon = ref(null);
const couponError = ref('');
const couponLoading = ref(false);
const showAuthRequiredModal = ref(false); // Modal para loguearse

const discountAmount = computed(() => {
    if (!coupon.value) return 0;
    if (coupon.value.type === 'percentage') {
        const discount = (subtotal.value * coupon.value.value) / 100;
        return coupon.value.max_discount ? Math.min(discount, coupon.value.max_discount) : discount;
    } else {
        return Math.min(coupon.value.value, subtotal.value);
    }
});

// --- DELIVERY COST LOGIC ---
const deliveryCost = computed(() => {
    // Si la tienda tiene activo el domicilio y tiene un costo > 0
    if (props.store?.delivery_cost_active && Number(props.store?.delivery_cost) > 0) {
        return Number(props.store.delivery_cost);
    }
    return 0;
});
// ---------------------------

// Precio total con descuento y envío
const totalPrice = computed(() => Math.max(0, subtotal.value - discountAmount.value) + deliveryCost.value);

// Aplicar cupón
const applyCoupon = async () => {
    if (!couponCode.value.trim()) {
        couponError.value = 'Ingresa un código de cupón';
        return;
    }
    
    couponLoading.value = true;
    couponError.value = '';
    
    try {
        const response = await window.axios.post(route('checkout.validate-coupon', { store: props.store.slug }), {
            code: couponCode.value.trim(),
        });
        
        if (response.data.valid) {
            coupon.value = response.data.coupon;
            couponError.value = '';
        } else {
            // Verificar si es error de autenticación
            if (response.data.code === 'LOGIN_REQUIRED') {
                 showAuthRequiredModal.value = true;
                 coupon.value = null;
                 // No mostrar error en texto rojo, el modal es suficiente
            } else {
                couponError.value = response.data.message || 'Cupón inválido';
                coupon.value = null;
            }
        }
    } catch (error) {
        couponError.value = error.response?.data?.message || 'Error al validar el cupón';
        coupon.value = null;
    } finally {
        couponLoading.value = false;
    }
};

// Remover cupón
const removeCoupon = () => {
    couponCode.value = '';
    coupon.value = null;
    couponError.value = '';
};

// Dirección seleccionada
const selectedAddressId = ref(null);
const useCustomAddress = ref(false);
const customAddress = ref('');

// Prellenar datos si el cliente está logueado
const customer = computed(() => props.customer || null);
const addresses = computed(() => props.addresses || []);

// Inicializar formulario con datos del cliente
const initializeForm = () => {
    if (customer.value) {
        form.customer_name = customer.value.name || '';
        form.customer_phone = customer.value.phone || '';
        form.customer_email = customer.value.email || '';
        
        // Si tiene dirección predeterminada, usarla
        const defaultAddress = addresses.value.find(addr => addr.is_default);
        if (defaultAddress) {
            selectedAddressId.value = defaultAddress.id;
            form.customer_address = defaultAddress.full_address || `${defaultAddress.address_line_1}, ${defaultAddress.city}`;
        }
    }
};

// Inicializar al montar
onMounted(() => {
    initializeForm();
});

// Cuando cambia la dirección seleccionada
const onAddressChange = (addressId) => {
    if (addressId) {
        const address = addresses.value.find(addr => addr.id === addressId);
        if (address) {
            form.customer_address = address.full_address || `${address.address_line_1}, ${address.city}`;
            useCustomAddress.value = false;
        }
    }
};

// Creamos el formulario
const form = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    customer_address: '',
    address_id: null,
    coupon_code: null,
});

// ===== ESTA ES LA FUNCIÓN QUE CAMBIA =====
const showError = ref(false);
const errorMessage = ref('');

const submitOrder = () => {
    // Agregar datos adicionales al formulario
    form.address_id = selectedAddressId.value;
    form.coupon_code = coupon.value ? couponCode.value : null;
    
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

    <main class="container mx-auto px-6 py-12 min-h-screen" :style="bodyStyleObj">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
            
            <div>
                <h1 class="text-xl sm:text-2xl font-medium text-gray-600 mb-6">Datos de Envío</h1>
                
                <!-- Mensaje si no está logueado -->
                <div v-if="!customer" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800 mb-2">
                        ¿Ya tienes cuenta? <Link :href="route('customer.login', { store: store.slug })" class="font-semibold underline">Inicia sesión</Link> para prellenar tus datos o <Link :href="route('customer.register', { store: store.slug })" class="font-semibold underline">regístrate</Link> para guardar tus direcciones.
                    </p>
                </div>

                <form @submit.prevent="submitOrder" class="space-y-6 bg-white p-8 lg:p-10 shadow-2xl rounded-2xl border border-gray-100 backdrop-blur-sm transform transition-all hover:shadow-3xl">
                    <div>
                        <label for="customer_name" class="block font-medium text-sm text-gray-700 mb-2">Nombre Completo</label>
                        <input id="customer_name" v-model="form.customer_name" type="text" class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4" :class="catalogUseDefault ? 'bg-gray-50 hover:bg-white' : ''" :style="inputStyleObj" required>
                    </div>
                    
                    <div>
                        <label for="customer_phone" class="block font-medium text-sm text-gray-700 mb-2">Teléfono (WhatsApp)</label>
                        <input id="customer_phone" v-model="form.customer_phone" type="tel" class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4" :class="catalogUseDefault ? 'bg-gray-50 hover:bg-white' : ''" :style="inputStyleObj" required>
                    </div>

                    <div>
                        <label for="customer_email" class="block font-medium text-sm text-gray-700 mb-2">Correo Electrónico</label>
                        <input id="customer_email" v-model="form.customer_email" type="email" class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4" :class="catalogUseDefault ? 'bg-gray-50 hover:bg-white' : ''" :style="inputStyleObj" required>
                    </div>

                    <!-- Selector de direcciones si el cliente está logueado -->
                    <div v-if="customer && addresses.length > 0">
                        <label class="block font-medium text-sm text-gray-700 mb-2">Dirección de Envío</label>
                        <select 
                            v-model="selectedAddressId" 
                            @change="onAddressChange(selectedAddressId)"
                            class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4 mb-3" 
                            :class="catalogUseDefault ? 'bg-gray-50 hover:bg-white' : ''" 
                            :style="inputStyleObj"
                        >
                            <option :value="null">Seleccionar dirección guardada</option>
                            <option v-for="address in addresses" :key="address.id" :value="address.id">
                                {{ address.label }} - {{ address.address_line_1 }}, {{ address.city }}
                            </option>
                        </select>
                        <button 
                            type="button" 
                            @click="useCustomAddress = !useCustomAddress"
                            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                        >
                            {{ useCustomAddress ? 'Usar dirección guardada' : 'Usar dirección diferente' }}
                        </button>
                    </div>

                    <div>
                        <label for="customer_address" class="block font-medium text-sm text-gray-700 mb-2">
                            {{ customer && addresses.length > 0 && !useCustomAddress ? 'Dirección (seleccionada arriba)' : 'Dirección Completa (con ciudad y detalles)' }}
                        </label>
                        <textarea 
                            id="customer_address" 
                            v-model="form.customer_address" 
                            :disabled="customer && addresses.length > 0 && !useCustomAddress && selectedAddressId"
                            class="block w-full rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4 resize-none" 
                            :class="[
                                catalogUseDefault ? 'bg-gray-50 hover:bg-white' : '',
                                customer && addresses.length > 0 && !useCustomAddress && selectedAddressId ? 'bg-gray-100 cursor-not-allowed' : ''
                            ]" 
                            :style="inputStyleObj" 
                            rows="3" 
                            required
                        ></textarea>
                    </div>
                    
                    <button type="submit" :disabled="form.processing" class="w-full font-bold py-4 px-6 rounded-xl text-center disabled:opacity-50 transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl" :class="catalogUseDefault ? 'bg-green-600 text-white hover:bg-green-700' : ''" :style="!catalogUseDefault && buttonStyleObj ? buttonStyleObj : {}">
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
                
                <!-- Campo de cupón -->
                <div class="border-t border-gray-200 pt-6">
                    <label class="block font-medium text-sm text-gray-700 mb-2">Código de Cupón (Opcional)</label>
                    <div class="flex gap-2">
                        <input 
                            v-model="couponCode" 
                            type="text" 
                            placeholder="Ingresa el código"
                            :disabled="couponLoading || !!coupon"
                            class="flex-1 block rounded-xl shadow-sm border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all py-3 px-4" 
                            :class="catalogUseDefault ? 'bg-gray-50 hover:bg-white' : ''" 
                            :style="inputStyleObj"
                        >
                        <button 
                            v-if="!coupon"
                            type="button"
                            @click="applyCoupon"
                            :disabled="couponLoading || !couponCode.trim()"
                            class="px-6 py-3 rounded-xl font-medium transition-all disabled:opacity-50"
                            :class="catalogUseDefault ? 'bg-blue-600 text-white hover:bg-blue-700' : ''"
                            :style="!catalogUseDefault && buttonStyleObj ? buttonStyleObj : {}"
                        >
                            {{ couponLoading ? '...' : 'Aplicar' }}
                        </button>
                        <button 
                            v-else
                            type="button"
                            @click="removeCoupon"
                            class="px-6 py-3 rounded-xl font-medium bg-red-600 text-white hover:bg-red-700 transition-all"
                        >
                            Quitar
                        </button>
                    </div>
                    <p v-if="couponError" class="mt-2 text-sm text-red-600">{{ couponError }}</p>
                    <p v-if="coupon" class="mt-2 text-sm text-green-600">
                        ✓ Cupón aplicado: {{ coupon.type === 'percentage' ? coupon.value + '%' : '$' + coupon.value }} de descuento
                    </p>
                </div>

                <div class="border-t border-gray-200 mt-6 pt-6 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-medium text-gray-600">Subtotal</span>
                        <span class="text-lg font-semibold text-gray-900">{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(subtotal) }}</span>
                    </div>
                    <div v-if="discountAmount > 0" class="flex justify-between items-center text-green-600">
                        <span class="text-lg font-medium">Descuento</span>
                        <span class="text-lg font-semibold">-{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(discountAmount) }}</span>
                    </div>
                    <!-- Costo de envío -->
                    <div v-if="deliveryCost > 0" class="flex justify-between items-center text-gray-800">
                        <span class="text-lg font-medium">Costo de envío</span>
                        <span class="text-lg font-semibold">{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(deliveryCost) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-t border-gray-200 pt-3">
                        <span class="text-xl font-bold text-gray-900">Total</span>
                        <span class="text-2xl font-bold text-gray-900">{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(totalPrice) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Cookie Consent Banner -->
    <CookieConsent />

    <AlertModal
        :show="showError"
        type="error"
        title="No pudimos procesar tu pedido"
        :message="errorMessage"
        primary-text="Entendido"
        @primary="showError=false"
        @close="showError=false"
    />

    <AuthRequiredModal 
        :show="showAuthRequiredModal"
        :store="store"
        @close="showAuthRequiredModal = false"
    />
</template>