<script setup>
// Layout de invitado sin branding de Laravel
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white/90 backdrop-blur-sm shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100">
            <slot />
        </div>
    </div>
</template>
