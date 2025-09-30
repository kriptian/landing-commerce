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
                        <div class="overflow-x-auto">
                        <table class="min-w-[600px] w-full divide-y divide-gray-200 table-auto">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 sm:px-4 sm:py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-2 text-left text-xs font-medium text-gray-500 uppercase">Usuarios</th>
                                    <th class="px-3 py-2 sm:px-4 sm:py-2 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="store in stores" :key="store.id">
                                    <td class="px-3 py-3 sm:px-4 sm:py-2">{{ store.name }}</td>
                                    <td class="px-3 py-3 sm:px-4 sm:py-2">{{ store.users_count }}</td>
                                    <td class="px-3 py-3 sm:px-4 sm:py-2 space-x-2">
                                        <Link :href="route('super.stores.edit', store.id)" class="text-indigo-600 hover:underline inline-flex items-center gap-1">
                                            <svg class="w-5 h-5 sm:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.862 3.487a2.25 2.25 0 113.182 3.182L9.428 17.284a3.75 3.75 0 01-1.582.992l-2.685.805a.75.75 0 01-.93-.93l.805-2.685a3.75 3.75 0 01.992-1.582L16.862 3.487z"/><path d="M15.75 4.5l3.75 3.75"/></svg>
                                            <span class="hidden sm:inline">Editar</span>
                                        </Link>
                                        <DangerButton @click="destroyStore(store.id)" class="inline-flex items-center gap-1">
                                            <svg class="w-5 h-5 sm:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/></svg>
                                            <span class="hidden sm:inline">Eliminar</span>
                                        </DangerButton>
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
    
</template>


