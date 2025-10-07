<script setup>
import ProductGallery from '@/Components/Product/ProductGallery.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    product: Object,
});

import { ref as vref } from 'vue';
const showVariantAlert = vref(false);
const selectedVariantIds = ref([]); // permitir seleccionar varias variantes

// UI amigable cuando hay muchas variantes
const isMobile = ref(false);
const updateIsMobile = () => { isMobile.value = window.innerWidth < 768; };
onMounted(() => { updateIsMobile(); window.addEventListener('resize', updateIsMobile); });
onBeforeUnmount(() => window.removeEventListener('resize', updateIsMobile));
const showAllVariants = ref(false);
const showVariantsModal = ref(false);
// Siempre mostrar máximo 3 en ambas versiones
const visibleCount = computed(() => 3);
const visibleVariants = computed(() => showAllVariants.value ? props.product.variants : props.product.variants.slice(0, visibleCount.value));
const hiddenVariantsCount = computed(() => Math.max(0, (props.product.variants?.length || 0) - visibleCount.value));

const store = computed(() => props.product.store);
const showSocialFab = ref(false);
const socialLinks = computed(() => {
    const links = [];
    const s = store.value || {};
    const fb = (s.facebook_url ?? '').toString().trim();
    const ig = (s.instagram_url ?? '').toString().trim();
    const tt = (s.tiktok_url ?? s.tiktok ?? s.tik_tok_url ?? '').toString().trim();
    const phone = (s.phone ?? '').toString().replace(/[^0-9]/g, '');
    if (fb) links.push({ key: 'fb', href: fb });
    if (ig) links.push({ key: 'ig', href: ig });
    if (tt) links.push({ key: 'tt', href: tt });
    if (phone) links.push({ key: 'wa', href: `https://wa.me/${phone}` });
    return links;
});

const hasAnySocial = computed(() => socialLinks.value.length > 0);

// Si hay exactamente una seleccionada, la tratamos como selección única
const selectedVariant = computed(() => {
    const ids = selectedVariantIds.value || [];
    if (ids.length !== 1) return null;
    return props.product.variants.find(v => v.id === ids[0]);
});

const isInventoryTracked = computed(() => props.product.track_inventory !== false);

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
    if (!isInventoryTracked.value) return Number.POSITIVE_INFINITY;
    return props.product.variants.length > 0 ? 0 : props.product.quantity; 
});

const selectedQuantity = ref(1);

const increaseQuantity = () => {
    if (!isInventoryTracked.value && props.product.variants.length === 0) {
        selectedQuantity.value++;
        return;
    }
    if (displayStock.value > 0 && selectedQuantity.value < displayStock.value) {
        selectedQuantity.value++;
    }
};

const decreaseQuantity = () => {
    if (selectedQuantity.value > 1) {
        selectedQuantity.value--;
    }
};

