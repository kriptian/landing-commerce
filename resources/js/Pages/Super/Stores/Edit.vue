<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
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
    promo_active: props.store.promo_active || false,
    promo_discount_percent: props.store.promo_discount_percent || 0,
    owner_name: props.store.owner?.name || '',
    owner_email: props.store.owner?.email || '',
    owner_password: '',
    owner_password_confirmation: '',
});

const submit = () => {
    form.post(route('super.stores.update', props.store.id));
};
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
                            <div>
                                <InputLabel for="promo" value="Promoción Global" />
                                <div class="mt-1 flex items-center gap-4">
                                    <label class="inline-flex items-center gap-2">
                                        <input id="promo" type="checkbox" v-model="form.promo_active" class="rounded border-gray-300">
                                        Activa
                                    </label>
                                    <TextInput type="number" min="1" max="90" class="w-24" v-model.number="form.promo_discount_percent" placeholder="%" />
                                    <span class="text-sm text-gray-500">Se aplicará a todos los productos sin promo propia.</span>
                                </div>
                                <InputError :message="form.errors.promo_discount_percent" class="mt-2" />
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
                                </div>
                                <div>
                                    <InputLabel for="owner_password_confirmation" value="Confirmación" />
                                    <TextInput id="owner_password_confirmation" type="password" class="mt-1 block w-full" v-model="form.owner_password_confirmation" />
                                </div>
                            </div>
                            <div>
                                <InputLabel for="max_users" value="Cupo de Usuarios" />
                                <TextInput id="max_users" type="number" min="1" class="mt-1 block w-full" v-model.number="form.max_users" />
                                <InputError :message="form.errors.max_users" class="mt-2" />
                            </div>
                            <PrimaryButton :disabled="form.processing">Guardar</PrimaryButton>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


