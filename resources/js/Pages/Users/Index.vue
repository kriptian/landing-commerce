<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, nextTick, computed } from 'vue';
import { useToast } from 'vue-toastification';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AlertModal from '@/Components/AlertModal.vue';
import SectionTour from '@/Components/SectionTour.vue';
import { useSectionTour } from '@/utils/useSectionTour.js';

const props = defineProps({
    users: Array,
    roles: Array,
});

const loggedInUser = usePage().props.auth.user;
const page = usePage();
const activeTab = ref(page?.props?.ziggy?.query?.tab === 'roles' ? 'roles' : 'users');
const showSuccess = ref(!!page.props.flash?.success);
const successMessage = ref(page.props.flash?.success || '');

// Tour de sección para usuarios
const { showTour, steps, handleTourComplete } = useSectionTour('users');

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

// Scroll lateral fades
const usersScrollRef = ref(null);
const rolesScrollRef = ref(null);
const usersShowLeftFade = ref(false);
const usersShowRightFade = ref(false);
const rolesShowLeftFade = ref(false);
const rolesShowRightFade = ref(false);
const updateFades = (elRef, leftRef, rightRef) => {
    const el = elRef.value;
    if (!el) return;
    const maxScrollLeft = el.scrollWidth - el.clientWidth;
    const left = el.scrollLeft || 0;
    leftRef.value = left > 0;
    rightRef.value = left < (maxScrollLeft - 1);
};
onMounted(() => {
    const hUsers = () => updateFades(usersScrollRef, usersShowLeftFade, usersShowRightFade);
    const hRoles = () => updateFades(rolesScrollRef, rolesShowLeftFade, rolesShowRightFade);
    nextTick(() => { hUsers(); hRoles(); });
    usersScrollRef.value?.addEventListener('scroll', hUsers, { passive: true });
    rolesScrollRef.value?.addEventListener('scroll', hRoles, { passive: true });
    window.addEventListener('resize', hUsers);
    window.addEventListener('resize', hRoles);
});
onBeforeUnmount(() => {
    usersScrollRef.value?.removeEventListener('scroll', () => {});
    rolesScrollRef.value?.removeEventListener('scroll', () => {});
    window.removeEventListener('resize', () => {});
});

// Redimensionables: primera columna de usuarios y roles
const USERS_COL_KEY = 'users_firstcol_w_px';
const ROLES_COL_KEY = 'roles_firstcol_w_px';
const FIRST_MIN = 60; const FIRST_MAX = 320;
const usersFirstColWidth = ref(Number(localStorage.getItem(USERS_COL_KEY)) || 180);
const rolesFirstColWidth = ref(Number(localStorage.getItem(ROLES_COL_KEY)) || 200);
const usersFirstColStyle = computed(() => ({ width: usersFirstColWidth.value+'px', minWidth: usersFirstColWidth.value+'px', maxWidth: usersFirstColWidth.value+'px' }));
const rolesFirstColStyle = computed(() => ({ width: rolesFirstColWidth.value+'px', minWidth: rolesFirstColWidth.value+'px', maxWidth: rolesFirstColWidth.value+'px' }));

let startX = 0; let startW = 0; let target = 'users';
const onResizeMove = (e) => {
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const next = Math.max(FIRST_MIN, Math.min(FIRST_MAX, startW + (clientX - startX)));
    if (target === 'users') usersFirstColWidth.value = next; else rolesFirstColWidth.value = next;
};
const stopResize = () => {
    document.removeEventListener('mousemove', onResizeMove);
    document.removeEventListener('mouseup', stopResize);
    document.removeEventListener('touchmove', onResizeMove);
    document.removeEventListener('touchend', stopResize);
    try {
        localStorage.setItem(USERS_COL_KEY, String(usersFirstColWidth.value));
        localStorage.setItem(ROLES_COL_KEY, String(rolesFirstColWidth.value));
    } catch(_) {}
};
const startResizeUsers = (e) => { target='users'; startX = e.touches ? e.touches[0].clientX : e.clientX; startW = usersFirstColWidth.value; document.addEventListener('mousemove', onResizeMove, { passive:false }); document.addEventListener('mouseup', stopResize); document.addEventListener('touchmove', onResizeMove, { passive:false }); document.addEventListener('touchend', stopResize); };
const startResizeRoles = (e) => { target='roles'; startX = e.touches ? e.touches[0].clientX : e.clientX; startW = rolesFirstColWidth.value; document.addEventListener('mousemove', onResizeMove, { passive:false }); document.addEventListener('mouseup', stopResize); document.addEventListener('touchmove', onResizeMove, { passive:false }); document.addEventListener('touchend', stopResize); };

