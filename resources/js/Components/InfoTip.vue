<template>
    <span :title="text" class="inline-flex items-center justify-center w-4 h-4 text-[10px] rounded-full border text-gray-500 select-none">i</span>
</template>

<script setup>
const props = defineProps({
    text: { type: String, default: '' },
});
</script>


