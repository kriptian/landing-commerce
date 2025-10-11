<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

const form = useForm({
    name: '', // El nombre de la categoría principal
    subcategories: [], // [{ name: string, children?: [{ name: string }] }]
});

// Función para añadir un nuevo campo de subcategoría
const addSubcategory = () => {
    form.subcategories.push({ name: '', children: [], _showChildren: false });
};

// Función para eliminar un campo de subcategoría
const removeSubcategory = (index) => {
    form.subcategories.splice(index, 1);
};

// Gestión de hijas (segundo nivel) durante la creación
const toggleChildren = (index) => {
    const sc = form.subcategories[index];
    if (!sc) return;
    if (!Array.isArray(sc.children)) sc.children = [];
    sc._showChildren = !sc._showChildren;
};
const addChildToSubcategory = (index) => {
    const sc = form.subcategories[index];
    if (!sc) return;
    if (!Array.isArray(sc.children)) sc.children = [];
    sc.children.push({ name: '' });
};
const removeChildFromSubcategory = (subIndex, childIndex) => {
    const sc = form.subcategories[subIndex];
    if (!sc || !Array.isArray(sc.children)) return;
    sc.children.splice(childIndex, 1);
};

const showSaved = ref(false);
const submit = () => {
    form.post(route('admin.categories.store'), {
        onSuccess: () => { showSaved.value = true; },
    });
};
</script>

<template>
    <Head title="Crear categoría" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear categoría</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit">
                            <div class="mb-6 border-b pb-6">
                                <label for="name" class="block font-medium text-lg text-gray-800">Nombre de la Categoría</label>
                                <input id="name" v-model="form.name" type="text" class="block mt-2 w-full rounded-md shadow-sm border-gray-300" required>
                                <p v-if="form.errors.name" class="text-sm text-red-600 mt-2">{{ form.errors.name }}</p>
                            </div>

                            <div class="mt-6">
                                <h3 class="font-medium text-lg text-gray-800">Estructura de Subcategorías</h3>
                                <p class="text-sm text-gray-500">Definí subcategorías y un subnivel adicional (máximo 3 niveles en total).</p>

                                <div v-for="(subcategory, index) in form.subcategories" :key="index" class="mt-4 border rounded-lg overflow-hidden">
                                    <div class="flex items-center gap-4 p-3">
                                        <input 
                                            v-model="subcategory.name" 
                                            type="text" 
                                            :placeholder="`Nombre de la Subcategoría ${index + 1}`"
                                            class="block w-full rounded-md shadow-sm border-gray-300"
                                        >
                                        <button @click.prevent="removeSubcategory(index)" type="button" class="bg-red-500 text-white font-bold p-2 rounded-full h-8 w-8 flex items-center justify-center">
                                            -
                                        </button>
                                    </div>
                                    <div class="bg-gray-50 px-3 pb-3">
                                        <button type="button" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800" @click.prevent="toggleChildren(index)">
                                            <span class="w-5 h-5 inline-flex items-center justify-center rounded-full border border-gray-300 text-gray-700">{{ subcategory._showChildren ? 'v' : '>' }}</span>
                                            <span>{{ subcategory._showChildren ? 'Colapsar subniveles' : 'Expandir subniveles' }}</span>
                                        </button>
                                        <div v-if="subcategory._showChildren" class="mt-3 space-y-2">
                                            <div v-for="(child, cidx) in (subcategory.children || [])" :key="cidx" class="flex items-center gap-3">
                                                <input v-model="child.name" type="text" class="block w-full rounded-md shadow-sm border-gray-300" :placeholder="`Subnivel ${cidx+1}`" />
                                                <button @click.prevent="removeChildFromSubcategory(index, cidx)" type="button" class="bg-red-500 text-white font-bold p-2 rounded-full h-8 w-8 flex items-center justify-center">-
                                                </button>
                                            </div>
                                            <button type="button" class="mt-1 inline-flex items-center gap-2 border border-green-500 text-green-600 py-1 px-3 rounded hover:bg-green-50" @click.prevent="addChildToSubcategory(index)">
                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="3" width="11" height="15" rx="2" ry="2" stroke-width="1.5" /><circle cx="18" cy="18" r="3" stroke-width="1.5" /><path stroke-linecap="round" stroke-width="1.5" d="M18 16.5v3M16.5 18h3" /></svg>
                                                Agregar subnivel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="form.errors.subcategories" class="text-sm text-red-600 mt-2">{{ form.errors.subcategories }}</p>


                                <button @click.prevent="addSubcategory" type="button" class="mt-4 inline-flex items-center gap-2 border border-green-500 text-green-600 py-2 px-4 rounded hover:bg-green-50">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="3" width="11" height="15" rx="2" ry="2" stroke-width="1.5" /><circle cx="18" cy="18" r="3" stroke-width="1.5" /><path stroke-linecap="round" stroke-width="1.5" d="M18 16.5v3M16.5 18h3" /></svg>
                                    Añadir Subcategoría
                                </button>
                            </div>

                            <div class="flex items-center justify-end mt-8 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 inline-flex items-center gap-2">
                                    <span class="text-lg">+</span>
                                    <span>Crear</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <AlertModal
        :show="showSaved"
        type="success"
        title="Categorías"
        message="¡Categoría creada con éxito!"
        primary-text="Entendido"
        @primary="showSaved=false; form.reset()"
        @close="showSaved=false; form.reset()"
    />
</template>