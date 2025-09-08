<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    category: Object,
});

const form = useForm({
    name: props.category.name,
});

const submit = () => {
    form.put(route('admin.categories.update', props.category.id));

};
</script>

<template>
    <Head title="Editar Categoría" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Categoría: {{ category.name }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">

                        <form @submit.prevent="submit">
                            <div>
                                <label for="name" class="block font-medium text-sm text-gray-700">Nombre</label>
                                <input id="name" v-model="form.name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required autofocus>
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                                    Actualizar
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>