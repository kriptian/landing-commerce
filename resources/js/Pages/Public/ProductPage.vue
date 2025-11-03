<script setup>
import ProductGallery from '@/Components/Product/ProductGallery.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    product: Object,
    store: Object,
    related: Array,
    suggested: Array,
});

import { ref as vref } from 'vue';
const showVariantAlert = vref(false);
const toast = useToast();

// Selección por atributos (Color, Talla, etc.)
const optionKeys = computed(() => {
    const keys = new Set();
    (props.product.variants || []).forEach(v => {
        const opts = v?.options || {};
        Object.keys(opts).forEach(k => keys.add(k));
    });
    return Array.from(keys);
});

const optionValuesByKey = computed(() => {
    const map = {};
    optionKeys.value.forEach(k => { map[k] = []; });
    (props.product.variants || []).forEach(v => {
        const opts = v?.options || {};
        optionKeys.value.forEach(k => {
            const val = opts[k];
            if (val != null && !map[k].includes(val)) map[k].push(val);
        });
    });
    return map;
});

const selectedOptions = ref({});
function initSelectedOptions() {
    const obj = {};
    optionKeys.value.forEach(k => { obj[k] = null; });
    selectedOptions.value = obj;
}
initSelectedOptions();

function selectOption(key, value) {
    selectedOptions.value[key] = (selectedOptions.value[key] === value) ? null : value;
}

function isValueSelectable(key, value) {
    // Permitir seleccionar valores aunque no haya stock; la validación final la hace el botón y el carrito
    return (props.product.variants || []).some(v => {
        const opts = v?.options || {};
        if (opts[key] !== value) return false;
        for (const k of optionKeys.value) {
            const sel = selectedOptions.value[k];
            if (k === key) continue;
            if (sel != null && opts[k] !== sel) return false;
        }
        return true;
    });
}

// Dependencia de atributos (opcional). Si está activada, mostramos solo valores que
// existen en combinación con las selecciones actuales, independientemente del stock.
const dependentAttributes = true; // habilitado por defecto; podemos parametrizar más adelante

function hasCombination(key, value) {
    return (props.product.variants || []).some(v => {
        const opts = v?.options || {};
        if (opts[key] !== value) return false;
        for (const k of optionKeys.value) {
            const sel = selectedOptions.value[k];
            if (k === key) continue;
            if (sel != null && opts[k] !== sel) return false;
        }
        return true;
    });
}

function getValuesForKey(key) {
    const values = optionValuesByKey.value[key] || [];
    if (!dependentAttributes) return values;
    // Si no hay ninguna selección en otras claves, mostramos todo
    const hasAnyOtherSelection = optionKeys.value.some(k => k !== key && selectedOptions.value[k] != null);
    if (!hasAnyOtherSelection) return values;
    return values.filter(v => hasCombination(key, v));
}

// Si la dependencia está activa, al cambiar una selección invalidamos las que ya no aplican
watch(selectedOptions, (now) => {
    if (!dependentAttributes) return;
    for (const key of optionKeys.value) {
        const val = now[key];
        if (val == null) continue;
        if (!hasCombination(key, val)) {
            selectedOptions.value[key] = null;
        }
    }
}, { deep: true });

// Calcular stock resultante si se fija un valor para una clave con la selección actual
function computeStockForValue(key, value) {
    if (!isInventoryTracked.value) return Number.POSITIVE_INFINITY;
    let total = 0;
    (props.product.variants || []).forEach(v => {
        const opts = v?.options || {};
        if (opts[key] !== value) return;
        // Coincidencia con el resto de selecciones
        for (const k of optionKeys.value) {
            const sel = selectedOptions.value[k];
            if (k === key) continue;
            if (sel != null && opts[k] !== sel) return; // no coincide
        }
        const vs = Number(v.stock ?? 0);
        total += vs > 0 ? vs : Number(props.product.quantity || 0);
    });
    return total;
}

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
// URL pública del producto para compartir (compatible con OG/preview)
const productUrl = computed(() => route('catalogo.show', { store: (store.value || {}).slug, product: props.product.id }));

const shareViaWhatsApp = () => {
    try {
        const text = `${props.product.name} — ${productUrl.value}`;
        const encoded = encodeURIComponent(text);
        const waUrl = `https://wa.me/?text=${encoded}`;
        if (typeof window !== 'undefined') window.open(waUrl, '_blank');
    } catch (e) {}
};

