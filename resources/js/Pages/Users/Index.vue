<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    users: Array,
    roles: Array,
});

const loggedInUser = usePage().props.auth.user;
const page = usePage();
const activeTab = ref(page?.props?.ziggy?.query?.tab === 'roles' ? 'roles' : 'users');
const showSuccess = ref(!!page.props.flash?.success);
const successMessage = ref(page.props.flash?.success || '');

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
            successMessage.value = '¡Usuario eliminado con éxito!';
            showSuccess.value = true;
        },
        onError: () => {
            closeUserModal();
            successMessage.value = 'Hubo un error al eliminar el usuario.';
            showSuccess.value = true;
        }
    });
};
// ---------------------------------------------
// ===== LÓGICA NUEVA PARA ELIMINAR ROLES =====
const confirmingRoleDeletion = ref(false);
const roleToDelete = ref(null);
const toast = useToast();

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

                        <div v-if="activeTab === 'users'" class="overflow-x-auto">
                            <table class="min-w-[720px] w-full divide-y divide-gray-200 table-auto">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users" :key="user.id">
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ user.name }}</td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ user.email }}</td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">
                                            <span v-if="user.roles.length > 0" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                {{ user.roles[0].name }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end items-center gap-2">
                                                <Link :href="route('admin.users.edit', user.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-indigo-600" title="Editar">
                                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                                </Link>
                                                <button v-if="user.id !== loggedInUser.id" @click="confirmUserDeletion(user.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-red-600" title="Eliminar">
                                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="activeTab === 'roles'" class="overflow-x-auto">
                            <table class="min-w-[520px] w-full divide-y divide-gray-200 table-auto">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre del Rol</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="role in roles" :key="role.id">
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap">{{ role.name }}</td>
                                        <td class="px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end items-center gap-2">
                                                <Link :href="route('admin.roles.edit', role.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-indigo-600" title="Editar">
                                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                                </Link>
                                                <button @click="confirmRoleDeletion(role.id)" class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-red-600" title="Eliminar">
                                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </div>
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

    <AlertModal
        :show="showSuccess"
        type="info"
        title="Notificación"
        :message="successMessage"
        primary-text="Entendido"
        @primary="showSuccess=false"
        @close="showSuccess=false"
    />
</template>