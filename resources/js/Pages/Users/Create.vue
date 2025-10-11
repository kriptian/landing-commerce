<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    roles: Array,
});


const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.roles.length > 0 ? props.roles[0] : '',
});

const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => {
            // Redirige al listado; el modal se muestra allí usando flash
            window.location = route('admin.users.index');
        },
        onError: (errors) => {
            if (errors && errors.limit) {
                // Mostramos un modal con CTA a WhatsApp
                showLimitModal.value = true;
                limitMessage.value = errors.limit;
            }
        },
        // Mantenemos tu lógica de 'onFinish' para limpiar los campos
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

// Estado del modal de límite
import { ref } from 'vue';
const showLimitModal = ref(false);
const limitMessage = ref('');
const showSaved = ref(false); // ya no se usa para crear; modal en Index
const superAdminPhone = '573208204198'; // TODO: mover a .env o config si querés
const whatsappUrl = `https://wa.me/${superAdminPhone}?text=${encodeURIComponent('Hola, necesito ampliar mis licencias de usuarios.')}`;
</script>

<template>
    <Head title="Crear Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear usuario</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="mb-4">
                                        <label for="name" class="block font-medium text-sm text-gray-700">Nombre</label>
                                        <input id="name" v-model="form.name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                        <p v-if="form.errors.name" class="text-sm text-red-600 mt-2">{{ form.errors.name }}</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
                                        <input id="email" v-model="form.email" type="email" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                        <p v-if="form.errors.email" class="text-sm text-red-600 mt-2">{{ form.errors.email }}</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="role" class="block font-medium text-sm text-gray-700">Rol</label>
                                        <select id="role" v-model="form.role" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                            <option v-for="role in roles" :key="role" :value="role">
                                                {{ role }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.role" class="text-sm text-red-600 mt-2">{{ form.errors.role }}</p>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-4">
                                        <label for="password" class="block font-medium text-sm text-gray-700">Contraseña</label>
                                        <input id="password" v-model="form.password" type="password" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                        <p v-if="form.errors.password" class="text-sm text-red-600 mt-2">{{ form.errors.password }}</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Confirmar Contraseña</label>
                                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Guardar Usuario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <AlertModal
            :show="showLimitModal"
            type="error"
            title="Límite de licencias alcanzado"
            :message="limitMessage || 'Tu plan alcanzó el máximo de usuarios. Contactá al administrador para ampliar licencias.'"
            primary-text="Contactar"
            :primary-href="whatsappUrl"
            secondary-text="Entendido"
            @close="showLimitModal=false"
            @secondary="showLimitModal=false"
        />

    <AlertModal
        :show="showSaved"
        type="success"
        title="¡Usuario creado con éxito!"
        primary-text="Entendido"
        @primary="showSaved=false"
        @close="showSaved=false"
    />
    </AuthenticatedLayout>
</template>