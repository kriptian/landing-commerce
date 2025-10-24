<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    store: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.store.name,
    max_users: props.store.max_users,
    phone: props.store.phone || '',
    plan: props.store.plan || 'emprendedor',
    plan_cycle: props.store.plan_cycle || 'mensual',
    owner_name: props.store.owner?.name || '',
    owner_email: props.store.owner?.email || '',
    owner_password: '',
    owner_password_confirmation: '',
});

const submit = () => {
    form.submit('put', route('super.stores.update', props.store.id), {
        preserveScroll: true,
        onSuccess: () => router.visit(route('super.stores.index')),
    });
};

const hasErrors = computed(() => Object.keys(form.errors || {}).length > 0);
</script>

<template>
    <Head title="Editar Tienda" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Tienda</h2>
                <Link :href="route('super.stores.index')" class="text-sm text-indigo-600 hover:underline">Volver</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div v-if="hasErrors" class="p-3 rounded bg-red-50 text-red-700 text-sm">
                                <ul class="list-disc ml-5">
                                    <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                                </ul>
                            </div>
                            <div>
                                <InputLabel for="name" value="Nombre de la Tienda" />
                                <TextInput id="name" class="mt-1 block w-full" v-model="form.name" />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel for="phone" value="Teléfono (WhatsApp) de la Tienda" />
                                <TextInput id="phone" class="mt-1 block w-full" v-model="form.phone" placeholder="57xxxxxxxxxx" />
                                <InputError :message="form.errors.phone" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Plan" />
                                    <select v-model="form.plan" class="mt-1 w-full rounded-md border-gray-300">
                                        <option value="emprendedor">Emprendedor</option>
                                        <option value="negociante">Negociante (recomendado)</option>
                                    </select>
                                    <InputError :message="form.errors.plan" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Ciclo" />
                                    <select v-model="form.plan_cycle" class="mt-1 w-full rounded-md border-gray-300">
                                        <option value="mensual">Mensual</option>
                                        <option value="anual">Anual</option>
                                    </select>
                                    <InputError :message="form.errors.plan_cycle" class="mt-2" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="owner_name" value="Nombre del Dueño" />
                                    <TextInput id="owner_name" class="mt-1 block w-full" v-model="form.owner_name" />
                                    <InputError :message="form.errors.owner_name" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel for="owner_email" value="Email del Dueño" />
                                    <TextInput id="owner_email" type="email" class="mt-1 block w-full" v-model="form.owner_email" />
                                    <InputError :message="form.errors.owner_email" class="mt-2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="owner_password" value="Nueva Contraseña (opcional)" />
                                    <TextInput id="owner_password" type="password" class="mt-1 block w-full" v-model="form.owner_password" />
                                    <InputError :message="form.errors.owner_password" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel for="owner_password_confirmation" value="Confirmación" />
                                    <TextInput id="owner_password_confirmation" type="password" class="mt-1 block w-full" v-model="form.owner_password_confirmation" />
                                    <InputError :message="form.errors.owner_password_confirmation" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <InputLabel for="max_users" value="Cupo de Usuarios" />
                                <TextInput id="max_users" type="number" min="1" class="mt-1 block w-full" v-model.number="form.max_users" />
                                <InputError :message="form.errors.max_users" class="mt-2" />
                            </div>
                            <button type="button" @click="submit" :disabled="form.processing" class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900">
                                Guardar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


