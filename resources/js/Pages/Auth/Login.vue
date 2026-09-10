<script setup>
import FormField from '@/Components/FormField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ canResetPassword: Boolean, status: String });

const showPassword = ref(false);
const form = useForm({ store_name: '', email: '', password: '' });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout title="Bienvenido de nuevo" description="Ingresa a la tienda que administras para continuar.">
        <Head title="Iniciar sesion" />
        <div v-if="status" class="ui-status-success mb-5" role="status">{{ status }}</div>

        <form class="space-y-5" @submit.prevent="submit">
            <FormField id="store_name" label="Nombre de tu tienda" :error="form.errors.store_name" help="Es el nombre con el que registraste el negocio." required v-slot="field">
                <input id="store_name" v-model="form.store_name" type="text" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" autocomplete="organization" placeholder="Ej. Tienda La Esquina" autofocus required />
            </FormField>
            <FormField id="email" label="Correo electronico" :error="form.errors.email" required v-slot="field">
                <input id="email" v-model="form.email" type="email" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" autocomplete="username" placeholder="tu@correo.com" required />
            </FormField>
            <FormField id="password" label="Contrasena" :error="form.errors.password" required v-slot="field">
                <div class="relative">
                    <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="ui-input pr-24" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" :aria-invalid="field.invalid" autocomplete="current-password" required />
                    <button type="button" class="absolute inset-y-0 right-3 top-1.5 text-xs font-bold text-indigo-700" @click="showPassword = !showPassword">{{ showPassword ? 'Ocultar' : 'Mostrar' }}</button>
                </div>
            </FormField>

            <div class="flex justify-end">
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Olvide mi contrasena</Link>
            </div>
            <button type="submit" class="ui-primary-button w-full" :disabled="form.processing">{{ form.processing ? 'Ingresando...' : 'Entrar a mi tienda' }}</button>
        </form>

        <div class="mt-6 border-t border-slate-200 pt-5 text-center text-sm text-slate-600">
            ¿Aun no tienes tienda? <Link :href="route('register')" class="font-bold text-indigo-700 hover:text-indigo-900">Crear una gratis</Link>
        </div>
    </GuestLayout>
</template>
