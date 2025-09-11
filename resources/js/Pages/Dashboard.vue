<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Le decimos al componente que va a recibir la información de la tienda
defineProps({
    store: Object,
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">¡Bienvenido a tu panel de administración!</div>
                </div>

                <div v-if="store" class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 my-8 text-center">
                    <p class="font-semibold">¡Compartí tu tienda con el mundo!</p>
                    <p class="text-sm mt-2">La URL pública de tu catálogo es:</p>
                    
                    <a :href="route('catalogo.index', { store: store.slug })" target="_blank" class="block font-mono text-sm bg-white px-3 py-2 rounded d-block mt-2 hover:bg-blue-100 border border-blue-200">
                        {{ route('catalogo.index', { store: store.slug }) }}
                    </a>
                </div>

                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">Gestionar Productos</h3>
                        <p class="text-gray-600 mb-4">Creá, editá y eliminá los productos de tu tienda.</p>
                        <Link :href="route('admin.products.index')" class="font-bold text-blue-600 hover:underline">Ir a Productos &rarr;</Link>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">Gestionar Categorías</h3>
                        <p class="text-gray-600 mb-4">Organizá tus productos en diferentes categorías.</p>
                        <Link :href="route('admin.categories.index')" class="font-bold text-blue-600 hover:underline">Ir a Categorías &rarr;</Link>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">Gestionar Usuarios</h3>
                        <p class="text-gray-600 mb-4">Administrá los usuarios y sus roles en el sistema.</p>
                        <Link :href="route('admin.users.index')" class="font-bold text-blue-600 hover:underline">Ir a Usuarios &rarr;</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>