const copyProductLink = async () => {
    try {
        await navigator.clipboard.writeText(productUrl.value);
        try { toast.success('Enlace copiado'); } catch (e) {}
    } catch (e) {}
};

const shareProduct = async () => {
    const title = `${props.product.name} · ${store.value?.name ?? ''}`.trim();
    const text = props.product.short_description || 'Mirá este producto';
    const url = productUrl.value;
    if (typeof navigator !== 'undefined' && navigator.share) {
        try {
            await navigator.share({ title, text, url });
            return;
        } catch (err) {
            // Usuario canceló o no se pudo abrir el share sheet: caemos a WhatsApp
        }
    }
    // Fallback: abrir WhatsApp con vista previa y, si falla, copiar
    try { shareViaWhatsApp(); } catch (e) { copyProductLink(); }
};
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
const allKeysSelected = computed(() => optionKeys.value.every(k => selectedOptions.value[k] != null));
const selectedVariant = computed(() => {
    if (!allKeysSelected.value) return null;
    return (props.product.variants || []).find(v => {
        const opts = v?.options || {};
        return optionKeys.value.every(k => opts[k] === selectedOptions.value[k]);
    }) || null;
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
    // Si hay una variante seleccionada, con inventario INACTIVO usamos retail de variante o caemos al precio principal del producto
    if (selectedVariant.value) {
        if (!isInventoryTracked.value) {
            const vr = selectedVariant.value.retail_price;
            return Number((vr !== null && vr !== '') ? vr : props.product.price);
        }
        // Con inventario activo, mantenemos el flujo actual (precio base de variante)
        return Number(selectedVariant.value.price ?? props.product.price);
    }
    return Number(props.product.price);
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
    // Cuando NO se maneja inventario, no limitamos cantidad
    if (!isInventoryTracked.value) return Number.POSITIVE_INFINITY;
    // Con inventario activo: si hay variante seleccionada, usar su stock (o caer al del producto)
    if (selectedVariant.value) {
        const sv = Number(selectedVariant.value.stock);
        if (!Number.isNaN(sv) && sv > 0) return sv;
        return Number(props.product.quantity || 0);
    }
    // Sin variante seleccionada: usar inventario total del producto
    return Number(props.product.quantity || 0);
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

watch(selectedVariant, () => { selectedQuantity.value = 1; });

watch(selectedQuantity, (newQty) => {
    if (newQty > displayStock.value) {
        selectedQuantity.value = displayStock.value;
    }
    if (newQty < 1) {
        selectedQuantity.value = 1;
    }
});

const addToCart = () => {
    // Exigir selección completa cuando hay variantes con múltiples atributos
    if (props.product.variants.length > 0 && !selectedVariant.value) {
        showVariantAlert.value = true;
        return;
    }

    const variantId = props.product.variants.length > 0 ? selectedVariant.value?.id ?? null : null;
    const qty = selectedQuantity.value;

    router.post(route('cart.store'), {
        product_id: props.product.id,
        product_variant_id: variantId,
        quantity: qty,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            try { toast.success('Producto agregado al carrito'); } catch (e) {}
            // Limpiar selección y cantidad para permitir agregar otra variante
            initSelectedOptions();
            selectedQuantity.value = 1;
        }
    });
};

const buyNow = () => {
    // Exigir selección completa cuando hay variantes con múltiples atributos
    if (props.product.variants.length > 0 && !selectedVariant.value) {
        showVariantAlert.value = true;
        return;
    }

    const variantId = props.product.variants.length > 0 ? selectedVariant.value?.id ?? null : null;
    const qty = selectedQuantity.value;

    router.post(route('cart.store'), {
        product_id: props.product.id,
        product_variant_id: variantId,
        quantity: qty,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Redirigir directamente al checkout después de agregar al carrito
            router.visit(route('checkout.index', { store: store.value.slug }), {
                preserveScroll: false,
            });
        },
        onError: () => {
            try { toast.error('Error al agregar el producto'); } catch (e) {}
        }
    });
};

// Badge de stock: 'Agotado' o '¡Pocas unidades!'
const stockBadge = computed(() => {
    if (!isInventoryTracked.value) return null;
    const qty = Number(displayStock.value || 0);
    const alert = Number(props.product?.alert || 0);
    if (qty <= 0) return 'Agotado';
    if (alert > 0 && qty <= alert) return '¡Pocas unidades!';
    return null;
});

