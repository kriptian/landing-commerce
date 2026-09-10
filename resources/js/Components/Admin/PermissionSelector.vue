<script setup>
import { computed } from 'vue';

const props = defineProps({ permissions: { type: Array, default: () => [] } });
const selected = defineModel({ type: Array, default: () => [] });

const definitions = [
    { title: 'Ventas y pedidos', description: 'Atencion de pedidos, caja y cambios comerciales.', terms: ['ordenes', 'ventas fisicas', 'precios y descuentos'] },
    { title: 'Catalogo e inventario', description: 'Productos, existencias, categorias, galeria y cupones.', terms: ['productos', 'inventario', 'categorias', 'cupones', 'galeria'] },
    { title: 'Informacion del negocio', description: 'Indicadores, reportes y clientes.', terms: ['dashboard', 'reportes', 'clientes'] },
    { title: 'Administracion', description: 'Usuarios, roles y tareas administrativas.', terms: ['usuarios', 'gastos'] },
];

const groups = computed(() => {
    const known = new Set();
    const grouped = definitions.map((definition) => ({
        ...definition,
        permissions: props.permissions.filter((permission) => definition.terms.some((term) => permission.name.includes(term))),
    })).filter((group) => group.permissions.length);
    grouped.forEach((group) => group.permissions.forEach((permission) => known.add(permission.id)));
    const remaining = props.permissions.filter((permission) => !known.has(permission.id));
    if (remaining.length) grouped.push({ title: 'Otros accesos', description: 'Permisos adicionales disponibles para este rol.', permissions: remaining });
    return grouped;
});

const isGroupSelected = (group) => group.permissions.every((permission) => selected.value.includes(permission.id));
const toggleGroup = (group) => {
    const ids = group.permissions.map((permission) => permission.id);
    selected.value = isGroupSelected(group)
        ? selected.value.filter((id) => !ids.includes(id))
        : [...new Set([...selected.value, ...ids])];
};
</script>

<template>
    <div class="space-y-4">
        <article v-for="group in groups" :key="group.title" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <div class="flex items-start justify-between gap-4"><div><h3 class="font-extrabold text-slate-900">{{ group.title }}</h3><p class="mt-1 text-sm text-slate-500">{{ group.description }}</p></div><button type="button" class="shrink-0 text-xs font-extrabold text-indigo-700" @click="toggleGroup(group)">{{ isGroupSelected(group) ? 'Quitar todos' : 'Seleccionar todos' }}</button></div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2"><label v-for="permission in group.permissions" :key="permission.id" class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition" :class="selected.includes(permission.id) ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 hover:border-indigo-200'"><input v-model="selected" type="checkbox" :value="permission.id" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /><span class="text-sm font-semibold capitalize text-slate-700">{{ permission.name }}</span></label></div>
        </article>
    </div>
</template>
