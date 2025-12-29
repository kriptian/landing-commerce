<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    roles: Array,
});

// Estado del modal de límite
const showLimitModal = ref(false);
const limitMessage = ref('');
const showSaved = ref(false); // ya no se usa para crear; modal en Index
const superAdminPhone = '573208204198'; // TODO: mover a .env o config si querés
const whatsappUrl = `https://wa.me/${superAdminPhone}?text=${encodeURIComponent('Hola, necesito ampliar mis licencias de usuarios.')}`;

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: (props.roles && Array.isArray(props.roles) && props.roles.length > 0) ? props.roles[0] : '',
});

const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => {
            // Redirige al listado; el modal se muestra allí usando flash
            window.location = route('admin.users.index');
        },
        onError: (errors) => {
            console.error('Errores al crear usuario:', errors);
            if (errors && errors.limit) {
                // Mostramos un modal con CTA a WhatsApp
                showLimitModal.value = true;
                limitMessage.value = errors.limit;
            }
            // Si hay un error general, mostrarlo también
            if (errors && errors.error) {
                console.error('Error general:', errors.error);
                // Los errores de validación se mostrarán automáticamente en los campos
            }
        },
        // Solo resetear campos de contraseña si fue exitoso
        onFinish: () => {
            // Solo resetear si no hay errores
            if (!form.hasErrors) {
                form.reset('password', 'password_confirmation');
            }
        },
    });
};
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
                                        <input 
                                            id="name" 
                                            v-model="form.name" 
                                            type="text" 
                                            :class="['block mt-1 w-full rounded-md shadow-sm border-gray-300', form.errors.name ? 'border-red-500' : '']" 
                                            required
                                        >
                                        <p v-if="form.errors.name" class="text-sm text-red-600 mt-2">{{ form.errors.name }}</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
                                        <input 
                                            id="email" 
                                            v-model="form.email" 
                                            type="email" 
                                            :class="['block mt-1 w-full rounded-md shadow-sm border-gray-300', form.errors.email ? 'border-red-500' : '']" 
                                            required
                                        >
                                        <p v-if="form.errors.email" class="text-sm text-red-600 mt-2">{{ form.errors.email }}</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="role" class="block font-medium text-sm text-gray-700">Rol</label>
                                        <select 
                                            id="role" 
                                            v-model="form.role" 
                                            :class="['block mt-1 w-full rounded-md shadow-sm border-gray-300', form.errors.role ? 'border-red-500' : '']" 
                                            required
                                        >
                                            <option value="" disabled>Selecciona un rol</option>
                                            <option v-for="role in (roles || [])" :key="role" :value="role">
                                                {{ role }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.role" class="text-sm text-red-600 mt-2">{{ form.errors.role }}</p>
                                        <p v-if="!roles || roles.length === 0" class="text-sm text-yellow-600 mt-2">
                                            No hay roles disponibles. Contacta al administrador.
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-4">
                                        <label for="password" class="block font-medium text-sm text-gray-700">Contraseña</label>
                                        <input 
                                            id="password" 
                                            v-model="form.password" 
                                            type="password" 
                                            :class="['block mt-1 w-full rounded-md shadow-sm border-gray-300', form.errors.password ? 'border-red-500' : '']" 
                                            required
                                        >
                                        <p v-if="form.errors.password" class="text-sm text-red-600 mt-2">{{ form.errors.password }}</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Confirmar Contraseña</label>
                                        <input 
                                            id="password_confirmation" 
                                            v-model="form.password_confirmation" 
                                            type="password" 
                                            :class="['block mt-1 w-full rounded-md shadow-sm border-gray-300', form.errors.password_confirmation ? 'border-red-500' : '']" 
                                            required
                                        >
                                        <p v-if="form.errors.password_confirmation" class="text-sm text-red-600 mt-2">{{ form.errors.password_confirmation }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Mostrar errores generales si existen -->
                            <div v-if="form.errors.error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                                <p class="text-sm text-red-600">{{ form.errors.error }}</p>
                            </div>

                            <div class="flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50">
                                    {{ form.processing ? 'Guardando...' : 'Guardar Usuario' }}
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