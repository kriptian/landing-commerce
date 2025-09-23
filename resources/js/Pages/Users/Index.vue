<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    users: Array,
    roles: Array,
});

const loggedInUser = usePage().props.auth.user;
const activeTab = ref('users');
const toast = useToast();

// --- Lógica para eliminar USUARIOS (la que ya tenías) ---
const confirmingUserDeletion = ref(false);
const userToDelete = ref(null);

const confirmUserDeletion = (id) => {
    userToDelete.value = id;
    confirmingUserDeletion.value = true;
};

const closeUserModal = () => {
    confirmingUserDeletion.value = false;
    userToDelete.value = null;
};

const deleteUser = () => {
    router.delete(route('admin.users.destroy', userToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            closeUserModal();
            toast.success('¡Usuario eliminado con éxito!');
        },
        onError: () => {
            closeUserModal();
            toast.error('Hubo un error al eliminar el usuario.');
        }
    });
};
// ---------------------------------------------


// ===== LÓGICA NUEVA PARA ELIMINAR ROLES =====
const confirmingRoleDeletion = ref(false);
const roleToDelete = ref(null);

const confirmRoleDeletion = (id) => {
    roleToDelete.value = id;
    confirmingRoleDeletion.value = true;
};

const closeRoleModal = () => {
    confirmingRoleDeletion.value = false;
    roleToDelete.value = null;
};

const deleteRole = () => {
    router.delete(route('admin.roles.destroy', roleToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            closeRoleModal();
            toast.success('¡Rol eliminado con éxito!');
        },
        onError: () => {
            closeRoleModal();
            toast.error('No se puede eliminar un rol que está en uso.');
        }
    });
};
// ===========================================

</script>

<template>
    <Head title="Gestionar Usuarios y Roles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestionar Usuarios y Roles</h2>
                <div>
                    <Link v-if="activeTab === 'roles'" :href="route('admin.roles.create')" class="bg-green-500 text-white font-bold py-2 px-4 rounded hover:bg-green-700 ml-4">
                        Crear Nuevo Rol
                    </Link>
                    <Link v-if="activeTab === 'users'" :href="route('admin.users.create')" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                        Crear Nuevo Usuario
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="mb-4 border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button @click="activeTab = 'users'" :class="[activeTab === 'users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                                    Usuarios
                                </button>
                                <button @click="activeTab = 'roles'" :class="[activeTab === 'roles' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                                    Roles
                                </button>
                            </nav>
                        </div>

                        <div v-if="activeTab === 'users'">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users" :key="user.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ user.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ user.email }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-if="user.roles.length > 0" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                {{ user.roles[0].name }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('admin.users.edit', user.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>
                                            
                                            <button
                                                v-if="user.id !== loggedInUser.id"
                                                @click="confirmUserDeletion(user.id)"
                                                class="text-red-600 hover:text-red-900 ml-4"
                                            >
                                                Eliminar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="activeTab === 'roles'">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre del Rol</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="role in roles" :key="role.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ role.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('admin.roles.edit', role.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>
                                            
                                            <button
                                                @click="confirmRoleDeletion(role.id)"
                                                class="text-red-600 hover:text-red-900 ml-4"
                                            >
                                                Eliminar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="confirmingUserDeletion" @close="closeUserModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Estás seguro de que quieres eliminar este usuario?
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Esta acción es irreversible.
            </p>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeUserModal"> Cancelar </SecondaryButton>
                <DangerButton class="ms-3" @click="deleteUser">
                    Sí, Eliminar Usuario
                </DangerButton>
            </div>
        </div>
    </Modal>
    
    <Modal :show="confirmingRoleDeletion" @close="closeRoleModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Estás seguro de que quieres eliminar este rol?
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Esta acción es irreversible. No podrás eliminar un rol si hay usuarios que lo tengan asignado.
            </p>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeRoleModal"> Cancelar </SecondaryButton>
                <DangerButton class="ms-3" @click="deleteRole">
                    Sí, Eliminar Rol
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>