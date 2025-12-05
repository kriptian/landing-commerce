<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    sales: Object,
    stats: Object,
    filters: Object,
});

// Pestaña activa
const activeTab = ref('sales');

// Formulario de filtros para reportes
const filterForm = useForm({
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    search: props.filters?.search || '',
});

// Búsqueda en ventas recientes
const recentSalesSearch = ref('');

// Ocultar/mostrar valores de ventas
const hideSaleValues = ref(false);

// Venta recién creada para imprimir
const lastCreatedSale = ref(null);
const showInvoiceModal = ref(false);

// Aplicar filtros
const applyFilters = () => {
    filterForm.get(route('admin.physical-sales.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Rangos rápidos de fechas
const formatYMD = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const getToday = () => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};

const setQuickRange = (range) => {
    const today = getToday();
    let start, end;
    switch (range) {
        case 'today':
            start = today;
            end = today;
            break;
        case 'last7':
            start = new Date(today);
            start.setDate(start.getDate() - 6);
            end = today;
            break;
        case 'last30':
            start = new Date(today);
            start.setDate(start.getDate() - 29);
            end = today;
            break;
        case 'thisMonth':
            start = new Date(today.getFullYear(), today.getMonth(), 1);
            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'lastMonth':
            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            end = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        default:
            return;
    }
    filterForm.start_date = formatYMD(start);
    filterForm.end_date = formatYMD(end);
    applyFilters();
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const formatDate = (datetime) => {
    return new Date(datetime).toLocaleString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Imprimir factura
const printInvoice = (sale) => {
    if (!sale) return;
    
    // Redirigir a la página de detalle con parámetro de impresión
    router.visit(route('admin.physical-sales.show', sale.id) + '?print=true');
};

// Filtrar ventas recientes: solo del día en curso y por búsqueda
const filteredRecentSales = computed(() => {
    if (!props.sales || !props.sales.data) return [];
    
    // Filtrar solo ventas del día en curso
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayEnd = new Date(today);
    todayEnd.setHours(23, 59, 59, 999);
    
    let sales = props.sales.data.filter(sale => {
        const saleDate = new Date(sale.created_at);
        return saleDate >= today && saleDate <= todayEnd;
    });
    
    // Aplicar búsqueda si existe
    if (recentSalesSearch.value.trim()) {
        const searchTerm = recentSalesSearch.value.toLowerCase();
        sales = sales.filter(sale => 
            sale.sale_number.toLowerCase().includes(searchTerm)
        );
    }
    
    return sales;
});

// Función para formatear valor (ocultar o mostrar)
const formatSaleValue = (value) => {
    if (hideSaleValues.value) {
        return '***';
    }
    return `$${parseFloat(value).toFixed(2)}`;
};

// Estado del carrito de venta
const cartItems = ref([]);
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showBarcodeScanner = ref(false);
const barcodeInput = ref('');
const showPaymentModal = ref(false);
const paymentMethod = ref('efectivo');
const saleNotes = ref('');
const discount = ref(0);
// Referencias para el escáner de código de barras
let barcodeScanner = null;
let stream = null;

// Calcular totales
const subtotal = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
});

const total = computed(() => {
    return subtotal.value - discount.value;
});

// Buscar productos
const searchProducts = async () => {
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(route('admin.physical-sales.search-products') + '?q=' + encodeURIComponent(searchQuery.value));
        const data = await response.json();
        searchResults.value = data.products || [];
    } catch (error) {
        console.error('Error buscando productos:', error);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

// Buscar por código de barras
const searchByBarcode = async (barcode) => {
    if (!barcode.trim()) return;

    try {
        const response = await fetch(route('admin.physical-sales.get-product-by-barcode') + '?barcode=' + encodeURIComponent(barcode));
        const data = await response.json();
        
        if (data.product) {
            addToCart(data.product);
            barcodeInput.value = '';
        } else {
            alert('Producto no encontrado con ese código de barras');
        }
    } catch (error) {
        console.error('Error buscando por código de barras:', error);
        alert('Error al buscar producto');
    }
};

// Calcular precio con descuento
const calculatePriceWithDiscount = (product, variant = null) => {
    // Precio base: variante o producto
    const basePrice = variant?.price ?? product.price;
    let finalPrice = parseFloat(basePrice);
    let discountPercent = 0;
    
    // Aplicar descuento de producto si está activo
    if (product.promo_active && product.promo_discount_percent > 0) {
        discountPercent = parseFloat(product.promo_discount_percent);
        finalPrice = Math.round((finalPrice * (100 - discountPercent)) / 100);
    }
    
    return {
        originalPrice: parseFloat(basePrice),
        finalPrice: finalPrice,
        discountPercent: discountPercent
    };
};

// Agregar producto al carrito
const addToCart = (product, variant = null) => {
    const existingItem = cartItems.value.find(item => 
        item.product_id === product.id && 
        item.variant_id === (variant?.id || null)
    );

    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        const priceData = calculatePriceWithDiscount(product, variant);
        cartItems.value.push({
            product_id: product.id,
            product_name: product.name,
            variant_id: variant?.id || null,
            variant_options: variant?.options || null,
            quantity: 1,
            unit_price: priceData.finalPrice,
            original_price: priceData.originalPrice,
            discount_percent: priceData.discountPercent,
            product: product,
            variant: variant,
        });
    }
    
    searchQuery.value = '';
    searchResults.value = [];
};

// Remover item del carrito
const removeFromCart = (index) => {
    cartItems.value.splice(index, 1);
};

// Actualizar cantidad
const updateQuantity = (index, delta) => {
    const item = cartItems.value[index];
    const newQuantity = item.quantity + delta;
    if (newQuantity > 0) {
        item.quantity = newQuantity;
    }
};

// Inicializar escáner de código de barras
const initBarcodeScanner = async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment' // Cámara trasera
            } 
        });
        
        showBarcodeScanner.value = true;
        
        // Aquí podrías integrar una librería como QuaggaJS o ZXing
        // Por ahora, usaremos un input manual para el código de barras
    } catch (error) {
        console.error('Error accediendo a la cámara:', error);
        alert('No se pudo acceder a la cámara. Por favor, permite el acceso a la cámara en la configuración del navegador.');
    }
};