</script>

<template>
    <Head title="Gestionar Usuarios y Roles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestionar Usuarios y Roles</h2>
                <div>
                    <Link v-if="activeTab === 'roles'" :href="route('admin.roles.create')" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded shadow ml-4">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm9-1.5V7h4.5L13 2.5z"/><path d="M8 13h8v2H8zM8 9h5v2H8z"/></svg>
                        <span>Crear</span>
                    </Link>
                    <Link v-if="activeTab === 'users'" :href="route('admin.users.create')" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm9-1.5V7h4.5L13 2.5z"/><path d="M8 13h8v2H8zM8 9h5v2H8z"/></svg>
                        <span>Crear</span>
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

                        <div v-if="activeTab === 'users'" ref="usersScrollRef" class="relative overflow-x-auto">
                            <div v-show="usersShowLeftFade" class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-white to-transparent"></div>
                            <div v-show="usersShowRightFade" class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-white to-transparent"></div>
                            <table class="min-w-[720px] w-full divide-y divide-gray-200 table-auto">
                                <thead class="sticky top-0 z-10 bg-gray-50">
                                    <tr>
                                        <th class="sticky left-0 z-20 bg-gray-50 px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap relative" :style="usersFirstColStyle">
                                            Nombre
                                            <div @mousedown="startResizeUsers" @touchstart.prevent="startResizeUsers" class="absolute top-0 right-0 h-full w-3 cursor-col-resize group">
                                                <div class="mx-auto my-auto h-6 w-1.5 bg-gray-300 rounded-full group-hover:bg-indigo-400"></div>
                                            </div>
                                        </th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(user, idx) in users" :key="user.id" class="odd:bg-white even:bg-gray-100">
                                        <td class="sticky left-0 z-10 px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap border-r truncate" :style="usersFirstColStyle" :title="user.name" :class="idx % 2 === 1 ? 'bg-gray-100' : 'bg-white'">{{ user.name }}</td>
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

                        <div v-if="activeTab === 'roles'" ref="rolesScrollRef" class="relative overflow-x-auto">
                            <div v-show="rolesShowLeftFade" class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-white to-transparent"></div>
                            <div v-show="rolesShowRightFade" class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-white to-transparent"></div>
                            <table class="min-w-[520px] w-full divide-y divide-gray-200 table-auto">
                                <thead class="sticky top-0 z-10 bg-gray-50">
                                    <tr>
                                        <th class="sticky left-0 z-20 bg-gray-50 px-3 py-2 sm:px-6 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap relative" :style="rolesFirstColStyle">
                                            Nombre del Rol
                                            <div @mousedown="startResizeRoles" @touchstart.prevent="startResizeRoles" class="absolute top-0 right-0 h-full w-3 cursor-col-resize group">
                                                <div class="mx-auto my-auto h-6 w-1.5 bg-gray-300 rounded-full group-hover:bg-indigo-400"></div>
                                            </div>
                                        </th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(role, idx) in roles" :key="role.id" class="odd:bg-white even:bg-gray-100">
                                        <td class="sticky left-0 z-10 px-3 py-3 sm:px-6 sm:py-4 whitespace-nowrap border-r truncate" :style="rolesFirstColStyle" :title="role.name" :class="idx % 2 === 1 ? 'bg-gray-100' : 'bg-white'">{{ role.name }}</td>
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

    <!-- Tour de sección para usuarios -->
    <SectionTour 
        :show="showTour" 
        section="users"
        :steps="steps"
        @complete="handleTourComplete"
    />
</template>