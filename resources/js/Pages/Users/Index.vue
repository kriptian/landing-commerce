<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AlertModal from '@/Components/AlertModal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ users: Array, roles: Array });
const page = usePage();
const loggedInUser = page.props.auth.user;
const activeTab = ref(typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'roles' ? 'roles' : 'users');
const search = ref('');
const confirmingUserDeletion = ref(false);
const confirmingRoleDeletion = ref(false);
const userToDelete = ref(null);
const roleToDelete = ref(null);
const notice = ref({ show: Boolean(page.props.flash?.success), type: 'success', message: page.props.flash?.success || '' });

const filteredUsers = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.users;
    return props.users.filter((user) => [user.name, user.email, user.roles?.[0]?.name].filter(Boolean).some((value) => String(value).toLowerCase().includes(term)));
});

const filteredRoles = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.roles;
    return props.roles.filter((role) => role.name.toLowerCase().includes(term));
});

const usersWithRole = (role) => props.users.filter((user) => user.roles?.some((assigned) => assigned.id === role.id)).length;

const setTab = (tab) => {
    activeTab.value = tab;
    search.value = '';
    window.history.replaceState({}, '', `${route('admin.users.index')}${tab === 'roles' ? '?tab=roles' : ''}`);
};

const confirmUserDeletion = (id) => {
    userToDelete.value = id;
    confirmingUserDeletion.value = true;
};

const confirmRoleDeletion = (id) => {
    roleToDelete.value = id;
    confirmingRoleDeletion.value = true;
};

const closeModals = () => {
    confirmingUserDeletion.value = false;
    confirmingRoleDeletion.value = false;
    userToDelete.value = null;
    roleToDelete.value = null;
};

const deleteUser = () => router.delete(route('admin.users.destroy', userToDelete.value), {
    preserveScroll: true,
    onSuccess: () => {
        closeModals();
        notice.value = { show: true, type: 'success', message: 'Usuario eliminado correctamente.' };
    },
    onError: (errors) => {
        closeModals();
        notice.value = { show: true, type: 'error', message: errors?.delete || 'No se pudo eliminar el usuario.' };
    },
});

const deleteRole = () => router.delete(route('admin.roles.destroy', roleToDelete.value), {
    preserveScroll: true,
    onSuccess: () => {
        closeModals();
        notice.value = { show: true, type: 'success', message: 'Rol eliminado correctamente.' };
    },
    onError: (errors) => {
        closeModals();
        notice.value = { show: true, type: 'error', message: errors?.delete || 'No se puede eliminar un rol asignado o requerido por el sistema.' };
    },
});
</script>

