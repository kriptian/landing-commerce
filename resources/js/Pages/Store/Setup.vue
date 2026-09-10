<script setup>
import FormField from '@/Components/FormField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({ store: Object });
const logoPreview = ref(props.store.logo_url || '');
const form = useForm({
    name: props.store.name,
    logo: null,
    phone: props.store.phone || '',
    address: props.store.address || '',
    address_two: props.store.address_two || '',
    address_three: props.store.address_three || '',
    address_four: props.store.address_four || '',
    facebook_url: props.store.facebook_url || '',
    instagram_url: props.store.instagram_url || '',
    tiktok_url: props.store.tiktok_url || '',
});

const completed = computed(() => [form.name, form.phone, logoPreview.value].filter(Boolean).length);
const selectLogo = (event) => {
    const file = event.target.files?.[0] || null;
    form.logo = file;
    if (logoPreview.value?.startsWith('blob:')) URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = file ? URL.createObjectURL(file) : props.store.logo_url || '';
};
const submit = () => form.post(route('store.save'), { forceFormData: true });
onBeforeUnmount(() => { if (logoPreview.value?.startsWith('blob:')) URL.revokeObjectURL(logoPreview.value); });
</script>

<template>
    <GuestLayout title="Dale identidad a tu tienda" description="Completa lo esencial para que tus clientes puedan reconocerte y contactarte." wide>
        <Head title="Configurar tienda" />
        <div class="mb-6 flex items-center gap-3 rounded-xl bg-slate-100 p-3">
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-indigo-600 transition-all" :style="{ width: `${Math.max(20, completed * 26)}%` }"></div></div>
            <span class="text-xs font-bold text-slate-600">Paso inicial</span>
        </div>

        <form class="space-y-7" @submit.prevent="submit">
            <section>
                <h3 class="text-sm font-bold uppercase tracking-wide text-indigo-700">1. Identidad</h3>
                <div class="mt-4 grid gap-5 sm:grid-cols-[1fr_9rem] sm:items-start">
                    <FormField id="name" label="Nombre visible de la tienda" :error="form.errors.name" required v-slot="field">
                        <input id="name" v-model="form.name" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" required />
                    </FormField>
                    <div>
                        <span class="ui-label">Logo</span>
                        <label for="logo" class="mt-1.5 flex aspect-square cursor-pointer items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 text-center text-xs font-bold text-slate-500 hover:border-indigo-400 hover:bg-indigo-50">
                            <img v-if="logoPreview" :src="logoPreview" alt="Vista previa del logo" class="h-full w-full object-contain p-2" />
                            <span v-else>Elegir<br>imagen</span>
                        </label>
                        <input id="logo" type="file" class="sr-only" accept="image/*" @change="selectLogo" />
                        <p v-if="form.errors.logo" class="ui-error">{{ form.errors.logo }}</p>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-6">
                <h3 class="text-sm font-bold uppercase tracking-wide text-indigo-700">2. Contacto</h3>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <FormField id="phone" label="WhatsApp del negocio" :error="form.errors.phone" help="Aqui llegaran las consultas y pedidos." v-slot="field">
                        <input id="phone" v-model="form.phone" type="tel" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" placeholder="Ej. 320 123 4567" />
                    </FormField>
                    <FormField id="address" label="Direccion principal" :error="form.errors.address" help="Opcional si vendes exclusivamente por internet." v-slot="field">
                        <input id="address" v-model="form.address" class="ui-input" :class="{ 'ui-input-error': field.invalid }" :aria-describedby="field.describedBy" placeholder="Barrio, calle o punto de referencia" />
                    </FormField>
                </div>
                <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <summary class="cursor-pointer text-sm font-bold text-slate-700">Agregar otras sedes</summary>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3"><input v-model="form.address_two" class="ui-input mt-0" placeholder="Sede 2" /><input v-model="form.address_three" class="ui-input mt-0" placeholder="Sede 3" /><input v-model="form.address_four" class="ui-input mt-0" placeholder="Sede 4" /></div>
                </details>
            </section>

            <details class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <summary class="cursor-pointer text-sm font-bold text-slate-700">Redes sociales (opcional)</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-3"><input v-model="form.facebook_url" type="url" class="ui-input mt-0" placeholder="Facebook" /><input v-model="form.instagram_url" type="url" class="ui-input mt-0" placeholder="Instagram" /><input v-model="form.tiktok_url" type="url" class="ui-input mt-0" placeholder="TikTok" /></div>
                <p v-if="form.errors.facebook_url || form.errors.instagram_url || form.errors.tiktok_url" class="ui-error">Revisa que las direcciones de tus redes empiecen por https://</p>
            </details>

            <div class="rounded-xl bg-indigo-50 p-4 text-sm leading-6 text-indigo-950"><strong>Siguiente paso:</strong> entraras al panel para crear tus categorias y tu primer producto.</div>
            <button type="submit" class="ui-primary-button w-full" :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar y entrar al panel' }}</button>
        </form>
    </GuestLayout>
</template>