// Cerrar escáner
const closeBarcodeScanner = () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    showBarcodeScanner.value = false;
};

// Procesar venta
const processSale = async () => {
    if (cartItems.value.length === 0) {
        alert('El carrito está vacío');
        return;
    }

        const saleData = {
        items: cartItems.value.map(item => ({
            product_id: item.product_id,
            variant_id: item.variant_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
        })),
        subtotal: subtotal.value,
        tax: 0,
        discount: discount.value,
        total: total.value,
        payment_method: paymentMethod.value,
        notes: saleNotes.value,
    };

    try {
        // Usar axios que ya está configurado con CSRF
        if (!window.axios) {
            alert('Error: No se pudo inicializar la conexión. Por favor, recarga la página.');
            return;
        }

        const response = await window.axios.post(route('admin.physical-sales.store'), saleData);

        if (response.data && response.data.success) {
            // Guardar la venta creada para mostrar opción de imprimir
            lastCreatedSale.value = response.data.sale;
            showPaymentModal.value = false;
            
            // Limpiar carrito
            cartItems.value = [];
            searchQuery.value = '';
            discount.value = 0;
            saleNotes.value = '';
            paymentMethod.value = 'efectivo';
            
            // Mostrar modal de éxito con opción de imprimir
            showInvoiceModal.value = true;
            
            // Recargar página para actualizar lista de ventas
            router.reload();
        } else {
            alert('Error: ' + (response.data?.message || 'No se pudo procesar la venta'));
        }
    } catch (error) {
        console.error('Error procesando venta:', error);
        
        let errorMessage = 'Error al procesar la venta';
        
        if (error.response) {
            // El servidor respondió con un código de error
            if (error.response.status === 419) {
                errorMessage = 'La sesión ha expirado. Por favor, recarga la página e intenta nuevamente.';
            } else if (error.response.status === 422) {
                errorMessage = error.response.data?.message || 'Error de validación. Verifica los datos e intenta nuevamente.';
            } else if (error.response.data?.message) {
                errorMessage = error.response.data.message;
            }
        } else if (error.request) {
            errorMessage = 'No se pudo conectar con el servidor. Verifica tu conexión.';
        }
        
        alert(errorMessage);
    }
};