const stockBadgeClass = computed(() => {
    const b = stockBadge.value;
    if (!b) return null;
    return b === 'Agotado' ? 'bg-red-600' : 'bg-yellow-500';
});

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
    // Con inventario INACTIVO: usar retail de variante o caer al precio principal del producto
    if (!isInventoryTracked.value) {
        const base = (variant && variant.retail_price != null && variant.retail_price !== '')
            ? variant.retail_price
            : props.product.price;
        const b = Number(base);
        const p = effectivePromoPercent.value;
        if (p > 0) return { current: Math.round((b * (100 - p)) / 100), original: b };
        return { current: b, original: null };
    }

    // Con inventario ACTIVO: mantenemos prioridad existente
    let base = null;
    if (variant && variant.retail_price != null && variant.retail_price !== '') {
        base = variant.retail_price;
    } else if (props.product.retail_price != null) {
        base = props.product.retail_price;
    } else if (variant && variant.price != null) {
        base = variant.price;
    } else {
        base = props.product.price;
    }
    const b = Number(base);
    const p = effectivePromoPercent.value;
    if (p > 0) return { current: Math.round((b * (100 - p)) / 100), original: b };
    return { current: b, original: null };
};
</script>

<template>
    <Head :title="product.name">
        <template #default>
        </template>
    </Head>

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="container mx-auto px-6 py-4 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <Link :href="route('catalogo.index', { store: store.slug })" class="shrink-0" title="Ir al catálogo">
                    <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover">
                </Link>
                <h1 class="truncate text-lg sm:text-2xl font-bold text-gray-900">{{ store.name }}</h1>
            </div>
            <div class="hidden md:flex items-center space-x-4">
            </div>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-12">
        
        
        <section class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-start">
            <div class="gallery relative">
                <button
                    type="button"
                    @click="shareProduct"
                    aria-label="Compartir producto"
                    v-if="isMobile"
                    class="absolute top-2 right-2 z-10 w-10 h-10 rounded-full bg-white/90 ring-1 ring-gray-300 text-gray-700 hover:bg-white hover:text-gray-900 shadow flex items-center justify-center md:hidden"
                    title="Compartir"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="18" cy="5" r="2.5"></circle>
                        <circle cx="6" cy="12" r="2.5"></circle>
                        <circle cx="18" cy="19" r="2.5"></circle>
                        <path d="M8.6 11.3 L15.4 6.7"></path>
                        <path d="M8.6 12.7 L15.4 17.3"></path>
                    </svg>
                </button>
                <ProductGallery 
                    :main-image-url="product.main_image_url"
                    :images="product.images"
                />
            </div>
            <div class="info flex flex-col space-y-4">
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 flex-1">{{ product.name }}</h1>
                    <!-- Badge en móvil junto al título para mejor jerarquía visual -->
                    <span v-if="stockBadge" class="md:hidden inline-flex items-center rounded text-white font-bold px-2 py-1 text-xs" :class="stockBadgeClass">{{ stockBadge }}</span>
                </div>
				<div class="flex items-center gap-2">
					<p class="text-2xl md:text-3xl font-extrabold text-gray-900">
						{{ formatCOP(displayPrice) }}
					</p>
					<span v-if="effectivePromoPercent > 0" class="inline-flex items-center rounded bg-red-600 text-white font-bold px-2 py-1 text-xs md:text-sm">-{{ effectivePromoPercent }}%</span>
				</div>
				<p v-if="originalPrice" class="text-sm md:text-base text-gray-400 line-through">
					{{ formatCOP(originalPrice) }}
				</p>
                <p v-if="product.short_description" class="text-lg text-gray-600">{{ product.short_description }}</p>

                <div v-if="product.variants.length > 0" class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Opciones Disponibles:</h3>
                    <div class="space-y-4">
                        <div v-for="key in optionKeys" :key="`opt-${key}`">
                            <div class="text-sm text-gray-700 font-medium mb-1">{{ key }}</div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="value in getValuesForKey(key)"
                                    :key="`${key}:${value}`"
                                    type="button"
                                    class="px-3 py-1 rounded border"
                                    :class="{
                                        'bg-blue-600 text-white border-blue-700': selectedOptions[key] === value,
                                        'bg-white text-gray-800 border-gray-300': selectedOptions[key] !== value,
                                        'opacity-50 cursor-not-allowed': isInventoryTracked && (computeStockForValue(key, value) === 0)
                                    }"
                                    :disabled="isInventoryTracked && (computeStockForValue(key, value) === 0)"
                                    @click="selectOption(key, value)"
                                >
                                    {{ value }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="pt-4">
            <div class="flex items-center space-x-4">
                        <!-- Badge solo en desktop en esta fila -->
                        <span v-if="stockBadge" class="hidden md:inline-flex items-center rounded text-white font-bold px-2 py-1 text-xs md:text-sm" :class="stockBadgeClass">{{ stockBadge }}</span>
                        <label class="font-semibold">Cantidad:</label>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="decreaseQuantity" class="w-9 h-9 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200">−</button>
                            <input type="number" v-model.number="selectedQuantity" :min="1" :max="isFinite(displayStock) ? displayStock : undefined" class="w-12 h-9 text-center border rounded-md" />
                            <button type="button" @click="increaseQuantity" class="w-9 h-9 rounded-full bg-gray-900 text-white hover:bg-gray-800">＋</button>
                        </div>
                <p v-if="(selectedVariant || product.variants.length == 0) && isInventoryTracked" class="ml-2 text-xs md:text-sm text-gray-600 whitespace-nowrap shrink-0">{{ isFinite(displayStock) ? displayStock : '∞' }} en stock</p>
                    </div>

                    <button 
                        @click="addToCart"
                        :disabled="(product.variants.length > 0 && !selectedVariant) || (isInventoryTracked && (displayStock === 0 || selectedQuantity > displayStock))"
                        class="w-full mt-6 bg-blue-600/30 backdrop-blur-sm text-blue-700 font-bold py-3 px-6 rounded-lg text-center transition duration-300 disabled:bg-gray-300 disabled:text-gray-500 enabled:hover:bg-blue-600/40 border-2 border-blue-600/50">
                        {{ product.variants.length > 0 && !selectedVariant ? 'Selecciona opciones' : (isInventoryTracked ? (displayStock === 0 ? 'Agotado' : 'Agregar al Carrito') : 'Agregar al Carrito') }}
                    </button>
                    
                    <button 
                        @click="buyNow"
                        :disabled="(product.variants.length > 0 && !selectedVariant) || (isInventoryTracked && (displayStock === 0 || selectedQuantity > displayStock))"
                        class="w-full mt-3 bg-blue-600 text-white font-bold py-3 px-6 rounded-lg text-center transition duration-300 disabled:bg-gray-400 enabled:hover:bg-blue-700 buy-now-button">
                        Comprar Ahora
                    </button>
                </div>
                
                <div v-if="specifications.length > 0" class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Especificaciones</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li v-for="spec in specifications" :key="spec">{{ spec }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <section v-if="product.long_description" class="long-description mt-12 md:mt-16 border-t pt-8">
            <h2 class="text-2xl font-bold mb-4">Descripción</h2>
            <div class="prose max-w-none text-gray-600">
                <p>{{ product.long_description }}</p> 
            </div>
        </section>

        <!-- Productos relacionados (carrusel horizontal) -->
        <section v-if="Array.isArray(related) && related.length" class="mt-12 md:mt-16 border-t pt-8">
            <h2 class="text-2xl font-bold mb-4">Productos relacionados</h2>
            <div class="flex gap-3 sm:gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-2">
                <Link v-for="rp in related" :key="rp.id" :href="route('catalogo.show', { store: store.slug, product: rp.id })" class="group block border rounded-xl shadow-sm overflow-hidden bg-white hover:shadow-md transition shrink-0 snap-start min-w-[160px] sm:min-w-[190px] md:min-w-[220px]">
                    <div class="relative">
                        <img v-if="rp.main_image_url" :src="rp.main_image_url" alt="Imagen del producto" class="w-full h-36 sm:h-44 md:h-48 object-cover transform group-hover:scale-105 transition duration-300">
                        <span v-if="(rp.track_inventory !== false) && Number(rp.quantity || 0) <= 0" class="absolute top-3 left-3 bg-red-600 text-white text-[10px] font-semibold px-2 py-1 rounded">Agotado</span>
                    </div>
                    <div class="p-3 sm:p-4 flex flex-col gap-2">
                        <h3 class="text-sm sm:text-base font-semibold text-gray-900 line-clamp-2">{{ rp.name }}</h3>
                        <div class="flex items-center gap-2">
                            <p class="text-sm sm:text-lg text-gray-900 font-extrabold">
                                {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
                                    rp.promo_active && Number(rp.promo_discount_percent||0) > 0 ? Math.round(Number(rp.price) * (100 - Number(rp.promo_discount_percent)) / 100) : Number(rp.price)
                                ) }}
                            </p>
                            <span v-if="rp.promo_active && Number(rp.promo_discount_percent||0) > 0" class="inline-flex items-center rounded bg-red-600 text-white font-bold px-1.5 py-0.5 text-[10px] sm:text-xs">
                                -{{ rp.promo_discount_percent }}%
                            </span>
                        </div>
                        <p v-if="rp.promo_active && Number(rp.promo_discount_percent||0) > 0" class="text-[11px] sm:text-xs text-gray-400 line-through">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(rp.price)) }}
                        </p>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Te puede interesar (carrusel horizontal) -->
        <section v-if="Array.isArray(suggested) && suggested.length" class="mt-12 md:mt-16 border-t pt-8">
            <h2 class="text-2xl font-bold mb-4">Te puede interesar</h2>
            <div class="flex gap-3 sm:gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-2">
                <Link v-for="sp in suggested" :key="sp.id" :href="route('catalogo.show', { store: store.slug, product: sp.id })" class="group block border rounded-xl shadow-sm overflow-hidden bg-white hover:shadow-md transition shrink-0 snap-start min-w-[160px] sm:min-w-[190px] md:min-w-[220px]">
                    <div class="relative">
                        <img v-if="sp.main_image_url" :src="sp.main_image_url" alt="Imagen del producto" class="w-full h-36 sm:h-44 md:h-48 object-cover transform group-hover:scale-105 transition duration-300">
                        <span v-if="(sp.track_inventory !== false) && Number(sp.quantity || 0) <= 0" class="absolute top-3 left-3 bg-red-600 text-white text-[10px] font-semibold px-2 py-1 rounded">Agotado</span>
                    </div>
                    <div class="p-3 sm:p-4 flex flex-col gap-2">
                        <h3 class="text-sm sm:text-base font-semibold text-gray-900 line-clamp-2">{{ sp.name }}</h3>
                        <div class="flex items-center gap-2">
                            <p class="text-sm sm:text-lg text-gray-900 font-extrabold">
                                {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
                                    sp.promo_active && Number(sp.promo_discount_percent||0) > 0 ? Math.round(Number(sp.price) * (100 - Number(sp.promo_discount_percent)) / 100) : Number(sp.price)
                                ) }}
                            </p>
                            <span v-if="sp.promo_active && Number(sp.promo_discount_percent||0) > 0" class="inline-flex items-center rounded bg-red-600 text-white font-bold px-1.5 py-0.5 text-[10px] sm:text-xs">
                                -{{ sp.promo_discount_percent }}%
                            </span>
                        </div>
                        <p v-if="sp.promo_active && Number(sp.promo_discount_percent||0) > 0" class="text-[11px] sm:text-xs text-gray-400 line-through">
                            {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(sp.price)) }}
                        </p>
                    </div>
                </Link>
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
                            <label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="{ 'border-blue-600 ring-2 ring-blue-300': selectedVariantId === variant.id }">
                            <input type="radio" :value="variant.id" v-model="selectedVariantId" name="variant-select-modal" class="form-radio text-blue-600">
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

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

/* Animación de salto vertical y pulso para el botón "Comprar Ahora" */
.buy-now-button:not(:disabled) {
    animation: bounce-pulse 2s ease-in-out infinite;
}

@keyframes bounce-pulse {
    0%, 100% {
        transform: translateY(0) scale(1);
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7);
    }
    25% {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 0 0 8px rgba(37, 99, 235, 0);
    }
    50% {
        transform: translateY(0) scale(1.02);
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
    }
    75% {
        transform: translateY(-2px) scale(1.01);
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
    }
}

.buy-now-button:not(:disabled):hover {
    animation: none;
    transform: translateY(0) scale(1.05);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.3);
}
</style>