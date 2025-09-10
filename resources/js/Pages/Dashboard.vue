<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
                    
                    <div v-if="$page.props.auth.can.includes('gestionar usuarios')" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4">Gestionar Usuarios</h3>
                        <p class="text-gray-600 mb-4">Administrá los usuarios y sus roles en el sistema.</p>
                        <Link :href="route('admin.users.index')" class="font-bold text-blue-600 hover:underline">Ir a Usuarios &rarr;</Link>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>