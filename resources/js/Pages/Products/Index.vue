<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    products: Array,
});

// Función para eliminar un producto
const destroy = (id) => {
    if (confirm('¿Estás seguro de que querés eliminar este producto?')) {
        router.delete(route('admin.products.destroy', id));
    }
};
</script>

<template>
    <Head title="Gestionar Productos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestionar Productos</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">

                        <div class="flex justify-between items-center mb-4">
                            <p>Aquí podés crear, editar y eliminar los productos de tu tienda.</p>
                            <Link :href="route('admin.products.create')" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                Crear Nuevo Producto
                            </Link>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="products.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No hay productos creados.</td>
                                </tr>
                                <tr v-for="product in products" :key="product.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ product.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">$ {{ product.price }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ product.quantity }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ product.category ? product.category.name : 'Sin Categoría' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">

                                        <Link :href="route('admin.products.edit', product.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>

                                        <button @click="destroy(product.id)" class="ml-4 text-red-600 hover:text-red-900">
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