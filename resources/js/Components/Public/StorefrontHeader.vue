<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    store: { type: Object, required: true },
    search: { type: String, default: '' },
    cartCount: { type: Number, default: 0 },
    customer: { type: Object, default: null },
    notificationsCount: { type: Number, default: 0 },
    hasPromo: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:search',
    'submit-search',
    'open-categories',
    'open-filters',
    'open-login',
    'open-register',
]);
</script>

<template>
    <div v-if="hasPromo" class="bg-[var(--catalog-promo)] text-[var(--catalog-promo-text)]">
        <div class="mx-auto flex max-w-7xl items-center justify-center px-4 py-2 text-center text-xs font-bold uppercase tracking-[0.16em] sm:text-sm">
            Promociones disponibles por tiempo limitado
        </div>
    </div>

    <header class="sticky top-0 z-40 border-b border-black/5 bg-[var(--catalog-header)] text-[var(--catalog-header-text)] shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center gap-3 sm:h-20">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-black/10 transition hover:bg-black/5"
                    aria-label="Abrir categorías"
                    @click="emit('open-categories')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>

                <Link :href="route('catalogo.index', { store: store.slug })" class="flex min-w-0 shrink-0 items-center gap-2.5">
                    <img
                        v-if="store.logo_url"
                        :src="store.logo_url"
                        :alt="`Logo de ${store.name}`"
                        class="h-10 w-10 rounded-xl object-cover ring-1 ring-black/10 sm:h-11 sm:w-11"
                    >
                    <span class="hidden max-w-48 truncate text-base font-extrabold tracking-tight sm:block">{{ store.name }}</span>
                </Link>

                <form class="mx-auto hidden w-full max-w-2xl md:block" role="search" @submit.prevent="emit('submit-search')">
                    <label class="relative block">
                        <span class="sr-only">Buscar productos</span>
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 opacity-45" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.3-4.3m1.3-5.2a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                        </svg>
                        <input
                            :value="search"
                            type="search"
                            autocomplete="off"
                            placeholder="¿Qué estás buscando?"
                            class="h-11 w-full rounded-full border-0 bg-[var(--catalog-input)] pl-12 pr-4 text-sm text-[var(--catalog-input-text)] shadow-inner ring-1 ring-black/10 transition placeholder:opacity-50 focus:ring-2 focus:ring-[var(--catalog-accent)]"
                            @input="emit('update:search', $event.target.value)"
                        >
                    </label>
                </form>

                <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-black/5 md:hidden"
                        aria-label="Abrir filtros"
                        @click="emit('open-filters')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M7 12h10m-7 6h4" />
                        </svg>
                    </button>

                    <Link
                        v-if="customer"
                        :href="route('customer.account', { store: store.slug })"
                        class="relative inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-black/5"
                        aria-label="Mi cuenta"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
                        </svg>
                        <span v-if="notificationsCount" class="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-4 text-white">
                            {{ notificationsCount > 9 ? '9+' : notificationsCount }}
                        </span>
                    </Link>
                    <button
                        v-else
                        type="button"
                        class="hidden rounded-full px-3 py-2 text-sm font-semibold transition hover:bg-black/5 sm:inline-flex"
                        @click="emit('open-login')"
                    >
                        Ingresar
                    </button>
                    <button
                        v-if="!customer"
                        type="button"
                        class="hidden rounded-full bg-[var(--catalog-accent)] px-4 py-2 text-sm font-bold text-[var(--catalog-accent-text)] shadow-sm transition hover:opacity-90 lg:inline-flex"
                        @click="emit('open-register')"
                    >
                        Crear cuenta
                    </button>

                    <Link
                        :href="route('cart.index', { store: store.slug })"
                        class="relative inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-black/5"
                        aria-label="Carrito de compras"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4.5h2l1.6 9.1a2 2 0 0 0 2 1.65h7.9a2 2 0 0 0 1.95-1.55L20 7.5H6m3.5 11.25h.01m6.49 0h.01" />
                        </svg>
                        <span v-if="cartCount" class="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-4 text-white">
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </Link>
                </div>
            </div>

            <form class="pb-3 md:hidden" role="search" @submit.prevent="emit('submit-search')">
                <label class="relative block">
                    <span class="sr-only">Buscar productos</span>
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 opacity-45" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.3-4.3m1.3-5.2a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                    </svg>
                    <input
                        :value="search"
                        type="search"
                        autocomplete="off"
                        :placeholder="`Buscar en ${store.name}`"
                        class="h-11 w-full rounded-full border-0 bg-[var(--catalog-input)] pl-12 pr-4 text-sm text-[var(--catalog-input-text)] ring-1 ring-black/10 focus:ring-2 focus:ring-[var(--catalog-accent)]"
                        @input="emit('update:search', $event.target.value)"
                    >
                </label>
            </form>
        </div>
    </header>
</template>
