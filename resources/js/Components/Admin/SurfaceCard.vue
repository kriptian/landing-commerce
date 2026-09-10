<script setup>
defineProps({
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.03),0_12px_32px_rgba(15,23,42,0.04)]" :class="padded ? 'p-5 sm:p-6' : ''">
        <slot />
    </section>
</template>
