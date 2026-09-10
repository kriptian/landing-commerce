<script setup>
defineProps({
    number: { type: [Number, String], default: null },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    tone: { type: String, default: 'indigo' },
});
</script>

<template>
    <section class="ui-card p-5 sm:p-7">
        <div class="mb-6 flex items-start gap-4">
            <span v-if="number" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold" :class="tone === 'indigo' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700'">{{ number }}</span>
            <div>
                <h2 class="text-lg font-bold text-slate-950">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm leading-6 text-slate-500">{{ description }}</p>
            </div>
        </div>
        <slot />
    </section>
</template>
