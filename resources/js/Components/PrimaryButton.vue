<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button
        :type="type"
        class="ui-primary-button"
    >
        <slot />
    </button>
    
</template>
