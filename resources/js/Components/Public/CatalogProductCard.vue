<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
    store: { type: Object, required: true },
    template: { type: String, default: 'default' },
    buying: { type: Boolean, default: false },
});

defineEmits(['buy']);

const promotionPercent = computed(() => {
    if (props.store.promo_active && Number(props.store.promo_discount_percent) > 0) {
        return Number(props.store.promo_discount_percent);
    }

    return props.product.promo_active ? Number(props.product.promo_discount_percent || 0) : 0;
});

const salePrice = computed(() => Math.round(Number(props.product.price || 0) * (100 - promotionPercent.value) / 100));
const formatCurrency = value => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format(value);

const outOfStock = computed(() => {
    if (props.product.track_inventory === false) return false;
    if (props.product.has_variants) {
        return !(props.product.variants || []).some(variant => Number(variant.stock || 0) > 0);
    }

    return Number(props.product.quantity || 0) <= 0;
});

const lowStock = computed(() => {
    if (outOfStock.value || props.product.track_inventory === false) return false;
    if (props.product.has_variants) {
        const available = (props.product.variants || []).filter(variant => Number(variant.stock || 0) > 0);
        return available.length > 0 && available.every(variant => Number(variant.alert || 0) > 0 && Number(variant.stock) <= Number(variant.alert));
    }

    return Number(props.product.alert || 0) > 0 && Number(props.product.quantity) <= Number(props.product.alert);
});

const cardClass = computed(() => {
    if (props.template === 'full_text') return 'sm:grid sm:grid-cols-[14rem_1fr]';
    return '';
});
</script>

<template>
    <article class="group overflow-hidden rounded-3xl border border-black/[0.07] bg-white shadow-[0_10px_35px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_45px_rgba(15,23,42,0.12)]" :class="cardClass">
        <div :class="template === 'full_text' ? 'sm:contents' : ''">
            <Link :href="route('catalogo.show', { store: store.slug, product: product.id })" class="relative block overflow-hidden bg-slate-100" :class="template === 'full_text' ? 'aspect-[4/3] sm:aspect-auto sm:min-h-56' : template === 'big' ? 'aspect-[16/9]' : 'aspect-[4/5]'">
                <img
                    :src="product.main_image_url"
                    :alt="product.name"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]"
                >
                <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-3">
                    <span v-if="promotionPercent" class="rounded-full bg-red-600 px-2.5 py-1 text-xs font-extrabold text-white shadow-sm">-{{ promotionPercent }}%</span>
                    <span v-if="outOfStock" class="ml-auto rounded-full bg-slate-950/85 px-2.5 py-1 text-xs font-bold text-white backdrop-blur">Agotado</span>
                    <span v-else-if="lowStock" class="ml-auto rounded-full bg-amber-400 px-2.5 py-1 text-xs font-bold text-amber-950">Últimas unidades</span>
                </div>
            </Link>

            <div class="flex min-w-0 flex-col p-4 sm:p-5">
                <p v-if="product.category" class="mb-1 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ product.category.name }}</p>
                <Link :href="route('catalogo.show', { store: store.slug, product: product.id })" class="line-clamp-2 text-base font-bold leading-snug text-slate-900 transition hover:text-[var(--catalog-accent)] sm:text-lg">
                    {{ product.name }}
                </Link>
                <p v-if="product.short_description && template !== 'default'" class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">{{ product.short_description }}</p>

                <div class="mt-auto pt-4">
                    <div class="flex flex-wrap items-end gap-x-2 gap-y-1">
                        <span class="text-lg font-black tracking-tight text-slate-950 sm:text-xl">{{ formatCurrency(salePrice) }}</span>
                        <span v-if="promotionPercent" class="pb-0.5 text-xs font-medium text-slate-400 line-through">{{ formatCurrency(product.price) }}</span>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <Link
                            :href="route('catalogo.show', { store: store.slug, product: product.id })"
                            class="inline-flex h-11 flex-1 items-center justify-center rounded-full border border-slate-200 px-3 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            {{ product.has_variants ? 'Elegir opciones' : 'Ver producto' }}
                        </Link>
                        <button
                            v-if="store.catalog_show_buy_button"
                            type="button"
                            :dusk="`catalog-buy-now-${product.id}`"
                            :disabled="outOfStock || buying"
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[var(--catalog-accent)] text-[var(--catalog-accent-text)] shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                            :aria-label="product.has_variants ? `Elegir opciones de ${product.name}` : `Comprar ${product.name}`"
                            @click="$emit('buy', product)"
                        >
                            <svg v-if="!buying && !product.has_variants" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4.5h2l1.6 9.1a2 2 0 0 0 2 1.65h7.9a2 2 0 0 0 1.95-1.55L20 7.5H6m6-6v6m-3-3h6" />
                            </svg>
                            <svg v-else-if="!buying" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" />
                            </svg>
                            <svg v-else class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-30" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-linecap="round" stroke-width="3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>
