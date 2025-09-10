<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: Object, // El usuario que vamos a editar
    roles: Array, // La lista de roles disponibles
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.roles[0]?.name || '', // Asigna el rol actual del usuario
});

const submit = () => {
    form.put(route('admin.users.update', props.user.id));
};
</script>

<template>
    <Head title="Editar Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Usuario: {{ user.name }}</h2>
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
                                        <label for="password" class="block font-medium text-sm text-gray-700">Nueva Contraseña (Opcional)</label>
                                        <input id="password" v-model="form.password" type="password" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                                        <p class="text-xs text-gray-500 mt-1">Dejar en blanco para no cambiar la contraseña.</p>
                                        <p v-if="form.errors.password" class="text-sm text-red-600 mt-2">{{ form.errors.password }}</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Confirmar Nueva Contraseña</label>
                                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Actualizar Usuario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>