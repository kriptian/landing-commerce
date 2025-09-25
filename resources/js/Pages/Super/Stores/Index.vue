<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    stores: Array,
});

const destroyStore = (id) => {
    if (confirm('¿Eliminar esta tienda? Esta acción es irreversible.')) {
        router.delete(route('super.stores.destroy', id));
    }
};
</script>

<template>
    <Head title="Tiendas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tiendas</h2>
                <Link :href="route('super.stores.create')">
                    <PrimaryButton>Crear Tienda</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usuarios</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="store in stores" :key="store.id">
                                    <td class="px-4 py-2">{{ store.name }}</td>
                                    <td class="px-4 py-2">{{ store.users_count }}</td>
                                    <td class="px-4 py-2 space-x-2">
                                        <Link :href="route('super.stores.edit', store.id)" class="text-indigo-600 hover:underline">Editar</Link>
                                        <DangerButton @click="destroyStore(store.id)">Eliminar</DangerButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    
</template>


