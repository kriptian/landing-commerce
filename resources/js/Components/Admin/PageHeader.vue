<script setup>
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <header class="mb-6 flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">{{ eyebrow }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ title }}</h1>
            <p v-if="description" class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2"><slot name="actions" /></div>
    </header>
</template>
