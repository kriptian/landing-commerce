<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const store = user.store;
const showSuccess = ref(false);

const form = useForm({
    _method: 'patch',
    name: user.name,
    email: user.email,
    // ===== CAMPO NUEVO PARA EL NOMBRE DE LA TIENDA =====
    store_name: store?.name || '', // <-- Lo inicializamos con el nombre actual
    // ===================================================
    logo: null,
    facebook_url: store?.facebook_url || '',
    instagram_url: store?.instagram_url || '',
    tiktok_url: store?.tiktok_url || '',
    custom_domain: store?.custom_domain || '',
    phone: store?.phone || '',
});

const logoPreview = ref(null);
const logoInput = ref(null);

const updateLogoPreview = () => {
    const photo = logoInput.value.files[0];
    if (!photo) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        logoPreview.value = e.target.result;
    };
    reader.readAsDataURL(photo);
};

const selectNewLogo = () => {
    logoInput.value.click();
};

const clearLogoFileInput = () => {
    if (logoInput.value) {
        logoInput.value.value = null;
    }
};

const updateProfileInformation = () => {
    form.post(route('profile.update'), {
        onSuccess: () => {
            clearLogoFileInput();
            logoPreview.value = null;
            showSuccess.value = true;
        },
    });
};

</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Información de Perfil y Tienda
            </h2>
    <AlertModal
        :show="showSuccess"
        type="success"
        title="¡Perfil actualizado!"
        message="Los datos de tu perfil y tienda se guardaron correctamente."
        primary-text="Entendido"
        @primary="showSuccess=false"
        @close="showSuccess=false"
    />
            <p class="mt-1 text-sm text-gray-600">
                Actualiza la información de tu cuenta, nombre, logo y redes sociales de tu tienda.
            </p>
        </header>

        <form @submit.prevent="updateProfileInformation" class="mt-6 space-y-6">
            <div>
                <InputLabel for="name" value="Tu Nombre" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            
            <div v-if="$page.props.auth.user.store" class="space-y-6 border-t pt-6">
                <div>
                    <InputLabel for="phone" value="WhatsApp de la tienda" />
                    <TextInput
                        id="phone"
                        type="tel"
                        class="mt-1 block w-full"
                        v-model="form.phone"
                        autocomplete="tel"
                        placeholder="57xxxxxxxxxx"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>
                <div>
                    <InputLabel for="store_name" value="Nombre de la Tienda" />
                    <TextInput
                        id="store_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.store_name"
                        required
                        autocomplete="organization"
                    />
                    <InputError class="mt-2" :message="form.errors.store_name" />
                </div>
                <div>
                    <InputLabel for="logo" value="Logo de la Tienda" />
                    <div class="mt-2 flex items-center space-x-6">
                        <div v-if="$page.props.auth.user.store.logo_url && !logoPreview">
                            <img :src="$page.props.auth.user.store.logo_url" class="h-20 w-20 rounded-full object-cover">
                        </div>
                        <div v-show="logoPreview">
                            <span
                                class="block h-20 w-20 rounded-full bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'' + logoPreview + '\');'"
                            />
                        </div>
                        
                        <SecondaryButton type="button" @click.prevent="selectNewLogo">
                            Seleccionar Logo
                        </SecondaryButton>
                    </div>
                    
                    <input
                        ref="logoInput"
                        type="file"
                        class="hidden"
                        @input="form.logo = $event.target.files[0]"
                        @change="updateLogoPreview"
                    >
                    <InputError :message="form.errors.logo" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="custom_domain" value="Dominio propio (opcional)" />
                    <TextInput
                        id="custom_domain"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.custom_domain"
                        autocomplete="off"
                        placeholder="tienda.midominio.com"
                    />
                    <p class="text-xs text-gray-500 mt-1">Apunta tu DNS a este servidor. Si lo defines, tu catálogo abrirá ahí.</p>
                    <InputError class="mt-2" :message="form.errors.custom_domain" />
                </div>

                <div>
                    <InputLabel for="facebook_url" value="URL de Facebook" />
                    <TextInput
                        id="facebook_url"
                        type="url"
                        class="mt-1 block w-full"
                        v-model="form.facebook_url"
                        autocomplete="off"
                        placeholder="https://facebook.com/tutienda"
                    />
                    <InputError class="mt-2" :message="form.errors.facebook_url" />
                </div>
                
                <div>
                    <InputLabel for="instagram_url" value="URL de Instagram" />
                    <TextInput
                        id="instagram_url"
                        type="url"
                        class="mt-1 block w-full"
                        v-model="form.instagram_url"
                        autocomplete="off"
                        placeholder="https://instagram.com/tutienda"
                    />
                    <InputError class="mt-2" :message="form.errors.instagram_url" />
                </div>
                
                <div>
                    <InputLabel for="tiktok_url" value="URL de TikTok" />
                    <TextInput
                        id="tiktok_url"
                        type="url"
                        class="mt-1 block w-full"
                        v-model="form.tiktok_url"
                        autocomplete="off"
                        placeholder="https://tiktok.com/@tutienda"
                    />
                    <InputError class="mt-2" :message="form.errors.tiktok_url" />
                </div>
            </div>
            
            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Guardar Cambios</PrimaryButton>
                </div>
        </form>
    </section>
</template>