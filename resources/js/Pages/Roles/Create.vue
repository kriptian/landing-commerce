<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import PermissionSelector from '@/Components/Admin/PermissionSelector.vue';
import FormField from '@/Components/FormField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ permissions: { type: Array, default: () => [] } });
const form = useForm({ name: '', permissions: [] });
</script>

<template><Head title="Nuevo rol" /><AuthenticatedLayout><AdminPage width="compact"><PageHeader eyebrow="Equipo y permisos" title="Crear rol" description="Define una responsabilidad reconocible y concede solamente los accesos necesarios."><template #actions><Link :href="`${route('admin.users.index')}?tab=roles`" class="ui-secondary-button">Volver a roles</Link></template></PageHeader><form class="space-y-6" @submit.prevent="form.post(route('admin.roles.store'))"><FormSection number="1" title="Identifica el rol" description="Usa un nombre que describa la responsabilidad, por ejemplo Vendedor o Encargado de inventario."><FormField id="name" label="Nombre del rol" :error="form.errors.name" required><template #default="field"><input id="name" v-model="form.name" type="text" class="ui-input" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" placeholder="Ej. Encargado de inventario" autofocus /></template></FormField></FormSection><FormSection number="2" title="Elige sus permisos" description="Los accesos estan agrupados por tarea para que puedas revisar el alcance antes de guardar."><PermissionSelector v-model="form.permissions" :permissions="permissions" /><p v-if="form.errors.permissions" class="ui-error">{{ form.errors.permissions }}</p></FormSection><div class="sticky bottom-4 flex justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur"><Link :href="`${route('admin.users.index')}?tab=roles`" class="ui-secondary-button">Cancelar</Link><button type="submit" class="ui-primary-button" :disabled="form.processing">{{ form.processing ? 'Creando...' : 'Crear rol' }}</button></div></form></AdminPage></AuthenticatedLayout></template>
