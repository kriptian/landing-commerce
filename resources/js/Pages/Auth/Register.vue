<script setup>
import FormField from '@/Components/FormField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({ name: '', store_name: '', email: '', phone: '', password: '', password_confirmation: '' });
const page = usePage();
const created = computed(() => page.props.flash?.store_created);
const submit = () => form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout title="Crea tu tienda gratis" description="Empieza con el plan Emprendedor. No necesitas tarjeta y no pagas comisiones por venta." wide>
        <Head title="Crear tienda" />
        <div v-if="created" class="text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-2xl font-black text-emerald-700">✓</div>
            <h3 class="mt-5 text-xl font-bold text-slate-950">Tu tienda {{ created.store_name }} esta lista</h3>
            <p class="mt-2 text-sm leading-6 text-slate-600">Ya puedes ingresar con <strong>{{ created.email }}</strong> y completar la configuracion inicial.</p>
            <Link :href="route('login')" class="ui-primary-button mt-6 w-full">Entrar a mi tienda</Link>
        </div>
        <form v-else class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
                <FormField id="name" label="Tu nombre" :error="form.errors.name" required v-slot="field">
                    <input id="name" v-model="form.name" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="name" placeholder="Como debemos llamarte" autofocus required />
                </FormField>
                <FormField id="store_name" label="Nombre del negocio" :error="form.errors.store_name" help="Sera el nombre visible de tu tienda." required v-slot="field">
                    <input id="store_name" v-model="form.store_name" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="organization" placeholder="Ej. Moda Luna" required />
                </FormField>
                <FormField id="email" label="Correo electronico" :error="form.errors.email" required v-slot="field">
                    <input id="email" v-model="form.email" type="email" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="username" placeholder="tu@correo.com" required />
                </FormField>
                <FormField id="phone" label="WhatsApp" :error="form.errors.phone" help="Opcional. Podremos ayudarte con la configuracion." v-slot="field">
                    <input id="phone" v-model="form.phone" type="tel" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="tel" placeholder="Ej. 320 123 4567" />
                </FormField>
                <FormField id="password" label="Crea una contrasena" :error="form.errors.password" help="Usa al menos 8 caracteres." required v-slot="field">
                    <input id="password" v-model="form.password" type="password" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="new-password" required />
                </FormField>
                <FormField id="password_confirmation" label="Repite la contrasena" :error="form.errors.password_confirmation" required v-slot="field">
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" autocomplete="new-password" required />
                </FormField>
            </div>
            <div v-if="form.errors.error" class="ui-status-error" role="alert">{{ form.errors.error }}</div>
            <div class="rounded-xl bg-indigo-50 p-4 text-sm text-indigo-950"><strong>Incluido desde el inicio:</strong> catalogo online, productos, categorias, variantes y pedidos por WhatsApp.</div>
            <button type="submit" class="ui-primary-button w-full" :disabled="form.processing">{{ form.processing ? 'Creando tu tienda...' : 'Crear mi tienda gratis' }}</button>
        </form>
        <p v-if="!created" class="mt-6 text-center text-sm text-slate-600">¿Ya tienes una cuenta? <Link :href="route('login')" class="font-bold text-indigo-700">Iniciar sesion</Link></p>
    </GuestLayout>
</template>
