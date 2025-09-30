<script setup>
import ProductGallery from '@/Components/Product/ProductGallery.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    product: Object,
});

import { ref as vref } from 'vue';
const showVariantAlert = vref(false);
const selectedVariantId = ref(null);

const store = computed(() => props.product.store);

const selectedVariant = computed(() => {
    if (!selectedVariantId.value) return null;
    return props.product.variants.find(v => v.id === selectedVariantId.value);
});

// Promociones: prioridad a la promo global de la tienda
const storePromoActive = computed(() => {
    const percent = Number(store.value?.promo_discount_percent || 0);
    return Boolean(store.value?.promo_active) && percent > 0;
});
const productPromoActive = computed(() => {
    const percent = Number(props.product?.promo_discount_percent || 0);
    return Boolean(props.product?.promo_active) && percent > 0;
});
const effectivePromoPercent = computed(() => {
    if (storePromoActive.value) return Number(store.value.promo_discount_percent);
    if (productPromoActive.value) return Number(props.product.promo_discount_percent);
    return 0;
});

const basePrice = computed(() => {
    const variantPrice = selectedVariant.value?.price;
    return Number(variantPrice ?? props.product.price);
});

const displayPrice = computed(() => {
    const percent = effectivePromoPercent.value;
    if (percent > 0) {
        return Math.round((basePrice.value * (100 - percent)) / 100);
    }
    return basePrice.value;
});

const originalPrice = computed(() => {
    return effectivePromoPercent.value > 0 ? basePrice.value : null;
});

const displayStock = computed(() => {
    if (selectedVariant.value) {
        return selectedVariant.value.stock;
    }
    return props.product.variants.length > 0 ? 0 : props.product.quantity; 
});

const selectedQuantity = ref(1);

const increaseQuantity = () => {
    if (displayStock.value > 0 && selectedQuantity.value < displayStock.value) {
        selectedQuantity.value++;
    }
};

const decreaseQuantity = () => {
    if (selectedQuantity.value > 1) {
        selectedQuantity.value--;
    }
};

watch(selectedVariantId, () => {
    selectedQuantity.value = 1;
});

watch(selectedQuantity, (newQty) => {
    if (newQty > displayStock.value) {
        selectedQuantity.value = displayStock.value;
    }
    if (newQty < 1) {
        selectedQuantity.value = 1;
    }
});

const addToCart = () => {
    if (props.product.variants.length > 0 && !selectedVariantId.value) {
        showVariantAlert.value = true;
        return;
    }

    router.post(route('cart.store'), {
        product_id: props.product.id,
        product_variant_id: selectedVariantId.value,
        quantity: selectedQuantity.value,
    }, {
        preserveScroll: true, 
        onSuccess: () => {},
        onError: () => {}
    });
};

const specifications = computed(() => {
    if (!props.product.specifications) return [];
    try {
        return JSON.parse(props.product.specifications);
    } catch (e) {
        return [];
    }
});

// Utilidad para formatear moneda
const formatCOP = (value) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(value || 0));

// Precio mostrado por variante (considerando promoción efectiva)
const getVariantDisplayPrices = (variant) => {
    const b = Number((variant && variant.price != null) ? variant.price : props.product.price);
    const p = effectivePromoPercent.value;
    if (p > 0) {
        return { current: Math.round((b * (100 - p)) / 100), original: b };
    }
    return { current: b, original: null };
};
</script>