watch(selectedVariantIds, () => {
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
    // Si hay variantes, exigir al menos una selección
    if (props.product.variants.length > 0 && selectedVariantIds.value.length === 0) {
        showVariantAlert.value = true;
        return;
    }

    // Si hay múltiples seleccionadas, agregamos cada una con cantidad 1
    const ids = props.product.variants.length > 0 ? selectedVariantIds.value : [null];
    const qty = (ids.length === 1) ? selectedQuantity.value : 1;

    ids.forEach((variantId) => {
        router.post(route('cart.store'), {
            product_id: props.product.id,
            product_variant_id: variantId,
            quantity: qty,
        }, {
            preserveScroll: true,
        });
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
    <Head :title="product.name">
        <template #default>
            <link v-if="store.logo_url" rel="icon" type="image/png" :href="store.logo_url">
        </template>
    </Head>

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover">
                <h1 class="truncate text-lg sm:text-2xl font-bold text-gray-900">{{ store.name }}</h1>
            </div>
            <div class="hidden md:flex items-center space-x-4">
            </div>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-12">
        
        <div class="mb-8 flex justify-between items-center">
            <Link :href="route('catalogo.index', { store: store.slug })" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300">
                &larr; Volver al Catálogo
            </Link>
            <div class="hidden md:flex items-center space-x-4">
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
				<div class="flex items-center gap-2">
					<p class="text-2xl md:text-3xl font-extrabold text-gray-900">
						{{ formatCOP(displayPrice) }}
					</p>
					<span v-if="effectivePromoPercent > 0" class="inline-flex items-center rounded bg-red-600 text-white font-bold px-2 py-1 text-xs md:text-sm">-{{ effectivePromoPercent }}%</span>
				</div>
				<p v-if="originalPrice" class="text-sm md:text-base text-gray-400 line-through">
					{{ formatCOP(originalPrice) }}
				</p>
                <p class="text-lg text-gray-600">{{ product.short_description }}</p>

                <div v-if="product.variants.length > 0" class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Opciones Disponibles:</h3>
                    <div class="space-y-2">
                        <div v-for="variant in visibleVariants" :key="variant.id">
							<label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="{ 'border-blue-600 ring-2 ring-blue-300': selectedVariantIds.includes(variant.id) }">
                                <input type="checkbox" :value="variant.id" v-model="selectedVariantIds" class="form-checkbox text-blue-600">
                                <div class="ml-4 flex-grow">
                                    <span class="font-semibold text-gray-800">
                                        <span v-for="(value, key) in variant.options" :key="key" class="mr-2">
                                            <span class="font-normal">{{ key }}:</span> {{ value }}
                                        </span>
                                    </span>
									<span class="ml-2 text-gray-900 font-semibold">({{ formatCOP(getVariantDisplayPrices(variant).current) }})</span>
									<span v-if="effectivePromoPercent > 0" class="ml-2 inline-flex items-center rounded bg-red-600 text-white font-bold px-1.5 py-0.5 text-xs">-{{ effectivePromoPercent }}%</span>
                                    <span v-if="getVariantDisplayPrices(variant).original" class="ml-2 text-gray-400 line-through">
                                        {{ formatCOP(getVariantDisplayPrices(variant).original) }}
                                    </span>
                                </div>
                                <span v-if="isInventoryTracked" class="text-sm font-medium" :class="{ 'text-red-600': variant.stock === 0, 'text-gray-600': variant.stock > 0 }">
                                    {{ variant.stock > 0 ? `${variant.stock} disponibles` : 'Agotado' }}
                                </span>
                            </label>
                        </div>
                        <button v-if="hiddenVariantsCount > 0" type="button" class="text-sm text-blue-600 hover:text-blue-800" @click="showVariantsModal = true">Ver +{{ hiddenVariantsCount }} opciones</button>
                    </div>
                </div>
                
                <div class="pt-4">
            <div class="flex items-center space-x-4">
                        <label class="font-semibold">Cantidad:</label>
                        <div class="flex items-center border rounded-md">
                            <button @click="decreaseQuantity" :disabled="(product.variants.length > 0 && selectedVariantIds.length !== 1)" class="px-3 py-1 text-lg font-bold hover:bg-gray-200 rounded-l-md disabled:opacity-50">-</button>
                            <input type="number" v-model.number="selectedQuantity" :disabled="(product.variants.length > 0 && selectedVariantIds.length !== 1)" class="w-16 text-center border-y-0 border-x disabled:bg-gray-100" />
                            <button @click="increaseQuantity" :disabled="(product.variants.length > 0 && selectedVariantIds.length !== 1)" class="px-3 py-1 text-lg font-bold hover:bg-gray-200 rounded-r-md disabled:opacity-50">+</button>
                        </div>
                <p v-if="(selectedVariant || product.variants.length == 0) && isInventoryTracked && selectedVariantIds.length <= 1" class="text-sm text-gray-600">({{ isFinite(displayStock) ? displayStock : '∞' }} en stock)</p>
                    </div>

                    <button 
                        @click="addToCart"
                        :disabled="(product.variants.length > 0 && selectedVariantIds.length === 0) || (isInventoryTracked && selectedVariantIds.length === 1 && (displayStock === 0 || selectedQuantity > displayStock))"
                        class="w-full mt-6 bg-blue-600 text-white font-bold py-3 px-6 rounded-lg text-center transition duration-300 disabled:bg-gray-400 enabled:hover:bg-blue-700">
                        {{ product.variants.length > 0 && selectedVariantIds.length === 0 ? 'Selecciona al menos una opción' : (isInventoryTracked && selectedVariantIds.length === 1 ? (displayStock === 0 ? 'Agotado' : 'Agregar al Carrito') : 'Agregar al Carrito') }}
                    </button>
                </div>
                
                <div class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Especificaciones</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li v-for="spec in specifications" :key="spec">{{ spec }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="long-description mt-12 md:mt-16 border-t pt-8">
            <h2 class="text-2xl font-bold mb-4">Descripción</h2>
            <div class="prose max-w-none text-gray-600">
                <p>{{ product.long_description }}</p> 
            </div>
        </section>

    </main>

    <!-- Modal de variantes (desktop) -->
    <div v-if="showVariantsModal" class="fixed inset-0 bg-black/40 z-[1000] flex items-center justify-center p-4" @click.self="showVariantsModal = false">
        <div class="bg-white rounded-lg w-full max-w-3xl max-h-[80vh] overflow-hidden shadow-xl" @click.stop>
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <h4 class="font-semibold">Selecciona opciones</h4>
                <button class="w-8 h-8 rounded-full bg-gray-900 text-white flex items-center justify-center" @click="showVariantsModal = false">×</button>
            </div>
            <div class="p-4 overflow-y-auto pb-24" style="max-height: 65vh;">
                <div class="space-y-2">
                    <div v-for="variant in product.variants" :key="`modal-${variant.id}`">
							<label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="{ 'border-blue-600 ring-2 ring-blue-300': selectedVariantIds.includes(variant.id) }">
                            <input type="checkbox" :value="variant.id" v-model="selectedVariantIds" class="form-checkbox text-blue-600">
                            <div class="ml-4 flex-grow">
                                <span class="font-semibold text-gray-800">
                                    <span v-for="(value, key) in variant.options" :key="key" class="mr-2">
                                        <span class="font-normal">{{ key }}:</span> {{ value }}
                                    </span>
                                </span>
									<span class="ml-2 text-gray-900 font-semibold">({{ formatCOP(getVariantDisplayPrices(variant).current) }})</span>
									<span v-if="effectivePromoPercent > 0" class="ml-2 inline-flex items-center rounded bg-red-600 text-white font-bold px-1.5 py-0.5 text-xs">-{{ effectivePromoPercent }}%</span>
									<span v-if="getVariantDisplayPrices(variant).original" class="ml-2 text-gray-400 line-through">{{ formatCOP(getVariantDisplayPrices(variant).original) }}</span>
                            </div>
                            <span v-if="isInventoryTracked" class="text-sm font-medium" :class="{ 'text-red-600': variant.stock === 0, 'text-gray-600': variant.stock > 0 }">
                                {{ variant.stock > 0 ? `${variant.stock} disponibles` : 'Agotado' }}
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 px-4 py-3 border-t sticky bottom-0 bg-white">
                <button class="px-4 py-2 rounded bg-gray-100 hover:bg-gray-200" @click="showVariantsModal = false">Cerrar</button>
                <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" @click="showVariantsModal = false">Aplicar</button>
            </div>
        </div>
    </div>

    <!-- FAB Social (móvil y desktop) -->
    <div v-if="hasAnySocial" class="fixed bottom-6 left-6 z-50">
        <div class="relative">
            <transition name="fade">
                <div v-if="showSocialFab" class="absolute right-0 bottom-0 flex flex-col items-end gap-3 -translate-y-14 z-10">
                    <a v-for="link in socialLinks" :key="link.key" :href="link.href" target="_blank" class="w-11 h-11 rounded-full bg-white/70 backdrop-blur ring-1 ring-blue-500/50 flex items-center justify-center shadow-2xl active:scale-95">
                        <svg v-if="link.key === 'fb'" class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
                        <svg v-if="link.key === 'ig'" class="w-5 h-5 text-pink-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.012-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.08 2.525c.636-.247 1.363-.416 2.427-.465C9.53 2.013 9.884 2 12.315 2zm-1.161 1.545a1.12 1.12 0 10-1.584 1.584 1.12 1.12 0 001.584-1.584zm-3.097 3.569a3.468 3.468 0 106.937 0 3.468 3.468 0 00-6.937 0z" clip-rule="evenodd" /><path d="M12 6.166a5.834 5.834 0 100 11.668 5.834 5.834 0 000-11.668zm0 1.545a4.289 4.289 0 110 8.578 4.289 4.289 0 010-8.578z" /></svg>
                        <svg v-if="link.key === 'tt'" class="w-5 h-5 text-black" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.01-1.58-.31-3.15-.82-4.7-.52-1.56-1.23-3.04-2.1-4.42a.1.1 0 00-.2-.04c-.02.13-.03.26-.05.39v7.24a.26.26 0 00.27.27c.82.04 1.63.16 2.42.37.04.83.16 1.66.36 2.47.19.82.49 1.6.86 2.33.36.73.81 1.41 1.32 2.02-.17 .1-.34 .19-.51 .28a4.26 4.26 0 01-1.93 .52c-1.37 .04-2.73-.06-4.1-.23a9.8 9.8 0 01-3.49-1.26c-.96-.54-1.8-1.23-2.52-2.03-.72-.8-1.3-1.7-1.77-2.69-.47-.99-.8-2.06-1.02-3.13a.15 .15 0 01.04-.15 .24 .24 0 01.2-.09c.64-.02 1.28-.04 1.92-.05 .1 0 .19-.01 .28-.01 .07 .01 .13 .02 .2 .04 .19 .04 .38 .09 .57 .14a5.2 5.2 0 005.02-5.22v-.02a.23 .23 0 00-.23-.23 .2 .2 0 00-.2-.02c-.83-.06-1.66-.13-2.49-.22-.05-.01-.1-.01-.15-.02-1.12-.13-2.25-.26-3.37-.44a.2 .2 0 01-.16-.24 .22 .22 0 01.23-.18c.41-.06 .82-.12 1.23-.18C9.9 .01 11.21 0 12.525 .02z"/></svg>
                        <svg v-if="link.key === 'wa'" class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74 .97 .99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64 .15-.19 .29-.74 .94-.9 1.13-.17 .19-.33 .22-.62 .07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45 .13-.6 .13-.13 .29-.33 .43-.5 .15-.17 .19-.29 .29-.48 .1-.19 .05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0 -.5 .07-.76 .36-.26 .29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15 .19 2 3.06 4.84 4.29 .68 .29 1.21 .46 1.62 .59 .68 .22 1.3 .19 1.79 .12 .55-.08 1.7-.7 1.94-1.38 .24-.68 .24-1.26 .17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                    </a>
                </div>
            </transition>
            <button @click="showSocialFab = !showSocialFab" class="w-12 h-12 rounded-full bg-blue-600/70 backdrop-blur ring-1 ring-blue-500/50 text-white flex items-center justify-center shadow-2xl active:scale-95 transition-transform duration-300" :class="{ 'scale-95': showSocialFab }">
                <svg v-if="!showSocialFab" class="w-6 h-6 transition-opacity duration-200" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2c-3.18-.35-6.2-1.63-8.82-3.68a19.86 19.86 0 0 1-6.24-6.24C2.7 9.38 1.42 6.36 1.07 3.18A2 2 0 0 1 3.06 1h3a2 2 0 0 1 2 1.72c.09.74.25 1.46.46 2.16a2 2 0 0 1-.45 2.06L7.5 8.5a16 16 0 0 0 8 8l1.56-1.57a2 2 0 0 1 2.06-.45c.7.21 1.42.37 2.16.46A2 2 0 0 1 22 16.92z"/>
                </svg>
                <svg v-else class="w-6 h-6 transition-opacity duration-200" viewBox="0 0 24 24" fill="currentColor"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>
    </div>

    <!-- FAB Carrito (móvil y desktop) -->
    <div class="fixed bottom-6 right-6 z-50">
        <Link :href="route('cart.index', { store: store.slug })" class="relative w-12 h-12 rounded-full bg-blue-600/70 backdrop-blur ring-1 ring-blue-500/50 text-white flex items-center justify-center shadow-2xl active:scale-95">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
            <span v-if="$page.props.cart.count > 0" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                {{ $page.props.cart.count }}
            </span>
        </Link>
    </div>
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