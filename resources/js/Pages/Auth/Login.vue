<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2 relative">
        <!-- HERO para móviles -->
        <section class="lg:hidden w-full bg-gradient-to-br from-blue-900 via-blue-800 to-cyan-700 text-white px-6 py-10">
            <h1 class="text-3xl font-extrabold leading-tight">Tu negocio en marcha</h1>
            <p class="mt-3 text-cyan-100">Crea tu tienda, gestiona inventario y multiplica tus ventas desde una plataforma simple y potente.</p>
            <ul class="mt-5 space-y-2 text-cyan-50 text-sm">
                <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-white"></span>Catálogo público con dominio personalizado</li>
                <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-white"></span>Control de inventario con mínimos y alertas</li>
                <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-white"></span>Roles y permisos por tienda y equipo</li>
            </ul>
            <a href="https://wa.me/573208204198?text=Hola%20quiero%20crear%20mi%20propia%20tienda%21" target="_blank" rel="noopener" class="inline-flex items-center mt-6 px-5 py-2.5 bg-white text-blue-800 font-semibold rounded-lg shadow hover:bg-blue-50 border border-white/60">
                Habla con un asesor
            </a>
        </section>
        <!-- Lado izquierdo: propuesta de valor -->
        <section class="relative hidden lg:flex items-center justify-center bg-gradient-to-br from-blue-900 via-blue-800 to-cyan-700 text-white p-10">
            <!-- Quitamos marca de fondo para mantener limpio -->
            <div class="relative z-10 max-w-xl">
                <h1 class="text-4xl font-extrabold leading-tight">Tu negocio en marcha</h1>
                <p class="mt-4 text-cyan-100 text-lg">Crea tu tienda, gestiona inventario y multiplica tus ventas desde una plataforma simple y potente.</p>
                <ul class="mt-6 space-y-3 text-cyan-50">
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-white"></span>
                        Catálogo público con dominio personalizado
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-white"></span>
                        Control de inventario con mínimos y alertas
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-white"></span>
                        Roles y permisos por tienda y equipo
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-white"></span>
                        Reportes de ventas y promociones con descuento
                    </li>
                </ul>
                <a href="https://wa.me/573208204198?text=Hola%20quiero%20crear%20mi%20propia%20tienda%21" target="_blank" rel="noopener" class="inline-flex items-center mt-8 px-5 py-3 bg-white text-blue-800 font-semibold rounded-lg shadow hover:bg-blue-50 border border-white/60">
                    Habla con un asesor
                </a>
            </div>
            <div class="pointer-events-none absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_20%_20%,white_0,transparent_40%),radial-gradient(circle_at_80%_0,white_0,transparent_40%)]"></div>
        </section>

        <!-- Lado derecho: formulario -->
        <section class="flex items-center justify-center p-6 lg:p-10 bg-gray-50">
            <div class="w-full max-w-md bg-white shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100">
                <Head title="Iniciar sesión" />

                <!-- Logo encima del título (en todas las vistas) -->
                <img src="/images/digitalsolution-logo.png" alt="digitalsolution.com" class="block mx-auto mb-4 h-[70px] sm:h-20 lg:h-[115px] w-auto" />

                <div class="mb-1 text-center">
                    <h2 class="text-2xl font-bold text-gray-900">Iniciar sesión</h2>
                    <p class="text-sm text-gray-600">Accede a tu panel de tienda</p>
                </div>
                <div class="mx-auto h-1 w-16 bg-orange-400 rounded-full mb-5"></div>

                <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Contraseña" />
                        <div class="relative">
                            <TextInput
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                class="mt-1 block w-full pr-10"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                            />
                            <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" class="absolute inset-y-0 right-0 mr-2 mt-1.5 p-2 text-gray-500 hover:text-gray-700">
                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M2.25 12c2.5-4.5 6.215-7.5 9.75-7.5 3.535 0 7.25 3 9.75 7.5-2.5 4.5-6.215 7.5-9.75 7.5-3.535 0-7.25-3-9.75-7.5Z" />
                                    <circle cx="12" cy="12" r="3.25" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M1 1l22 22" />
                                    <path d="M9.88 9.88A3 3 0 0 0 12 15a3 3 0 0 0 2.121-.879M10.59 5.08A10.84 10.84 0 0 1 12 4.5c3.535 0 7.25 3 9.75 7.5-.89 1.602-2.02 3.01-3.32 4.14" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 select-none">
                            <input type="checkbox" v-model="form.remember" class="rounded" />
                            <span class="text-sm text-gray-700">Recordarme</span>
                        </label>

                        <template v-if="canResetPassword">
                            <Link
                                :href="route('password.request')"
                                class="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </template>
                    </div>

                    <div class="flex items-center justify-end pt-2">
                        <PrimaryButton
                            class="ms-4"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Iniciar sesión
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </section>
    </div>
</template>