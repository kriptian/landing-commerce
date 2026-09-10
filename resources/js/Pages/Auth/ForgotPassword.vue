<script setup>
import FormField from '@/Components/FormField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: String });
const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <GuestLayout title="Recupera tu acceso" description="Escribe el correo de tu cuenta y te enviaremos un enlace para crear una nueva contrasena.">
        <Head title="Recuperar contrasena" />
        <div v-if="status" class="ui-status-success mb-5" role="status">{{ status }}</div>
        <form class="space-y-5" @submit.prevent="submit">
            <FormField id="email" label="Correo electronico" :error="form.errors.email" required v-slot="field">
                <input id="email" v-model="form.email" type="email" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="username" placeholder="tu@correo.com" autofocus required />
            </FormField>
            <button type="submit" class="ui-primary-button w-full" :disabled="form.processing">{{ form.processing ? 'Enviando...' : 'Enviar enlace de recuperacion' }}</button>
        </form>
        <Link :href="route('login')" class="mt-5 block text-center text-sm font-bold text-indigo-700">Volver a iniciar sesion</Link>
    </GuestLayout>
</template>
