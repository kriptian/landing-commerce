<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    store: Object,
});

const form = useForm({
    name: props.store.name,
    logo: null,
    phone: props.store.phone,
    address: props.store.address,
    address_two: props.store.address_two,
    address_three: props.store.address_three,
    address_four: props.store.address_four,
    facebook_url: props.store.facebook_url,
    instagram_url: props.store.instagram_url,
    tiktok_url: props.store.tiktok_url, // <-- 1. CAMPO NUEVO EN EL FORMULARIO
    plan: props.store.plan || 'emprendedor',
    plan_cycle: props.store.plan_cycle || 'mensual',
});

const submit = () => {
    // Usamos 'post' porque estamos enviando un archivo (logo)
    // Laravel sabe que es una actualización por la ruta y el controlador.
    form.post(route('store.save'));
};
</script>

<template>
    <Head title="Configura tu Tienda" />

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <h1 class="text-3xl font-bold">¡Casi listo!</h1>
            <p class="text-center text-gray-600">Configura los datos de tu tienda</p>
        </div>

        <div class="w-full sm:max-w-xl mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label for="name" class="block font-medium text-sm text-gray-700">Nombre de la Tienda</label>
                    <input id="name" v-model="form.name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                </div>

                <div class="mb-4">
                    <label for="logo" class="block font-medium text-sm text-gray-700">Logo de la Tienda</label>
                    <input id="logo" @input="form.logo = $event.target.files[0]" type="file" class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>

                <div class="mb-4">
                    <label for="phone" class="block font-medium text-sm text-gray-700">Teléfono (WhatsApp)</label>
                    <input id="phone" v-model="form.phone" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                </div>

                <div class="mb-4">
                    <label for="address" class="block font-medium text-sm text-gray-700">Dirección Principal</label>
                    <input id="address" v-model="form.address" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                </div>
                
                <div class="mb-4">
                    <label for="address_two" class="block font-medium text-sm text-gray-700">Dirección Sede 2 (Opcional)</label>
                    <input id="address_two" v-model="form.address_two" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                </div>
                
                <div class="mb-4">
                    <label for="address_three" class="block font-medium text-sm text-gray-700">Dirección Sede 3 (Opcional)</label>
                    <input id="address_three" v-model="form.address_three" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                </div>
                
                <div class="mb-4">
                    <label for="address_four" class="block font-medium text-sm text-gray-700">Dirección Sede 4 (Opcional)</label>
                    <input id="address_four" v-model="form.address_four" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                </div>

                <div class="mb-4">
                    <label class="block font-medium text-sm text-gray-700">Plan</label>
                    <select v-model="form.plan" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                        <option value="emprendedor">Emprendedor</option>
                        <option value="negociante">Negociante (recomendado)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block font-medium text-sm text-gray-700">Ciclo</label>
                    <select v-model="form.plan_cycle" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                        <option value="mensual">Mensual</option>
                        <option value="anual">Anual</option>
                    </select>
                </div>

                <div class="mb-4 p-3 rounded border bg-gray-50 text-sm">
                    <p class="font-semibold mb-1">Resumen del plan seleccionado</p>
                    <ul class="list-disc ml-5 text-gray-700">
                        <li v-if="form.plan==='emprendedor'">Catálogo, productos ilimitados, variantes, categorías, checkout a WhatsApp, 0% comisión.</li>
                        <li v-else>Incluye todo lo del Emprendedor + órdenes avanzadas, inventario, usuarios/roles, reportes y exportar a Excel.</li>
                    </ul>
                </div>

                <div class="mb-4">
                    <label for="facebook_url" class="block font-medium text-sm text-gray-700">URL de Facebook (Completa)</label>
                    <input id="facebook_url" v-model="form.facebook_url" type="url" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" placeholder="https://facebook.com/tutienda">
                </div>

                <div class="mb-4">
                    <label for="instagram_url" class="block font-medium text-sm text-gray-700">URL de Instagram (Completa)</label>
                    <input id="instagram_url" v-model="form.instagram_url" type="url" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" placeholder="https://instagram.com/tutienda">
                </div>

                <div class="mb-4">
                    <label for="tiktok_url" class="block font-medium text-sm text-gray-700">URL de TikTok (Completa)</label>
                    <input id="tiktok_url" v-model="form.tiktok_url" type="url" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" placeholder="https://tiktok.com/@tutienda">
                </div>
                <div class="flex items-center justify-end mt-4">
                    <button type="submit" :disabled="form.processing" class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                        Guardar y Finalizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>