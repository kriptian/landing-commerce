<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch, nextTick, computed } from 'vue';

const props = defineProps({
    products: Object, // Objeto de paginación con los productos y sus variantes
    filters: Object,
});

// Estado UI del buscador
const showSearch = ref(Boolean(props.filters?.search));
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || ''); // '', 'out_of_stock', 'low_stock'
const searchInputRef = ref(null);
const showStatusMenu = ref(false);

const statuses = [
    { value: '', label: 'Todos' },
    { value: 'out_of_stock', label: 'Agotados' },
    { value: 'low_stock', label: 'Bajo stock' },
];

const statusLabel = computed(() => {
    return (statuses.find(s => s.value === status.value) || statuses[0]).label;
});

// Abrir la cortina de búsqueda y enfocar
const toggleSearch = async () => {
    showSearch.value = !showSearch.value;
    if (showSearch.value) {
        await nextTick();
        searchInputRef.value?.focus();
    } else {
        // Si se cierra, limpiar búsqueda y enviar
        search.value = '';
        submitFilters();
    }
};

// Enviar filtros a la URL Conservando scroll y reemplazando historia
const submitFilters = () => {
    router.get(route('admin.inventory.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Disparar búsqueda al tipear con debounce sencillo
let debounceTimer;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => submitFilters(), 350);
});

// Seleccionar estado desde el menú y aplicar filtro
const selectStatus = (newStatus) => {
    status.value = newStatus;
    showStatusMenu.value = false;
    submitFilters();
};

// Filtrar variantes en la UI según estado activo (estricto y consistente con backend)
const filteredVariants = (variants) => {
    if (!status.value) return variants;
    return variants.filter((v) => {
        const stock = Number(v.stock) || 0;
        const alert = Number(v.alert) || 0;
        if (status.value === 'out_of_stock') return stock <= 0;
        if (status.value === 'low_stock') return alert > 0 && stock > 0 && stock <= alert;
        return true;
    });
};

// Mostrar productos sin variantes solo si coinciden con el filtro (estricto)
const matchesProductStatus = (product) => {
    if (!status.value) return true;
    const qty = Number(product.quantity) || 0;
    const alert = Number(product.alert) || 0;
    if (status.value === 'out_of_stock') return qty <= 0;
    if (status.value === 'low_stock') return alert > 0 && qty > 0 && qty <= alert;
    return true;
};

// Función para determinar el estado del stock
const getStockStatus = (item) => {
    const stock = Number(item.stock) || 0;
    const threshold = Number(item.alert) || 0;
    if (stock <= 0) {
        return { text: 'Agotado', class: 'bg-red-100 text-red-800' };
    }
    if (threshold > 0 && stock <= threshold) {
        return { text: 'Bajo Stock', class: 'bg-yellow-100 text-yellow-800' };
    }
    return { text: 'En Stock', class: 'bg-green-100 text-green-800' };
};

// Función para juntar las opciones de la variante en un texto
const formatVariantOptions = (options) => {
    return Object.entries(options).map(([key, value]) => `${key}: ${value}`).join(', ');
};
</script>

<template>
    <Head title="Gestión de Inventario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de Inventario</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <!-- Barra de acciones: buscador con cortina + filtro -->
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <!-- Botón lupita -->
                                <button type="button" @click="toggleSearch" class="p-2 rounded hover:bg-gray-100 transition">
                                    <!-- Ícono de lupa -->
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
                                    </svg>
                                </button>
                                <!-- Cortina del buscador -->
                                <transition name="fade-slide">
                                    <div v-if="showSearch" class="relative">
                                        <input
                                            ref="searchInputRef"
                                            v-model="search"
                                            type="text"
                                            placeholder="Buscar producto..."
                                            class="w-64 md:w-80 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                </transition>
                            </div>
                            <!-- Dropdown de estado (estilizado) -->
                            <div class="relative">
                                <button type="button" @click="showStatusMenu = !showStatusMenu" class="inline-flex items-center gap-2 px-3 py-2 border rounded-md shadow-sm hover:bg-gray-50">
                                    <span>{{ statusLabel }}</span>
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                                </button>
                                <transition name="fade-slide">
                                    <ul v-if="showStatusMenu" class="absolute right-0 mt-2 w-40 bg-white border rounded-md shadow-lg z-10">
                                        <li v-for="opt in statuses" :key="opt.value">
                                            <button type="button" @click="selectStatus(opt.value)" class="w-full text-left px-3 py-2 hover:bg-gray-100" :class="{ 'font-semibold text-indigo-600': opt.value === status }">
                                                {{ opt.label }}
                                            </button>
                                        </li>
                                    </ul>
                                </transition>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto / Variante</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock Actual</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock Mínimo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="relative px-6 py-3">
                                            <span class="sr-only">Editar</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template v-for="product in products.data" :key="product.id">
                                        <tr v-if="product.variants.length === 0 && matchesProductStatus(product)">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ product.name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-bold">{{ product.quantity }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ product.minimum_stock }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="getStockStatus({ stock: product.quantity, minimum_stock: product.minimum_stock }).class">
                                                    {{ getStockStatus({ stock: product.quantity, minimum_stock: product.minimum_stock }).text }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <Link :href="route('admin.products.edit', product.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>
                                            </td>
                                        </tr>
                                        <tr v-for="variant in filteredVariants(product.variants)" :key="variant.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="font-medium text-gray-900">{{ product.name }}</div>
                                                <div class="text-gray-500">{{ formatVariantOptions(variant.options) }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-bold">{{ variant.stock }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ variant.minimum_stock }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                 <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="getStockStatus(variant).class">
                                                    {{ getStockStatus(variant).text }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <Link :href="route('admin.products.edit', product.id)" class="text-indigo-600 hover:text-indigo-900">Editar</Link>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-if="products.data.length === 0">
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                            No hay productos para mostrar.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <Pagination class="mt-6" :links="products.links" />

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.fade-slide-enter-active, .fade-slide-leave-active { transition: all .2s ease; }
.fade-slide-enter-from { opacity: 0; transform: translateY(-4px); }
.fade-slide-enter-to { opacity: 1; transform: translateY(0); }
.fade-slide-leave-from { opacity: 1; transform: translateY(0); }
.fade-slide-leave-to { opacity: 0; transform: translateY(-4px); }
</style>