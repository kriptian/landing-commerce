<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import PermissionSelector from '@/Components/Admin/PermissionSelector.vue';
import FormField from '@/Components/FormField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ role: Object, permissions: { type: Array, default: () => [] }, rolePermissions: { type: Array, default: () => [] } });
const form = useForm({ name: props.role.name, permissions: props.rolePermissions });
</script>

<template><Head title="Editar rol" /><AuthenticatedLayout><AdminPage width="compact"><PageHeader eyebrow="Equipo y permisos" :title="`Configurar ${role.name}`" description="Revisa el nombre y los accesos de este perfil. Los cambios aplican a todos los usuarios que lo tengan asignado."><template #actions><Link :href="`${route('admin.users.index')}?tab=roles`" class="ui-secondary-button">Volver a roles</Link></template></PageHeader><form class="space-y-6" @submit.prevent="form.put(route('admin.roles.update', role.id))"><FormSection number="1" title="Identifica el rol" description="Mantener nombres claros facilita asignar correctamente el acceso."><FormField id="name" label="Nombre del rol" :error="form.errors.name" required><template #default="field"><input id="name" v-model="form.name" type="text" class="ui-input" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" /></template></FormField></FormSection><FormSection number="2" title="Revisa sus permisos" description="Selecciona solo las tareas que esta persona necesita realizar."><PermissionSelector v-model="form.permissions" :permissions="permissions" /><p v-if="form.errors.permissions" class="ui-error">{{ form.errors.permissions }}</p></FormSection><div class="sticky bottom-4 flex justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur"><Link :href="`${route('admin.users.index')}?tab=roles`" class="ui-secondary-button">Cancelar</Link><button type="submit" class="ui-primary-button" :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar cambios' }}</button></div></form></AdminPage></AuthenticatedLayout></template>
