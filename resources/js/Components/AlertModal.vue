<script setup>
import Modal from '@/Components/Modal.vue';
const props = defineProps({
  show: { type: Boolean, default: false },
  type: { type: String, default: 'error' }, // 'success' | 'error' | 'warning' | 'info'
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  primaryText: { type: String, default: 'Aceptar' },
  secondaryText: { type: String, default: '' },
  primaryHref: { type: String, default: '' },
});
const emit = defineEmits(['close','primary','secondary']);

const iconClass = {
  success: 'text-green-600 border-green-500',
  error: 'text-red-600 border-red-500',
  warning: 'text-yellow-600 border-yellow-500',
  info: 'text-blue-600 border-blue-500',
};
</script>

<template>
  <Modal :show="show" @close="emit('close')">
    <div class="p-6 sm:p-8 text-center">
      <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full border-2"
           :class="iconClass[type]">
        <svg v-if="type==='success'" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <svg v-else-if="type==='error'" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        <svg v-else-if="type==='warning'" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <svg v-else class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      </div>
      <h3 class="text-2xl font-bold text-gray-900">{{ title }}</h3>
      <p class="mt-2 text-sm text-gray-600" v-if="message">{{ message }}</p>
      <div class="mt-6 flex flex-col sm:flex-row justify-center gap-3">
        <a v-if="primaryHref" :href="primaryHref" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 text-white rounded-full hover:bg-green-700 w-full sm:w-auto justify-center" @click="emit('primary')">{{ primaryText }}</a>
        <button v-else type="button" class="px-5 py-2.5 rounded-full border text-gray-700 hover:bg-gray-50 w-full sm:w-auto" @click="emit('primary')">{{ primaryText }}</button>
        <button v-if="secondaryText" type="button" class="px-5 py-2.5 rounded-full border text-gray-700 hover:bg-gray-50 w-full sm:w-auto" @click="emit('secondary')">{{ secondaryText }}</button>
      </div>
    </div>
  </Modal>
  
</template>


