<script setup>
defineProps({
    width: { type: String, default: 'wide' },
});
</script>

<template>
    <div class="px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
        <div class="mx-auto" :class="width === 'wide' ? 'max-w-[92rem]' : 'max-w-7xl'">
            <slot />
        </div>
    </div>
</template>
