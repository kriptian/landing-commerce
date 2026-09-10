<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="ui-error" role="alert">
            {{ message }}
        </p>
    </div>
</template>
