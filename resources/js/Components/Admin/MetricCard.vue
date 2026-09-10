<script setup>
import SurfaceCard from './SurfaceCard.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'cyan' },
});

const tones = {
    cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-100',
    violet: 'bg-violet-50 text-violet-700 ring-violet-100',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
};
</script>

<template>
    <SurfaceCard class="relative overflow-hidden">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-slate-500">{{ label }}</p>
                <p class="mt-2 truncate text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ value }}</p>
                <p v-if="hint" class="mt-2 text-xs text-slate-400">{{ hint }}</p>
            </div>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1" :class="tones[tone] || tones.cyan">
                <slot name="icon" />
            </span>
        </div>
    </SurfaceCard>
</template>
