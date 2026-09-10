<script setup>
import FormField from '@/Components/FormField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ email: { type: String, required: true }, token: { type: String, required: true } });
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout title="Crea una nueva contrasena" description="Elige una clave que no uses en otros servicios.">
        <Head title="Nueva contrasena" />
        <form class="space-y-5" @submit.prevent="submit">
            <FormField id="email" label="Correo electronico" :error="form.errors.email" required v-slot="field">
                <input id="email" v-model="form.email" type="email" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="username" required />
            </FormField>
            <FormField id="password" label="Nueva contrasena" :error="form.errors.password" help="Usa al menos 8 caracteres." required v-slot="field">
                <input id="password" v-model="form.password" type="password" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="new-password" autofocus required />
            </FormField>
            <FormField id="password_confirmation" label="Confirma la contrasena" :error="form.errors.password_confirmation" required v-slot="field">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="new-password" required />
            </FormField>
            <button type="submit" class="ui-primary-button w-full" :disabled="form.processing">{{ form.processing ? 'Actualizando...' : 'Guardar nueva contrasena' }}</button>
        </form>
    </GuestLayout>
</template>
