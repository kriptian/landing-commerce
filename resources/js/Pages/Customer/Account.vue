<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    store: Object,
    customer: Object,
    orders: Object,
    addresses: Array,
    stats: Object,
});

const page = usePage();
const showSuccess = ref(page?.props?.flash?.success ? true : false);

// Formularios
const profileForm = useForm({
    name: props.customer.name,
    phone: props.customer.phone || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const addressForm = useForm({
    label: 'Casa',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'Colombia',
    is_default: false,
    notes: '',
});

// Modales
const showEditAddressModal = ref(false);
const editingAddress = ref(null);
const showDeleteAddressModal = ref(false);
const addressToDelete = ref(null);
const showAddAddressModal = ref(false);

// Actualizar perfil
const updateProfile = () => {
    profileForm.put(route('customer.profile.update', { store: props.store.slug }), {
        onSuccess: () => {
            showSuccess.value = true;
            setTimeout(() => showSuccess.value = false, 3000);
        },
    });
};

// Cambiar contraseña
const updatePassword = () => {
    passwordForm.put(route('customer.password.update', { store: props.store.slug }), {
        onSuccess: () => {
            passwordForm.reset();
            showSuccess.value = true;
            setTimeout(() => showSuccess.value = false, 3000);
        },
    });
};

// Agregar dirección
const openAddAddressModal = () => {
    addressForm.reset();
    addressForm.label = 'Casa';
    addressForm.city = '';
    addressForm.country = 'Colombia';
    addressForm.is_default = props.addresses.length === 0;
    showAddAddressModal.value = true;
};

const addAddress = () => {
    addressForm.post(route('customer.addresses.store', { store: props.store.slug }), {
        onSuccess: () => {
            showAddAddressModal.value = false;
            addressForm.reset();
        },
    });
};

// Editar dirección
const openEditAddressModal = (address) => {
    editingAddress.value = address;
    addressForm.label = address.label;
    addressForm.address_line_1 = address.address_line_1;
    addressForm.address_line_2 = address.address_line_2 || '';
    addressForm.city = address.city;
    addressForm.state = address.state || '';
    addressForm.postal_code = address.postal_code || '';
    addressForm.country = address.country || 'Colombia';
    addressForm.is_default = address.is_default;
    addressForm.notes = address.notes || '';
    showEditAddressModal.value = true;
};

const updateAddress = () => {
    addressForm.put(route('customer.addresses.update', { store: props.store.slug, address: editingAddress.value.id }), {
        onSuccess: () => {
            showEditAddressModal.value = false;
            editingAddress.value = null;
            addressForm.reset();
        },
    });
};

// Eliminar dirección
const confirmDeleteAddress = (address) => {
    addressToDelete.value = address;
    showDeleteAddressModal.value = true;
};

const deleteAddress = () => {
    router.delete(route('customer.addresses.destroy', { store: props.store.slug, address: addressToDelete.value.id }), {
        onSuccess: () => {
            showDeleteAddressModal.value = false;
            addressToDelete.value = null;
        },
    });
};

// Establecer dirección predeterminada
const setDefaultAddress = (address) => {
    router.post(route('customer.addresses.set-default', { store: props.store.slug, address: address.id }), {
        preserveScroll: true,
    });
};

// Formatear fecha
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Formatear precio
const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(price);
};
</script>

<template>
    <Head :title="`Mi Cuenta - ${store.name}`" />

    <div class="min-h-screen" :style="store?.catalog_body_bg_color ? { backgroundColor: store.catalog_body_bg_color } : { backgroundColor: '#F9FAFB' }">
        <!-- Header -->
        <header class="bg-white shadow-sm sticky top-0 z-40">
            <nav class="container mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img v-if="store?.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover ring-2 ring-gray-100">
                    <h1 class="text-lg font-medium text-gray-600">{{ store.name }}</h1>
                </div>
                <div class="flex items-center gap-4">
                    <Link :href="route('catalogo.index', { store: store.slug })" class="text-sm text-gray-600 hover:text-gray-800">
                        Volver al catálogo
                    </Link>
                    <form @submit.prevent="router.post(route('customer.logout', { store: store.slug }))">
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700 font-medium">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </nav>
        </header>

        <main class="container mx-auto px-6 py-12">
            <div class="max-w-6xl mx-auto">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Mi Cuenta</h2>

                <!-- Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="text-sm text-gray-600 mb-1">Total de Pedidos</p>
                        <p class="text-3xl font-bold text-gray-900">{{ stats.total_orders }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="text-sm text-gray-600 mb-1">Total Gastado</p>
                        <p class="text-3xl font-bold text-gray-900">{{ formatPrice(stats.total_spent) }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="text-sm text-gray-600 mb-1">Dirección Predeterminada</p>
                        <p class="text-sm font-medium text-gray-900" v-if="stats.default_address">
                            {{ stats.default_address.label }} - {{ stats.default_address.city }}
                        </p>
                        <p class="text-sm text-gray-500" v-else>No configurada</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Columna izquierda: Perfil y Direcciones -->
                    <div class="space-y-6">
                        <!-- Perfil -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4">Mi Perfil</h3>
                            <form @submit.prevent="updateProfile" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                    <input v-model="profileForm.name" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input :value="customer.email" type="email" class="block w-full rounded-md border-gray-300 bg-gray-50" disabled>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                    <input v-model="profileForm.phone" type="tel" class="block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <PrimaryButton type="submit" :disabled="profileForm.processing">
                                    Guardar cambios
                                </PrimaryButton>
                            </form>
                        </div>

                        <!-- Cambiar contraseña -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4">Cambiar Contraseña</h3>
                            <form @submit.prevent="updatePassword" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña Actual</label>
                                    <input v-model="passwordForm.current_password" type="password" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label>
                                    <input v-model="passwordForm.password" type="password" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Nueva Contraseña</label>
                                    <input v-model="passwordForm.password_confirmation" type="password" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                                </div>
                                <PrimaryButton type="submit" :disabled="passwordForm.processing">
                                    Cambiar contraseña
                                </PrimaryButton>
                            </form>
                        </div>

                        <!-- Direcciones -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-semibold text-gray-900">Mis Direcciones</h3>
                                <button @click="openAddAddressModal" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                    + Agregar
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="address in addresses" :key="address.id" class="border rounded-lg p-4" :class="{ 'border-blue-500 bg-blue-50': address.is_default }">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="font-semibold text-gray-900">{{ address.label }}</span>
                                                <span v-if="address.is_default" class="text-xs bg-blue-500 text-white px-2 py-1 rounded">Predeterminada</span>
                                            </div>
                                            <p class="text-sm text-gray-600">{{ address.address_line_1 }}</p>
                                            <p v-if="address.address_line_2" class="text-sm text-gray-600">{{ address.address_line_2 }}</p>
                                            <p class="text-sm text-gray-600">{{ address.city }}{{ address.state ? ', ' + address.state : '' }}</p>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="openEditAddressModal(address)" class="text-blue-600 hover:text-blue-700 text-sm">Editar</button>
                                            <button v-if="!address.is_default" @click="setDefaultAddress(address)" class="text-green-600 hover:text-green-700 text-sm">Predeterminada</button>
                                            <button @click="confirmDeleteAddress(address)" class="text-red-600 hover:text-red-700 text-sm">Eliminar</button>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="addresses.length === 0" class="text-sm text-gray-500 text-center py-4">
                                    No tienes direcciones guardadas
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Agregar Dirección -->
    <Modal :show="showAddAddressModal" @close="showAddAddressModal = false">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Agregar Nueva Dirección</h2>
            <form @submit.prevent="addAddress" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Etiqueta (Ej: Casa, Oficina)</label>
                    <input v-model="addressForm.label" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección Línea 1</label>
                    <input v-model="addressForm.address_line_1" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección Línea 2 (Opcional)</label>
                    <input v-model="addressForm.address_line_2" type="text" class="block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                        <input v-model="addressForm.city" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Departamento/Estado</label>
                        <input v-model="addressForm.state" type="text" class="block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código Postal</label>
                        <input v-model="addressForm.postal_code" type="text" class="block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">País</label>
                        <input v-model="addressForm.country" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas (Opcional)</label>
                    <textarea v-model="addressForm.notes" class="block w-full rounded-md border-gray-300 shadow-sm" rows="2"></textarea>
                </div>
                <div class="flex items-center">
                    <input v-model="addressForm.is_default" type="checkbox" id="is_default_new" class="rounded border-gray-300">
                    <label for="is_default_new" class="ml-2 text-sm text-gray-700">Marcar como dirección predeterminada</label>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <SecondaryButton @click="showAddAddressModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="addressForm.processing">Agregar</PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Modal Editar Dirección -->
    <Modal :show="showEditAddressModal" @close="showEditAddressModal = false">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Editar Dirección</h2>
            <form @submit.prevent="updateAddress" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Etiqueta</label>
                    <input v-model="addressForm.label" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección Línea 1</label>
                    <input v-model="addressForm.address_line_1" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dirección Línea 2 (Opcional)</label>
                    <input v-model="addressForm.address_line_2" type="text" class="block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                        <input v-model="addressForm.city" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Departamento/Estado</label>
                        <input v-model="addressForm.state" type="text" class="block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código Postal</label>
                        <input v-model="addressForm.postal_code" type="text" class="block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">País</label>
                        <input v-model="addressForm.country" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notas (Opcional)</label>
                    <textarea v-model="addressForm.notes" class="block w-full rounded-md border-gray-300 shadow-sm" rows="2"></textarea>
                </div>
                <div class="flex items-center">
                    <input v-model="addressForm.is_default" type="checkbox" id="is_default_edit" class="rounded border-gray-300">
                    <label for="is_default_edit" class="ml-2 text-sm text-gray-700">Marcar como dirección predeterminada</label>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <SecondaryButton @click="showEditAddressModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="addressForm.processing">Guardar</PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Modal Eliminar Dirección -->
    <Modal :show="showDeleteAddressModal" @close="showDeleteAddressModal = false">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Eliminar Dirección</h2>
            <p class="text-gray-600 mb-6">
                ¿Estás seguro de que deseas eliminar esta dirección? Esta acción no se puede deshacer.
            </p>
            <div class="flex justify-end gap-3">
                <SecondaryButton @click="showDeleteAddressModal = false">Cancelar</SecondaryButton>
                <DangerButton @click="deleteAddress">Eliminar</DangerButton>
            </div>
        </div>
    </Modal>

    <!-- Modal de éxito -->
    <AlertModal
        :show="showSuccess"
        type="success"
        title="¡Éxito!"
        :message="page?.props?.flash?.success || 'Operación realizada exitosamente'"
        primary-text="Entendido"
        @primary="showSuccess=false"
        @close="showSuccess=false"
    />
</template>

