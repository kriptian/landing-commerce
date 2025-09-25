<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { defineProps } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useToast } from 'vue-toastification'; // <-- 1. IMPORTAMOS EL TOAST

const props = defineProps({
    roles: Array,
});

const toast = useToast(); // <-- 2. INICIALIZAMOS EL TOAST

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.roles.length > 0 ? props.roles[0] : '',
});

const submit = () => {
    form.post(route('admin.users.store'), {
        // --- 3. AGREGAMOS LA NOTIFICACIÓN DE ÉXITO ---
        onSuccess: () => {
            toast.success('¡Usuario creado con éxito!');
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
const superAdminPhone = '573208204198'; // TODO: mover a .env o config si querés
const whatsappUrl = `https://wa.me/${superAdminPhone}?text=${encodeURIComponent('Hola, necesito ampliar mis licencias de usuarios.')}`;
</script>

<template>
    <Head title="Crear Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear Nuevo Usuario</h2>
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

        <Modal :show="showLimitModal" @close="showLimitModal = false">
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-yellow-500 mt-1" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm.75 6a.75.75 0 00-1.5 0v6a.75.75 0 001.5 0V8zm-.75 10a1.125 1.125 0 110-2.25 1.125 1.125 0 010 2.25z"/></svg>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Límite de licencias alcanzado</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ limitMessage || 'Has alcanzado el número máximo de usuarios permitidos para tu plan. Por favor, contactá al administrador para ampliar licencias.' }}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showLimitModal = false" class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50">Cerrar</button>
                    <a :href="whatsappUrl" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74.97.99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64.15-.19.29-.74.94-.9 1.13-.17.19-.33.22-.62.07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45.13-.6.13-.13.29-.33.43-.5.15-.17.19-.29.29-.48.1-.19.05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0-.5.07-.76.36-.26.29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15.19 2 3.06 4.84 4.29.68.29 1.21.46 1.62.59.68.22 1.3.19 1.79.12.55-.08 1.7-.7 1.94-1.38.24-.68.24-1.26.17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                        Contactar por WhatsApp
                    </a>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>