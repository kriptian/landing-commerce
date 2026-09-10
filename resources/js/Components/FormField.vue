<script setup>
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    error: { type: String, default: '' },
    help: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <label :for="id" class="ui-label">{{ label }} <span v-if="required" class="text-rose-600" aria-hidden="true">*</span></label>
        <slot :described-by="error ? `${id}-error` : help ? `${id}-help` : undefined" :invalid="Boolean(error)" />
        <p v-if="error" :id="`${id}-error`" class="ui-error" role="alert">{{ error }}</p>
        <p v-else-if="help" :id="`${id}-help`" class="ui-help">{{ help }}</p>
    </div>
</template>
