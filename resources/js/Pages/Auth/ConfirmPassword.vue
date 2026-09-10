<script setup>
import FormField from '@/Components/FormField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm'), { onFinish: () => form.reset() });
</script>

<template>
    <GuestLayout title="Confirma que eres tu" description="Esta accion afecta informacion sensible. Ingresa nuevamente tu contrasena para continuar.">
        <Head title="Confirmar contrasena" />
        <form class="space-y-5" @submit.prevent="submit">
            <FormField id="password" label="Contrasena actual" :error="form.errors.password" required v-slot="field">
                <input id="password" v-model="form.password" type="password" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="current-password" autofocus required />
            </FormField>
            <button type="submit" class="ui-primary-button w-full" :disabled="form.processing">{{ form.processing ? 'Confirmando...' : 'Confirmar y continuar' }}</button>
        </form>
    </GuestLayout>
</template>
