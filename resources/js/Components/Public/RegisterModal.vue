<script setup>
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    show: Boolean,
    store: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('customer.register.store', { store: props.store.slug }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        onSuccess: () => {
            emit('close');
            // Inertia will handle the redirect/reload automatically if the controller redirects.
            // If controller redirects to 'catalogo.index', we are already there, so it effectively reloads with auth.
        },
    });
};

const close = () => {
    emit('close');
    form.reset();
    form.clearErrors();
};

</script>

<template>
    <Modal :show="show" @close="close" maxWidth="md">
        <div class="p-6 relative">
             <!-- Botón cerrar -->
            <button 
                @click="close" 
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-500 transition-colors"
                aria-label="Cerrar"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

             <!-- Header -->
            <div class="text-center mb-6">
                 <img 
                    v-if="store?.logo_url" 
                    :src="store.logo_url" 
                    :alt="`Logo de ${store.name}`" 
                    class="h-16 w-16 rounded-full object-cover ring-2 ring-gray-100 mx-auto mb-3"
                />
                <h2 class="text-xl font-bold text-gray-900">Crear cuenta</h2>
                <p class="text-sm text-gray-500">Regístrate en {{ store.name }}</p>
                <div class="mx-auto h-1 w-12 bg-orange-400 rounded-full mt-3"></div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel for="modal-name" value="Nombre completo" />
                    <TextInput
                        id="modal-name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="modal-email" value="Email" />
                    <TextInput
                        id="modal-email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="modal-phone" value="Teléfono (Opcional)" />
                    <TextInput
                        id="modal-phone"
                        type="tel"
                        class="mt-1 block w-full"
                        v-model="form.phone"
                        autocomplete="tel"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel for="modal-password" value="Contraseña" />
                    <div class="relative">
                        <TextInput
                            id="modal-password"
                            :type="showPassword ? 'text' : 'password'"
                            class="mt-1 block w-full pr-10"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
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

                <div>
                    <InputLabel for="modal-password_confirmation" value="Confirmar contraseña" />
                    <TextInput
                        id="modal-password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div class="flex items-center justify-between pt-4">
                    <Link
                         :href="route('customer.login', { store: store.slug })"
                         class="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                         @click="close"
                    >
                        ¿Ya tienes cuenta? Inicia sesión
                    </Link>
                    <PrimaryButton
                        class="ms-4"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Registrarse
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