// Limpiar al desmontar
onBeforeUnmount(() => {
    closeBarcodeScanner();
});

// Watch para búsqueda automática
let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (searchQuery.value.trim().length >= 2) {
            searchProducts();
        } else {
            searchResults.value = [];
        }
    }, 300);
});
</script>

<template>
    <Head title="Ventas Físicas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ventas Físicas</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Pestañas -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button 
                            @click="activeTab = 'sales'" 
                            :class="[
                                activeTab === 'sales' 
                                    ? 'border-blue-500 text-blue-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Ventas
                        </button>
                        <button 
                            @click="activeTab = 'reports'" 
                            :class="[
                                activeTab === 'reports' 
                                    ? 'border-blue-500 text-blue-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Reportes
                        </button>
                    </nav>
                </div>

                <!-- Contenido de Ventas -->
                <div v-if="activeTab === 'sales'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Panel izquierdo: Búsqueda y carrito -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Búsqueda de productos -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4">Buscar Productos</h3>
                                
                                <div class="flex gap-2 mb-4">
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Buscar por nombre, ID o código de barras..."
                                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        @keyup.enter="searchProducts"
                                    />
                                    <button
                                        @click="initBarcodeScanner"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                    >
                                        📷 Escanear
                                    </button>
                                </div>

                                <!-- Resultados de búsqueda -->
                                <div v-if="searchResults.length > 0" class="mt-4 space-y-2 max-h-64 overflow-y-auto">
                                    <div
                                        v-for="product in searchResults"
                                        :key="product.id"
                                        class="p-3 border rounded-md hover:bg-gray-50 cursor-pointer"
                                        @click="addToCart(product)"
                                    >
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <p class="font-medium">{{ product.name }}</p>
                                                <p class="text-sm text-gray-500">${{ product.price }}</p>
                                                <p v-if="product.barcode" class="text-xs text-gray-400">Código: {{ product.barcode }}</p>
                                            </div>
                                            <button
                                                @click.stop="addToCart(product)"
                                                class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700"
                                            >
                                                Agregar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Carrito de venta -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4">Carrito de Venta</h3>
                                
                                <div v-if="cartItems.length === 0" class="text-center py-8 text-gray-500">
                                    El carrito está vacío
                                </div>

                                <div v-else class="space-y-3">
                                    <div
                                        v-for="(item, index) in cartItems"
                                        :key="index"
                                        class="flex items-center justify-between p-3 border rounded-md"
                                    >
                                        <div class="flex-1">
                                            <p class="font-medium">{{ item.product_name }}</p>
                                            <p v-if="item.variant_options" class="text-sm text-gray-500">
                                                {{ Object.entries(item.variant_options).map(([k, v]) => `${k}: ${v}`).join(', ') }}
                                            </p>
                                            <div class="flex items-center gap-2">
                                                <div>
                                                    <p v-if="item.original_price && item.original_price > item.unit_price" class="text-sm text-gray-400 line-through">
                                                        ${{ item.original_price.toFixed(2) }}
                                                    </p>
                                                    <p class="text-sm text-gray-600 font-medium">
                                                        ${{ item.unit_price.toFixed(2) }} c/u
                                                    </p>
                                                </div>
                                                <span v-if="item.discount_percent > 0" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded font-medium">
                                                    -{{ item.discount_percent }}%
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button
                                                @click="updateQuantity(index, -1)"
                                                class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300"
                                            >
                                                -
                                            </button>
                                            <span class="w-12 text-center">{{ item.quantity }}</span>
                                            <button
                                                @click="updateQuantity(index, 1)"
                                                class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300"
                                            >
                                                +
                                            </button>
                                            <span class="w-20 text-right font-medium">
                                                ${{ (item.quantity * item.unit_price).toFixed(2) }}
                                            </span>
                                            <button
                                                @click="removeFromCart(index)"
                                                class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Totales -->
                                <div v-if="cartItems.length > 0" class="mt-6 pt-4 border-t space-y-2">
                                    <div class="flex justify-between">
                                        <span>Subtotal:</span>
                                        <span>${{ subtotal.toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Descuento:</span>
                                        <input
                                            v-model.number="discount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :max="subtotal"
                                            class="w-24 text-right border rounded px-2"
                                        />
                                    </div>
                                    <div class="flex justify-between text-lg font-bold pt-2 border-t">
                                        <span>Total:</span>
                                        <span>${{ total.toFixed(2) }}</span>
                                    </div>
                                    <button
                                        @click="showPaymentModal = true"
                                        class="w-full mt-4 px-4 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 font-semibold"
                                    >
                                        Procesar Venta
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel derecho: Historial de ventas -->
                    <div class="lg:col-span-1">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold">Ventas Recientes</h3>
                                    <button
                                        @click="hideSaleValues = !hideSaleValues"
                                        class="p-2 rounded-md hover:bg-gray-100 transition-colors"
                                        :title="hideSaleValues ? 'Mostrar valores' : 'Ocultar valores'"
                                    >
                                        <svg 
                                            v-if="!hideSaleValues"
                                            xmlns="http://www.w3.org/2000/svg" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke-width="1.5" 
                                            stroke="currentColor" 
                                            class="w-5 h-5 text-gray-600"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <svg 
                                            v-else
                                            xmlns="http://www.w3.org/2000/svg" 
                                            fill="none" 
                                            viewBox="0 0 24 24" 
                                            stroke-width="1.5" 
                                            stroke="currentColor" 
                                            class="w-5 h-5 text-gray-600"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L3 3m6.772 6.772L21 21m-3.228-3.228L21 21m-6.772-6.772L3 3" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <!-- Búsqueda por número de venta -->
                                <div class="mb-4">
                                    <input
                                        v-model="recentSalesSearch"
                                        type="text"
                                        placeholder="Buscar por número de venta..."
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                                    />
                                </div>
                                
                                <div v-if="!props.sales || !props.sales.data || props.sales.data.length === 0" class="text-center py-8 text-gray-500">
                                    No hay ventas registradas
                                </div>

                                <div v-else-if="filteredRecentSales.length === 0" class="text-center py-8 text-gray-500">
                                    {{ recentSalesSearch.trim() ? 'No se encontraron ventas con ese número' : 'No hay ventas del día de hoy' }}
                                </div>

                                <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                                    <div
                                        v-for="sale in filteredRecentSales"
                                        :key="sale.id"
                                        class="p-3 border rounded-md hover:bg-gray-50 cursor-pointer"
                                        @click="router.get(route('admin.physical-sales.show', sale.id))"
                                    >
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-medium text-sm">#{{ sale.sale_number }}</p>
                                                <p class="text-xs text-gray-500">{{ new Date(sale.created_at).toLocaleString() }}</p>
                                            </div>
                                            <p class="font-semibold text-green-600">{{ formatSaleValue(sale.total) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido de Reportes -->
                <div v-if="activeTab === 'reports'">
                    <!-- Estadísticas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Ventas Totales</h3>
                                <p class="mt-1 text-3xl font-semibold text-gray-900">
                                    {{ stats ? formatCurrency(stats.totalSales || 0) : '$0' }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total de Ventas</h3>
                                <p class="mt-1 text-3xl font-semibold text-gray-900">
                                    {{ stats ? stats.totalCount || 0 : 0 }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <form @submit.prevent="applyFilters">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                    <div>
                                        <label for="search_sale" class="block text-sm font-medium text-gray-700">Buscar por número</label>
                                        <input 
                                            type="text" 
                                            id="search_sale" 
                                            v-model="filterForm.search" 
                                            placeholder="Ej: V-000001"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        >
                                    </div>
                                    <div>
                                        <label for="start_date" class="block text-sm font-medium text-gray-700">Desde</label>
                                        <input 
                                            type="date" 
                                            id="start_date" 
                                            v-model="filterForm.start_date" 
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        >
                                    </div>
                                    <div>
                                        <label for="end_date" class="block text-sm font-medium text-gray-700">Hasta</label>
                                        <input 
                                            type="date" 
                                            id="end_date" 
                                            v-model="filterForm.end_date" 
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                        >
                                    </div>
                                    <div class="flex space-x-2">
                                        <button 
                                            type="submit" 
                                            :disabled="filterForm.processing" 
                                            class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            Filtrar
                                        </button>
                                        <Link 
                                            :href="route('admin.physical-sales.index')" 
                                            class="w-full bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded hover:bg-gray-400 text-center"
                                        >
                                            Limpiar
                                        </Link>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="text-sm text-gray-500 mr-2 self-center">Rangos rápidos:</span>
                                    <button type="button" @click="setQuickRange('today')" class="px-3 py-1 rounded-full text-sm border bg-white text-gray-700 border-gray-300 hover:bg-gray-50">Hoy</button>
                                    <button type="button" @click="setQuickRange('last7')" class="px-3 py-1 rounded-full text-sm border bg-white text-gray-700 border-gray-300 hover:bg-gray-50">Últimos 7 días</button>
                                    <button type="button" @click="setQuickRange('last30')" class="px-3 py-1 rounded-full text-sm border bg-white text-gray-700 border-gray-300 hover:bg-gray-50">Últimos 30 días</button>
                                    <button type="button" @click="setQuickRange('thisMonth')" class="px-3 py-1 rounded-full text-sm border bg-white text-gray-700 border-gray-300 hover:bg-gray-50">Este mes</button>
                                    <button type="button" @click="setQuickRange('lastMonth')" class="px-3 py-1 rounded-full text-sm border bg-white text-gray-700 border-gray-300 hover:bg-gray-50">Mes pasado</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de ventas -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Historial de Ventas</h3>
                                <a 
                                    :href="route('admin.physical-sales.export', { start_date: filters?.start_date, end_date: filters?.end_date })"
                                    v-if="props.sales && props.sales.data && props.sales.data.length > 0"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-2">
                                        <path d="M7 10a1 1 0 011-1h2V4a1 1 0 112 0v5h2a1 1 0 01.7 1.714l-3 3a1 1 0 01-1.4 0l-3-3A1 1 0 017 10z"/>
                                        <path d="M5 15a1 1 0 011 1v2a2 2 0 002 2h8a2 2 0 002-2v-2a1 1 0 112 0v2a4 4 0 01-4 4H8a4 4 0 01-4-4v-2a1 1 0 011-1z"/>
                                    </svg>
                                    Exportar a Excel
                                </a>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método de Pago</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descuento</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="sale in props.sales.data" :key="sale.id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                                <Link :href="route('admin.physical-sales.show', sale.id)" class="hover:underline">
                                                    {{ sale.sale_number }}
                                                </Link>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ sale.user?.name || 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(sale.created_at) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                                {{ sale.payment_method }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ formatCurrency(sale.subtotal) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ formatCurrency(sale.discount) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                {{ formatCurrency(sale.total) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <Link 
                                                    :href="route('admin.physical-sales.show', sale.id)"
                                                    class="text-blue-600 hover:text-blue-900"
                                                >
                                                    Ver
                                                </Link>
                                            </td>
                                        </tr>
                                        <tr v-if="!props.sales || !props.sales.data || props.sales.data.length === 0">
                                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                                No hay ventas para mostrar en este período.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <Pagination v-if="props.sales && props.sales.links" class="mt-6" :links="props.sales.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de pago -->
        <Modal :show="showPaymentModal" @close="showPaymentModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Confirmar Venta</h2>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Método de Pago</label>
                    <select v-model="paymentMethod" class="w-full rounded-md border-gray-300">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="mixto">Mixto</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notas (Opcional)</label>
                    <textarea
                        v-model="saleNotes"
                        class="w-full rounded-md border-gray-300"
                        rows="3"
                        placeholder="Notas adicionales sobre la venta..."
                    ></textarea>
                </div>

                    <!-- Resumen de descuentos aplicados -->
                    <div v-if="cartItems.some(item => item.discount_percent > 0)" class="mb-4 p-3 bg-green-50 border border-green-200 rounded">
                        <h4 class="text-sm font-semibold text-green-800 mb-2">Descuentos por Producto:</h4>
                        <div class="space-y-1">
                            <div 
                                v-for="(item, index) in cartItems.filter(i => i.discount_percent > 0)" 
                                :key="index"
                                class="flex justify-between text-sm"
                            >
                                <span class="text-green-700">{{ item.product_name }}:</span>
                                <span class="text-green-700 font-medium">-{{ item.discount_percent }}% ({{ formatCurrency((item.original_price - item.unit_price) * item.quantity) }})</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 p-3 bg-gray-50 rounded">
                    <div class="flex justify-between mb-2">
                        <span>Subtotal:</span>
                        <span>${{ subtotal.toFixed(2) }}</span>
                    </div>
                    
                    <!-- Descuento manual si existe -->
                    <div v-if="discount > 0" class="flex justify-between mb-2 text-red-600">
                        <span>Descuento manual:</span>
                        <span>-${{ (discount || 0).toFixed(2) }}</span>
                    </div>
                    
                    <div class="flex justify-between font-bold text-lg pt-2 border-t">
                        <span>Total:</span>
                        <span>${{ total.toFixed(2) }}</span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <SecondaryButton @click="showPaymentModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="processSale">Confirmar Venta</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal de éxito con opción de imprimir -->
        <Modal :show="showInvoiceModal" @close="showInvoiceModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4 text-green-600">¡Venta registrada exitosamente!</h2>
                
                <div v-if="lastCreatedSale" class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        Número de venta: <span class="font-semibold">#{{ lastCreatedSale.sale_number }}</span>
                    </p>
                    <p class="text-sm text-gray-600 mb-2">
                        Total: <span class="font-semibold">{{ formatCurrency(lastCreatedSale.total) }}</span>
                    </p>
                </div>

                <div class="flex gap-3">
                    <SecondaryButton @click="showInvoiceModal = false">Cerrar</SecondaryButton>
                    <PrimaryButton 
                        v-if="lastCreatedSale"
                        @click="printInvoice(lastCreatedSale)"
                    >
                        🖨️ Imprimir Factura
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal de escáner (placeholder - necesitarías integrar una librería real) -->
        <Modal :show="showBarcodeScanner" @close="closeBarcodeScanner">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">Escanear Código de Barras</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Por favor, ingresa el código de barras manualmente o usa un lector externo.
                </p>
                <div class="flex gap-2">
                    <input
                        v-model="barcodeInput"
                        type="text"
                        placeholder="Código de barras..."
                        class="flex-1 rounded-md border-gray-300"
                        @keyup.enter="searchByBarcode(barcodeInput)"
                    />
                    <button
                        @click="searchByBarcode(barcodeInput)"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                    >
                        Buscar
                    </button>
                </div>
                <SecondaryButton @click="closeBarcodeScanner" class="mt-4">Cerrar</SecondaryButton>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

