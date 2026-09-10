<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import { safeRoute } from '@/utils/safeRoute';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import { Link } from '@inertiajs/vue3';

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
const showErrors = ref(false);
const errorMessages = ref([]);

const submit = () => {
    const url = safeRoute('admin.categories.store', {}, '/admin/categories');
    form.post(url, {
        onSuccess: () => { 
            showSaved.value = true; 
            showErrors.value = false;
            errorMessages.value = [];
        },
        onError: (errors) => {
            // Error silenciado
            // Construir mensajes de error legibles en español
            const msgs = [];
            for (const [key, val] of Object.entries(form.errors)) {
                let message = '';
                if (typeof val === 'string') {
                    message = val;
                } else if (Array.isArray(val)) {
                    message = val.join(', ');
                } else {
                    message = String(val);
                }
                
                // Traducir mensajes técnicos a mensajes amigables
                if (message.includes('has already been taken')) {
                    // Extraer el nombre del campo para hacer el mensaje más claro
                    if (key.includes('subcategories') && key.includes('children')) {
                        const match = key.match(/subcategories\.(\d+)\.children\.(\d+)\.name/);
                        if (match) {
                            const subIndex = parseInt(match[1]) + 1;
                            const childIndex = parseInt(match[2]) + 1;
                            message = `El nombre del subnivel ${childIndex} de la subcategoría ${subIndex} ya existe en tu tienda.`;
                        } else {
                            message = 'Uno de los nombres de subnivel ya existe en tu tienda.';
                        }
                    } else if (key.includes('subcategories')) {
                        const match = key.match(/subcategories\.(\d+)\.name/);
                        if (match) {
                            const subIndex = parseInt(match[1]) + 1;
                            message = `El nombre de la subcategoría ${subIndex} ya existe en tu tienda.`;
                        } else {
                            message = 'Uno de los nombres de subcategoría ya existe en tu tienda.';
                        }
                    } else {
                        message = message.replace('has already been taken', 'ya existe en tu tienda');
                    }
                }
                
                msgs.push(message);
            }
            errorMessages.value = msgs;
            showErrors.value = msgs.length > 0;
        },
    });
};
</script>

<template>
    <Head title="Crear categoría" />

    <AuthenticatedLayout>
        <AdminPage>
            <PageHeader eyebrow="Catalogo" title="Nueva categoria" description="Crea primero un grupo general y agrega divisiones solo cuando ayuden al cliente a encontrar productos.">
                <template #actions><Link :href="route('admin.categories.index')" class="ui-secondary-button">Cancelar</Link></template>
            </PageHeader>

            <form class="space-y-6" @submit.prevent="submit">
                <div v-if="showErrors && errorMessages.length" class="ui-status-error" role="alert"><p class="font-bold">Revisa estos puntos:</p><ul class="mt-2 list-disc space-y-1 pl-5"><li v-for="msg in errorMessages" :key="msg">{{ msg }}</li></ul></div>
                <FormSection number="1" title="Categoria principal" description="Usa un nombre breve que tus clientes reconozcan facilmente.">
                    <label for="name" class="ui-label">Nombre</label>
                    <input id="name" v-model="form.name" type="text" class="ui-input" :class="{ 'ui-input-error': form.errors.name }" placeholder="Ej. Ropa, Hogar o Accesorios" autofocus required>
                    <p v-if="form.errors.name" class="ui-error">{{ form.errors.name }}</p>
                </FormSection>

                <FormSection number="2" title="Organizacion opcional" description="Puedes dejar esta parte vacia y agregar subcategorias mas adelante.">
                    <div v-if="!form.subcategories.length" class="rounded-xl border-2 border-dashed border-slate-200 p-8 text-center"><p class="font-semibold text-slate-800">¿Necesitas dividir esta categoria?</p><p class="mt-1 text-sm text-slate-500">Por ejemplo: Ropa > Mujer > Camisetas.</p></div>
                    <div class="space-y-4">
                        <div v-for="(subcategory, index) in form.subcategories" :key="index" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start gap-3"><div class="min-w-0 flex-1"><label :for="`subcategory-${index}`" class="ui-label">Subcategoria {{ index + 1 }}</label><input :id="`subcategory-${index}`" v-model="subcategory.name" class="ui-input" placeholder="Ej. Mujer" /></div><button type="button" class="mt-7 rounded-lg p-2 text-sm font-bold text-rose-700 hover:bg-rose-100" @click="removeSubcategory(index)">Quitar</button></div>
                            <button type="button" class="mt-3 text-sm font-bold text-indigo-700" @click="toggleChildren(index)">{{ subcategory._showChildren ? 'Ocultar tercer nivel' : '+ Agregar tercer nivel' }}</button>
                            <div v-if="subcategory._showChildren" class="mt-3 space-y-2 border-l-2 border-indigo-200 pl-4">
                                <div v-for="(child, childIndex) in subcategory.children" :key="childIndex" class="flex gap-2"><input v-model="child.name" class="ui-input mt-0" placeholder="Ej. Camisetas" /><button type="button" class="rounded-lg px-3 text-rose-700 hover:bg-rose-100" aria-label="Quitar tercer nivel" @click="removeChildFromSubcategory(index, childIndex)">Quitar</button></div>
                                <button type="button" class="ui-secondary-button" @click="addChildToSubcategory(index)">+ Otro tercer nivel</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="ui-secondary-button mt-4" @click="addSubcategory">+ Agregar subcategoria</button>
                </FormSection>

                <div class="sticky bottom-3 z-20 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur"><button type="submit" class="ui-primary-button" :disabled="form.processing">{{ form.processing ? 'Creando...' : 'Crear categoria' }}</button></div>
            </form>
        </AdminPage>
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
