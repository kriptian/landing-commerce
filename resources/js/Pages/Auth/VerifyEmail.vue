<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ status: String });
const form = useForm({});
const sent = computed(() => props.status === 'verification-link-sent');
const submit = () => form.post(route('verification.send'));
</script>

<template>
    <GuestLayout title="Revisa tu correo" description="Enviamos un enlace para confirmar tu direccion de correo. Abre el mensaje y pulsa el boton de verificacion.">
        <Head title="Verificar correo" />
        <div v-if="sent" class="ui-status-success mb-5" role="status">Enviamos un nuevo enlace de verificacion.</div>
        <form class="space-y-4" @submit.prevent="submit">
            <button type="submit" class="ui-primary-button w-full" :disabled="form.processing">{{ form.processing ? 'Enviando...' : 'Reenviar correo de verificacion' }}</button>
            <Link :href="route('logout')" method="post" as="button" class="ui-secondary-button w-full">Cerrar sesion</Link>
        </form>
    </GuestLayout>
</template>
