<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    categories: Array,
});

const destroy = (id) => {
    if (confirm('¿Estás seguro de que querés eliminar esta categoría?')) {
        // Usamos el nombre de ruta correcto
        router.delete(route('admin.categories.destroy', id), {
            preserveScroll: true, 
        });
    }
};
</script>

<template>
    <Head title="Gestionar Categorías" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestionar Categorías</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">

                        <div class="flex justify-between items-center mb-4">
                            <p>Aquí podés crear, editar y eliminar las categorías de tus productos.</p>

                            <Link :href="route('admin.categories.create')" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                Crear Nueva
                            </Link>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="categories.length === 0">
                                    <td colspan="3" class="px-6 py-4 text-center text-gray-500">No hay categorías creadas.</td>
                                </tr>
                                <tr v-for="category in categories" :key="category.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ category.id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ category.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">

                                        <Link :href="route('admin.categories.edit', category.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>

                                        <button @click="destroy(category.id)" class="ml-4 text-red-600 hover:text-red-900">
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
    </AuthenticatedLayout>
</template>