<template>
    <Head :title="product.name" />

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover">
                <h1 class="text-2xl font-bold text-gray-900">{{ store.name }}</h1>
            </div>
            
            <Link :href="(store.custom_domain ? ( (typeof window !== 'undefined' ? window.location.protocol : 'https:') + '//' + store.custom_domain + '/cart') : route('cart.index', { store: store.slug }))" class="relative flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                <span>🛒</span>
                <span v-if="$page.props.cart.count > 0" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                    {{ $page.props.cart.count }}
                </span>
            </Link>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-12">
        
        <div class="mb-8 flex justify-between items-center">
            <Link :href="route('catalogo.index', { store: store.slug })" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300">
                &larr; Volver al Catálogo
            </Link>
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
                <a v-if="store.phone" :href="`https://wa.me/${String(store.phone).replace(/[^0-9]/g,'')}`" target="_blank" class="text-gray-500 hover:text-green-600" title="WhatsApp">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74.97.99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64.15-.19.29-.74.94-.9 1.13-.17.19-.33.22-.62.07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45.13-.6.13-.13.29-.33.43-.5.15-.17.19-.29.29-.48.1-.19.05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0-.5.07-.76.36-.26.29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15.19 2 3.06 4.84 4.29.68.29 1.21.46 1.62.59.68.22 1.3.19 1.79.12.55-.08 1.7-.7 1.94-1.38.24-.68.24-1.26.17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                </a>
            </div>
        </div>
        <section class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-start">
            <div class="gallery">
                <ProductGallery 
                    :main-image-url="product.main_image_url"
                    :images="product.images"
                />
            </div>
            <div class="info flex flex-col space-y-4">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900">{{ product.name }}</h1>
                <div class="flex items-baseline gap-2">
                    <p class="text-2xl md:text-3xl font-extrabold text-blue-600">
                        {{ formatCOP(displayPrice) }}
                    </p>
                    <p v-if="originalPrice" class="text-base md:text-lg text-gray-400 line-through">
                        {{ formatCOP(originalPrice) }}
                    </p>
                </div>
                <p class="text-lg text-gray-600">{{ product.short_description }}</p>

                <div v-if="product.variants.length > 0" class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Opciones Disponibles:</h3>
                    <div class="space-y-2">
                        <div v-for="variant in product.variants" :key="variant.id">
                            <label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="{ 'border-blue-600 ring-2 ring-blue-300': selectedVariantId === variant.id }">
                                <input type="radio" :value="variant.id" v-model="selectedVariantId" class="form-radio text-blue-600">
                                <div class="ml-4 flex-grow">
                                    <span class="font-semibold text-gray-800">
                                        <span v-for="(value, key) in variant.options" :key="key" class="mr-2">
                                            <span class="font-normal">{{ key }}:</span> {{ value }}
                                        </span>
                                    </span>
                                    <span class="ml-2 text-blue-600">
                                        ({{ formatCOP(getVariantDisplayPrices(variant).current) }})
                                    </span>
                                    <span v-if="getVariantDisplayPrices(variant).original" class="ml-2 text-gray-400 line-through">
                                        {{ formatCOP(getVariantDisplayPrices(variant).original) }}
                                    </span>
                                </div>
                                <span class="text-sm font-medium" :class="{ 'text-red-600': variant.stock === 0, 'text-gray-600': variant.stock > 0 }">
                                    {{ variant.stock > 0 ? `${variant.stock} disponibles` : 'Agotado' }}
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="pt-4">
                    <div class="flex items-center space-x-4">
                        <label class="font-semibold">Cantidad:</label>
                        <div class="flex items-center border rounded-md">
                            <button @click="decreaseQuantity" :disabled="!selectedVariant && product.variants.length > 0" class="px-3 py-1 text-lg font-bold hover:bg-gray-200 rounded-l-md disabled:opacity-50">-</button>
                            <input type="number" v-model.number="selectedQuantity" class="w-16 text-center border-y-0 border-x" />
                            <button @click="increaseQuantity" :disabled="!selectedVariant && product.variants.length > 0" class="px-3 py-1 text-lg font-bold hover:bg-gray-200 rounded-r-md disabled:opacity-50">+</button>
                        </div>
                        <p v-if="selectedVariant || product.variants.length == 0" class="text-sm text-gray-600">({{ displayStock }} en stock)</p>
                    </div>

                    <button 
                        @click="addToCart"
                        :disabled="(product.variants.length > 0 && !selectedVariant) || displayStock === 0 || selectedQuantity > displayStock"
                        class="w-full mt-6 bg-blue-600 text-white font-bold py-3 px-6 rounded-lg text-center transition duration-300 disabled:bg-gray-400 enabled:hover:bg-blue-700">
                        {{ product.variants.length > 0 && !selectedVariant ? 'Selecciona una opción' : (displayStock === 0 ? 'Agotado' : 'Agregar al Carrito') }}
                    </button>
                </div>
                
                <div class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Especificaciones Técnicas</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li v-for="spec in specifications" :key="spec">{{ spec }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="long-description mt-12 md:mt-16 border-t pt-8">
            <h2 class="text-2xl font-bold mb-4">Descripción Detallada</h2>
            <div class="prose max-w-none text-gray-600">
                <p>{{ product.long_description }}</p> 
            </div>
        </section>

    </main>

    <AlertModal
        :show="showVariantAlert"
        type="error"
        title="Selecciona una opción"
        message="Para agregar al carrito, elegí una variante del producto."
        primary-text="Entendido"
        @primary="showVariantAlert=false"
        @close="showVariantAlert=false"
    />

    

    <footer class="bg-white mt-16 border-t">
        <div class="container mx-auto px-6 py-4 text-center text-gray-500">
            <p>&copy; 2025 {{ store.name }}</p>
        </div>
    </footer>
</template>