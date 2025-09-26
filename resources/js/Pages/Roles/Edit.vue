<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    role: Object,
    permissions: Array,
});

import { ref } from 'vue';
const page = usePage();
const showSaved = ref(page?.props?.flash?.success ? true : false);

// ===== AQUÍ ESTÁ EL ARREGLO =====
// 1. Primero procesamos los permisos y los guardamos en una variable normal
const initialPermissions = props.role.permissions.map(p => p.name);

// 2. LUEGO, usamos esa variable para inicializar el formulario
const form = useForm({
    _method: 'PUT',
    name: props.role.name,
    permissions: initialPermissions, // <-- Usamos la variable, no el 'computed'
});
// =================================

const submit = () => {
    form.put(route('admin.roles.update', props.role.id), {
        // --- 3. AGREGAMOS LA NOTIFICACIÓN DE ÉXITO ---
        onSuccess: () => { showSaved.value = true; }
    });
};
</script>

<template>
    <Head title="Editar Rol" />
        <AlertModal
            :show="showSaved"
            type="success"
            title="Rol actualizado"
            primary-text="Entendido"
            @primary="showSaved=false"
            @close="showSaved=false"
        />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-4">
                <Link :href="route('admin.users.index') + '?tab=roles'" class="text-blue-600 hover:text-blue-800">
                    &larr; Volver a Usuarios y Roles
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Rol: {{ role.name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit">
                            <div class="mb-6">
                                <label for="name" class="block font-medium text-sm text-gray-700">Nombre del Rol</label>
                                <input id="name" v-model="form.name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                <p v-if="form.errors.name" class="text-sm text-red-600 mt-2">{{ form.errors.name }}</p>
                            </div>

                            <div class="mb-6">
                                <label class="block font-medium text-sm text-gray-700">Permisos</label>
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div v-for="permission in permissions" :key="permission" class="flex items-center">
                                        <input :id="permission" :value="permission" v-model="form.permissions" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <label :for="permission" class="ml-2 text-sm text-gray-600">{{ permission }}</label>
                                    </div>
                                </div>
                                 <p v-if="form.errors.permissions" class="text-sm text-red-600 mt-2">{{ form.errors.permissions }}</p>
                            </div>

                            <div class="flex items-center justify-end mt-4 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Actualizar Rol
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>