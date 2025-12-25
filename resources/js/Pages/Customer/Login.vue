<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    store: Object,
});

const form = useForm({
    email: '',
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('customer.login.store', { store: props.store.slug }), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 p-6" :style="store?.catalog_body_bg_color ? { backgroundColor: store.catalog_body_bg_color } : {}">
        <Head :title="`Iniciar sesión - ${store.name}`" />

        <div class="w-full max-w-md bg-white shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100">
            <!-- Logo de la tienda -->
            <div class="flex justify-center mb-4">
                <img 
                    v-if="store?.logo_url" 
                    :src="store.logo_url" 
                    :alt="`Logo de ${store.name}`" 
                    class="h-20 w-20 rounded-full object-cover ring-2 ring-gray-200"
                />
            </div>

            <div class="mb-1 text-center">
                <h2 class="text-2xl font-bold text-gray-900">Iniciar sesión</h2>
                <p class="text-sm text-gray-600">Accede a tu cuenta en {{ store.name }}</p>
            </div>
            <div class="mx-auto h-1 w-16 bg-orange-400 rounded-full mb-5"></div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Contraseña" />
                    <div class="relative">
                        <TextInput
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="mt-1 block w-full pr-10"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                        />
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 mr-2 mt-1.5 p-2 text-gray-500 hover:text-gray-700">
                            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <path d="M2.25 12c2.5-4.5 6.215-7.5 9.75-7.5 3.535 0 7.25 3 9.75 7.5-2.5 4.5-6.215 7.5-9.75 7.5-3.535 0-7.25-3-9.75-7.5Z" />
                                <circle cx="12" cy="12" r="3.25" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <path d="M1 1l22 22" />
                                <path d="M9.88 9.88A3 3 0 0 0 12 15a3 3 0 0 0 2.121-.879M10.59 5.08A10.84 10.84 0 0 1 12 4.5c3.535 0 7.25 3 9.75 7.5-.89 1.602-2.02 3.01-3.32 4.14" />
                            </svg>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <Link
                        :href="route('customer.register', { store: store.slug })"
                        class="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                    >
                        ¿No tienes cuenta? Regístrate
                    </Link>
                    <PrimaryButton
                        class="ms-4"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Iniciar sesión
                    </PrimaryButton>
                </div>
            </form>

            <div class="mt-4 text-center">
                <Link
                    :href="route('catalogo.index', { store: store.slug })"
                    class="text-sm text-gray-600 hover:text-gray-800"
                >
                    ← Volver al catálogo
                </Link>
            </div>
        </div>
    </div>
</template>

