<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-slate-950">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(79,70,229,0.42),transparent_42%),radial-gradient(circle_at_bottom_right,rgba(6,182,212,0.22),transparent_36%)]" aria-hidden="true"></div>
        <div class="relative mx-auto grid min-h-screen max-w-7xl items-center gap-10 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(24rem,0.7fr)] lg:px-8">
            <section class="hidden text-white lg:block">
                <Link :href="route('home')" class="inline-flex items-center rounded-xl bg-white p-3 shadow-lg">
                    <img src="/images/New_Logo_ondgtl.png?v=5" alt="Ondigital Solution" class="h-14 w-auto object-contain" />
                </Link>
                <p class="mt-10 text-sm font-bold uppercase tracking-[0.2em] text-cyan-300">Comercio sin complicaciones</p>
                <h1 class="mt-3 max-w-xl text-4xl font-black leading-tight tracking-tight xl:text-5xl">Tu tienda, tus ventas y tu equipo en un solo lugar.</h1>
                <p class="mt-5 max-w-lg text-lg leading-8 text-slate-300">Organiza productos, vende presencialmente y recibe pedidos online con herramientas pensadas para comerciantes.</p>
                <ul class="mt-8 grid max-w-lg gap-4 text-sm text-slate-200 sm:grid-cols-2">
                    <li class="rounded-xl border border-white/10 bg-white/5 p-4">Sin comisiones por venta</li>
                    <li class="rounded-xl border border-white/10 bg-white/5 p-4">Inventario fácil de controlar</li>
                    <li class="rounded-xl border border-white/10 bg-white/5 p-4">Pedidos directos por WhatsApp</li>
                    <li class="rounded-xl border border-white/10 bg-white/5 p-4">Soporte para empezar</li>
                </ul>
            </section>

            <section class="mx-auto w-full" :class="wide ? 'max-w-2xl' : 'max-w-md'">
                <div class="ui-card p-5 sm:p-8">
                    <Link :href="route('home')" class="mb-6 flex justify-center lg:hidden">
                        <img src="/images/New_Logo_ondgtl.png?v=5" alt="Ondigital Solution" class="h-20 w-auto object-contain" />
                    </Link>
                    <div v-if="title" class="mb-6">
                        <h2 class="text-2xl font-bold tracking-tight text-slate-950">{{ title }}</h2>
                        <p v-if="description" class="mt-2 text-sm leading-6 text-slate-600">{{ description }}</p>
                    </div>
                    <slot />
                </div>
                <p class="mt-5 text-center text-xs text-slate-400">Tus datos se protegen y nunca se comparten con otras tiendas.</p>
            </section>
        </div>
    </main>
</template>
