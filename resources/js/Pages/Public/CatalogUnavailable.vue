<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({ store: { type: Object, required: true } });
</script>

<template>
    <Head :title="`${store.name} volvera pronto`" />
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-5 py-12 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(79,70,229,0.38),transparent_35%),radial-gradient(circle_at_80%_80%,rgba(34,211,238,0.18),transparent_32%)]" aria-hidden="true"></div>
        <section class="relative w-full max-w-xl text-center">
            <div class="mx-auto flex h-24 w-24 items-center justify-center overflow-hidden rounded-3xl border border-white/15 bg-white p-3 shadow-2xl shadow-indigo-950/50">
                <img :src="store.logo_url" :alt="store.name" class="h-full w-full object-contain" />
            </div>
            <p class="mt-8 text-xs font-extrabold uppercase tracking-[0.24em] text-cyan-300">{{ store.name }}</p>
            <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">Estamos preparando novedades para ti</h1>
            <p class="mx-auto mt-6 max-w-lg text-lg leading-8 text-slate-300">Nuestro catalogo no esta disponible temporalmente, pero regresaremos muy pronto. Gracias por tu comprension.</p>
            <div class="mx-auto mt-9 h-1.5 w-24 rounded-full bg-gradient-to-r from-indigo-500 to-cyan-300"></div>
        </section>
    </main>
</template>
