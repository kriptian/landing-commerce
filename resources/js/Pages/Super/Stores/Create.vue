<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    name: '',
    phone: '',
    owner_name: '',
    owner_email: '',
    owner_password: '',
    owner_password_confirmation: '',
    max_users: 3,
    plan: 'negociante',
    plan_cycle: 'mensual',
});

const submit = () => {
    form.post(route('super.stores.store'));
};
</script>

<template>
    <Head title="Crear Tienda" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear Tienda</h2>
                <Link :href="route('super.stores.index')" class="text-sm text-indigo-600 hover:underline">Volver</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <InputLabel for="name" value="Nombre de la Tienda" />
                                <TextInput id="name" class="mt-1 block w-full" v-model="form.name" />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>
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
                            <div>
                                <InputLabel for="phone" value="Teléfono (WhatsApp) de la Tienda" />
                                <TextInput id="phone" class="mt-1 block w-full" v-model="form.phone" placeholder="57xxxxxxxxxx" />
                                <InputError :message="form.errors.phone" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="owner_password" value="Contraseña" />
                                    <TextInput id="owner_password" type="password" class="mt-1 block w-full" v-model="form.owner_password" />
                                    <InputError :message="form.errors.owner_password" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel for="owner_password_confirmation" value="Confirmar Contraseña" />
                                    <TextInput id="owner_password_confirmation" type="password" class="mt-1 block w-full" v-model="form.owner_password_confirmation" />
                                </div>
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
                            <div>
                                <InputLabel for="max_users" value="Cupo de Usuarios" />
                                <TextInput id="max_users" type="number" min="1" class="mt-1 block w-full" v-model.number="form.max_users" />
                                <InputError :message="form.errors.max_users" class="mt-2" />
                            </div>
                            <PrimaryButton :disabled="form.processing">Crear</PrimaryButton>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