<template>
    <Head title="Equipo y permisos" />
    <AuthenticatedLayout>
        <AdminPage>
            <PageHeader eyebrow="Administracion" title="Equipo y permisos" description="Invita colaboradores y define con claridad que puede hacer cada rol.">
                <template #actions><Link :href="activeTab === 'users' ? route('admin.users.create') : route('admin.roles.create')" class="ui-primary-button">+ {{ activeTab === 'users' ? 'Nuevo usuario' : 'Nuevo rol' }}</Link></template>
            </PageHeader>

            <section class="grid gap-3 sm:grid-cols-2" aria-label="Seccion de administracion">
                <button type="button" class="rounded-2xl border p-5 text-left transition" :class="activeTab === 'users' ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-100' : 'border-slate-200 bg-white hover:border-indigo-200'" @click="setTab('users')"><span class="text-xs font-extrabold uppercase tracking-wider text-indigo-600">Personas</span><strong class="mt-2 block text-lg text-slate-900">Usuarios</strong><span class="mt-1 block text-sm text-slate-500">{{ users.length }} personas con acceso a la tienda.</span></button>
                <button type="button" class="rounded-2xl border p-5 text-left transition" :class="activeTab === 'roles' ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-100' : 'border-slate-200 bg-white hover:border-indigo-200'" @click="setTab('roles')"><span class="text-xs font-extrabold uppercase tracking-wider text-indigo-600">Responsabilidades</span><strong class="mt-2 block text-lg text-slate-900">Roles y permisos</strong><span class="mt-1 block text-sm text-slate-500">{{ roles.length }} perfiles de acceso configurados.</span></button>
            </section>

            <div class="ui-card p-4 sm:p-5"><label for="team-search" class="ui-label">Buscar {{ activeTab === 'users' ? 'persona' : 'rol' }}</label><input id="team-search" v-model="search" type="search" class="ui-input" :placeholder="activeTab === 'users' ? 'Nombre, correo o rol' : 'Nombre del rol'" /></div>

            <section v-if="activeTab === 'users'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="user in filteredUsers" :key="user.id" class="ui-card flex flex-col p-5"><div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-extrabold text-indigo-700">{{ user.name?.charAt(0)?.toUpperCase() }}</div><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="truncate font-bold text-slate-900">{{ user.name }}</h2><span v-if="user.id === loggedInUser.id" class="ui-badge-neutral">Tu cuenta</span></div><p class="truncate text-sm text-slate-500">{{ user.email }}</p></div></div><div class="mt-5 rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Rol asignado</p><p class="mt-1 font-semibold text-slate-800">{{ user.roles?.[0]?.name || 'Sin rol' }}</p></div><div class="mt-auto flex gap-2 pt-5"><Link :href="route('admin.users.edit', user.id)" class="ui-secondary-button flex-1">Editar acceso</Link><button v-if="user.id !== loggedInUser.id" type="button" class="rounded-xl px-3 text-sm font-bold text-rose-700 hover:bg-rose-50" @click="confirmUserDeletion(user.id)">Eliminar</button></div></article>
                <div v-if="!filteredUsers.length" class="ui-card col-span-full p-10 text-center text-sm text-slate-500">No encontramos usuarios con esa busqueda.</div>
            </section>

            <section v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="role in filteredRoles" :key="role.id" class="ui-card flex flex-col p-5"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-extrabold uppercase tracking-wider text-indigo-600">Perfil de acceso</p><h2 class="mt-2 text-lg font-extrabold text-slate-900">{{ role.name }}</h2></div><span v-if="role.name === 'physical-sales'" class="ui-badge-neutral">Sistema</span></div><dl class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm"><div><dt class="text-slate-500">Permisos</dt><dd class="mt-1 font-extrabold text-slate-900">{{ role.permissions?.length || 0 }}</dd></div><div><dt class="text-slate-500">Usuarios</dt><dd class="mt-1 font-extrabold text-slate-900">{{ usersWithRole(role) }}</dd></div></dl><div class="mt-auto flex gap-2 pt-5"><Link :href="route('admin.roles.edit', role.id)" class="ui-secondary-button flex-1">Configurar</Link><button v-if="role.name !== 'physical-sales'" type="button" class="rounded-xl px-3 text-sm font-bold text-rose-700 hover:bg-rose-50" @click="confirmRoleDeletion(role.id)">Eliminar</button></div></article>
                <div v-if="!filteredRoles.length" class="ui-card col-span-full p-10 text-center text-sm text-slate-500">No encontramos roles con esa busqueda.</div>
            </section>
        </AdminPage>
    </AuthenticatedLayout>

    <Modal :show="confirmingUserDeletion" @close="closeModals"><div class="p-6"><h2 class="text-lg font-bold text-slate-900">Eliminar usuario</h2><p class="mt-2 text-sm text-slate-600">Esta persona perdera inmediatamente el acceso. Esta accion no se puede deshacer.</p><div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="closeModals">Cancelar</SecondaryButton><DangerButton @click="deleteUser">Eliminar usuario</DangerButton></div></div></Modal>
    <Modal :show="confirmingRoleDeletion" @close="closeModals"><div class="p-6"><h2 class="text-lg font-bold text-slate-900">Eliminar rol</h2><p class="mt-2 text-sm text-slate-600">Solo puedes eliminar roles que no esten asignados a ningun usuario.</p><div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="closeModals">Cancelar</SecondaryButton><DangerButton @click="deleteRole">Eliminar rol</DangerButton></div></div></Modal>
    <AlertModal :show="notice.show" :type="notice.type" title="Equipo y permisos" :message="notice.message" primary-text="Entendido" @primary="notice.show = false" @close="notice.show = false" />
</template>
