<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    coupons: Object,
    products: Array,
});

const activeTab = computed(() => {
    return route().current('admin.coupons.*') ? 'coupons' : 'customers';
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const editingCoupon = ref(null);
const couponToDelete = ref(null);
const productSearchQuery = ref('');

const form = useForm({
    code: '',
    type: 'percentage',
    value: '',
    min_purchase: '',
    max_discount: '',
    valid_from: '',
    valid_until: '',
    usage_limit: '',
    usage_limit_per_customer: '',
    is_active: true,
    description: '',
    product_ids: [],
});

const openCreateModal = () => {
    form.reset();
    form.type = 'percentage';
    form.is_active = true;
    form.product_ids = [];
    productSearchQuery.value = '';
    showCreateModal.value = true;
};

const openEditModal = (coupon) => {
    editingCoupon.value = coupon;
    form.code = coupon.code;
    form.type = coupon.type;
    form.value = coupon.value;
    form.min_purchase = coupon.min_purchase || '';
    form.max_discount = coupon.max_discount || '';
    form.valid_from = coupon.valid_from ? coupon.valid_from.split(' ')[0] : '';
    form.valid_until = coupon.valid_until ? coupon.valid_until.split(' ')[0] : '';
    form.usage_limit = coupon.usage_limit || '';
    form.usage_limit_per_customer = coupon.usage_limit_per_customer || '';
    form.is_active = coupon.is_active;
    form.description = coupon.description || '';
    form.product_ids = coupon.products?.map(p => p.id) || [];
    productSearchQuery.value = '';
    showEditModal.value = true;
};

const closeModals = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showDeleteModal.value = false;
    editingCoupon.value = null;
    couponToDelete.value = null;
    productSearchQuery.value = '';
    form.reset();
};

const createCoupon = () => {
    form.post(route('admin.coupons.store'), {
        onSuccess: () => {
            closeModals();
        },
    });
};

const updateCoupon = () => {
    form.put(route('admin.coupons.update', editingCoupon.value.id), {
        onSuccess: () => {
            closeModals();
        },
    });
};

const confirmDelete = (coupon) => {
    couponToDelete.value = coupon;
    showDeleteModal.value = true;
};

const deleteCoupon = () => {
    router.delete(route('admin.coupons.destroy', couponToDelete.value.id), {
        onSuccess: () => {
            closeModals();
        },
    });
};

const toggleActive = (coupon) => {
    router.put(route('admin.coupons.toggle-active', coupon.id), {
        preserveScroll: true,
    });
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const isExpired = (coupon) => {
    if (!coupon.valid_until) return false;
    return new Date(coupon.valid_until) < new Date();
};

// Filtrar productos por búsqueda
const filteredProducts = computed(() => {
    if (!productSearchQuery.value.trim()) {
        return props.products || [];
    }
    const query = productSearchQuery.value.toLowerCase();
    return (props.products || []).filter(product => 
        product.name.toLowerCase().includes(query)
    );
});

// Toggle selección de producto
const toggleProduct = (productId) => {
    const index = form.product_ids.indexOf(productId);
    if (index > -1) {
        form.product_ids.splice(index, 1);
    } else {
        form.product_ids.push(productId);
    }
};

// Verificar si un producto está seleccionado
const isProductSelected = (productId) => {
    return form.product_ids.includes(productId);
};
</script>

<template>
    <Head title="Cupones de Descuento" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clientes</h2>
                <PrimaryButton v-if="activeTab === 'coupons'" @click="openCreateModal">
                    + Crear Cupón
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tabs -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <Link
                            :href="route('admin.customers.index')"
                            :class="[
                                activeTab === 'customers'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Listado de Clientes
                        </Link>
                        <Link
                            :href="route('admin.coupons.index')"
                            :class="[
                                activeTab === 'coupons'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Cupones de Descuento
                        </Link>
                    </nav>
                </div>

                <!-- Contenido de Cupones -->
                <div v-if="activeTab === 'coupons'">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usos</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expira</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="coupon in coupons.data" :key="coupon.id" class="hover:bg-gray-50" :class="{ 'bg-red-50': isExpired(coupon) }">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ coupon.code }}</div>
                                        <div v-if="coupon.products && coupon.products.length > 0" class="text-xs text-gray-500">
                                            {{ coupon.products.length }} producto(s)
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ coupon.type === 'percentage' ? 'Porcentaje' : 'Fijo' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ coupon.type === 'percentage' ? coupon.value + '%' : '$' + coupon.value }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ coupon.usages_count || 0 }}{{ coupon.usage_limit ? ' / ' + coupon.usage_limit : '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatDate(coupon.valid_until) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button
                                            @click="toggleActive(coupon)"
                                            class="px-2 py-1 rounded text-xs font-medium"
                                            :class="coupon.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                        >
                                            {{ coupon.is_active ? 'Activo' : 'Inactivo' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button @click="openEditModal(coupon)" class="text-indigo-600 hover:text-indigo-900">
                                            Editar
                                        </button>
                                        <button @click="confirmDelete(coupon)" class="text-red-600 hover:text-red-900">
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="coupons.data.length === 0">
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No hay cupones creados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="coupons.links && coupons.links.length > 3" class="px-6 py-4 border-t border-gray-200">
                        <Pagination :links="coupons.links" />
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar -->
        <Modal :show="showCreateModal || showEditModal" @close="closeModals">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ showCreateModal ? 'Crear Nuevo Cupón' : 'Editar Cupón' }}
                </h2>
                <form @submit.prevent="showCreateModal ? createCoupon() : updateCoupon()" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código del Cupón</label>
                        <input v-model="form.code" type="text" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Descuento</label>
                        <select v-model="form.type" class="block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="percentage">Porcentaje (%)</option>
                            <option value="fixed">Monto Fijo ($)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Valor del Descuento {{ form.type === 'percentage' ? '(%)' : '($)' }} <span class="text-red-500">*</span>
                        </label>
                        <p class="text-xs text-gray-500 mb-2">
                            {{ form.type === 'percentage' 
                                ? 'Ingresa el porcentaje de descuento (ej: 15 para 15% de descuento)' 
                                : 'Ingresa el monto fijo de descuento en pesos (ej: 50000 para $50.000 de descuento)' }}
                        </p>
                        <input v-model="form.value" type="number" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm" required :placeholder="form.type === 'percentage' ? 'Ej: 15' : 'Ej: 50000'">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monto Mínimo de Compra (Opcional)</label>
                        <p class="text-xs text-gray-500 mb-2">
                            El cupón solo será válido si el total del carrito es igual o mayor a este monto. Déjalo vacío si no quieres un mínimo.
                        </p>
                        <input v-model="form.min_purchase" type="number" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm" placeholder="Ej: 100000">
                    </div>
                    <div v-if="form.type === 'percentage'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descuento Máximo (Opcional)</label>
                        <p class="text-xs text-gray-500 mb-2">
                            Limita el descuento máximo en pesos. Ejemplo: Si el cupón es 15% y pones máximo $50.000, aunque el 15% de $1.000.000 sería $150.000, solo se aplicará $50.000 de descuento.
                        </p>
                        <input v-model="form.max_discount" type="number" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm" placeholder="Ej: 50000">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Válido Desde (Opcional)</label>
                            <input v-model="form.valid_from" type="date" class="block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Válido Hasta (Opcional)</label>
                            <input v-model="form.valid_until" type="date" class="block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Límite Total de Usos (Opcional)</label>
                            <input v-model="form.usage_limit" type="number" min="1" class="block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Límite por Cliente (Opcional)</label>
                            <input v-model="form.usage_limit_per_customer" type="number" min="1" class="block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (Opcional)</label>
                        <textarea v-model="form.description" class="block w-full rounded-md border-gray-300 shadow-sm" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Productos Específicos (Opcional)</label>
                        <p class="text-xs text-gray-500 mb-2">Selecciona uno o varios productos. Si no seleccionas ninguno, el cupón aplicará a todo el catálogo.</p>
                        
                        <!-- Búsqueda de productos -->
                        <div class="mb-3">
                            <input
                                v-model="productSearchQuery"
                                type="text"
                                placeholder="Buscar productos..."
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>

                        <!-- Lista de productos con checkboxes -->
                        <div class="border border-gray-300 rounded-md max-h-60 overflow-y-auto bg-white">
                            <div v-if="filteredProducts.length === 0" class="p-4 text-sm text-gray-500 text-center">
                                No se encontraron productos
                            </div>
                            <div v-else class="divide-y divide-gray-200">
                                <label
                                    v-for="product in filteredProducts"
                                    :key="product.id"
                                    class="flex items-center px-4 py-3 hover:bg-gray-50 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isProductSelected(product.id)"
                                        @change="toggleProduct(product.id)"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <div class="ml-3 flex-1">
                                        <span class="text-sm font-medium text-gray-900">{{ product.name }}</span>
                                        <span v-if="product.price" class="ml-2 text-xs text-gray-500">
                                            - {{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(product.price) }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Contador de productos seleccionados -->
                        <div v-if="form.product_ids.length > 0" class="mt-2 text-sm text-indigo-600">
                            {{ form.product_ids.length }} producto(s) seleccionado(s)
                        </div>
                    </div>
                    <div class="flex items-center">
                        <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded border-gray-300">
                        <label for="is_active" class="ml-2 text-sm text-gray-700">Cupón activo</label>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <SecondaryButton @click="closeModals" type="button">Cancelar</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ showCreateModal ? 'Crear' : 'Guardar' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal Eliminar -->
        <Modal :show="showDeleteModal" @close="closeModals">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Eliminar Cupón</h2>
                <p class="text-gray-600 mb-6">
                    ¿Estás seguro de que deseas eliminar el cupón "{{ couponToDelete?.code }}"? Esta acción no se puede deshacer.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeModals">Cancelar</SecondaryButton>
                    <DangerButton @click="deleteCoupon">Eliminar</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

