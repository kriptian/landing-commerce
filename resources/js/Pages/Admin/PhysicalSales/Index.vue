<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { downloadPDF, sharePDF } from '@/Utils/pdfUtils';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import ExpenseModal from './ExpenseModal.vue';
import VariantSelectorModal from './VariantSelectorModal.vue';

const props = defineProps({
    sales: Object,
    stats: Object,
    filters: Object,
    products: Array,
    categories: Array,
    store: Object,
});

const page = usePage();
const hasPhysicalSalesRole = computed(() => {
    const roles = page.props.auth?.roles || [];
    return roles.includes('physical-sales');
});
const hasElevatedAccess = computed(() => (
    page.props.auth?.user?.is_admin
    || (page.props.auth?.roles || []).includes('Administrador')
));
const canOverridePrices = computed(() => (
    hasElevatedAccess.value
    || (page.props.auth?.permissions || []).includes('modificar precios y descuentos pos')
));
const canRegisterExpenses = computed(() => (
    hasElevatedAccess.value
    || (page.props.auth?.permissions || []).includes('registrar gastos')
));

const handleExit = () => {
    if (hasPhysicalSalesRole.value) {
        // Si tiene el rol physical-sales, desloguear
        // Usar router.post con onSuccess para asegurar que se ejecute
        router.post(route('logout'), {}, {
            onSuccess: () => {
                // Redirigir al login después del logout
                window.location.href = route('login');
            },
            onError: () => {
                // Si hay error, forzar redirección
                window.location.href = route('login');
            }
        });
    } else {
        // Si no tiene el rol, ir al dashboard
        router.visit(route('dashboard'));
    }
};

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
const isGeneratingPDF = ref(false);

const downloadInvoicePDF = async () => {
    if (!lastCreatedSale.value) return;
    isGeneratingPDF.value = true;
    try {
        const element = document.getElementById('invoice-content-pos');
        if (element) {
            await downloadPDF(element, `factura-${lastCreatedSale.value.sale_number}.pdf`);
        }
    } catch (error) {
        console.error(error);
        showPosNotice('Intenta nuevamente o imprime el comprobante.', 'No se pudo generar el PDF');
    } finally {
        isGeneratingPDF.value = false;
    }
};

const shareInvoicePDF = async () => {
    if (!lastCreatedSale.value) return;
    isGeneratingPDF.value = true;
    try {
        const element = document.getElementById('invoice-content-pos');
        if (element) {
            await sharePDF(element, `factura-${lastCreatedSale.value.sale_number}.pdf`, `Factura #${lastCreatedSale.value.sale_number}`);
        }
    } catch (error) {
        console.error(error);
    } finally {
        isGeneratingPDF.value = false;
    }
};

// Modal de selección de variantes
const showVariantSelectorModal = ref(false);
const selectedProductForVariant = ref(null);

const openVariantSelector = (product) => {
    selectedProductForVariant.value = product;
    showVariantSelectorModal.value = true;
};

const handleVariantAddToCart = (product, variant) => {
    addToCart(product, variant);
};

const handleProductClick = (product) => {
    // Si tiene variantes REALES, abrir modal
    // Validamos variants Y variant_options para ignorar basura (productos simples que parecen variantes)
    if (product.variants && product.variants.length > 0 && product.variant_options && product.variant_options.length > 0) {
        
        // FALLBACK: Si variantes suman 0 stock pero hay cantidad global > 0, tratar como simple (ghost variants)
        const vSum = product.variants.reduce((acc, v) => acc + (Number(v.stock)||0), 0);
        if (vSum === 0 && (Number(product.quantity)||0) > 0) {
            addToCart(product);
            return;
        }

        openVariantSelector(product);
    } else {
        addToCart(product);
    }
};

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
    if (!value && value !== 0) return '$0';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const formatNumber = (value) => {
    if (!value && value !== 0) return '0';
    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
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
    return formatCurrency(value);
};

// Calcular ganancia de una venta individual
const calculateSaleProfit = (sale) => {
    let totalProfit = 0;
    let hasCost = false;
    
    // Verificar items
    if (sale.items && sale.items.length > 0) {
        sale.items.forEach(item => {
            const cost = parseFloat(item.purchase_price) || 0;
            
            if (cost > 0) {
                hasCost = true;
                totalProfit += (parseFloat(item.unit_price) - cost) * parseFloat(item.quantity);
            }
        });
    }
    
    return hasCost ? formatCurrency(totalProfit) : '-';
};

// Estado del carrito de venta
const cartItems = ref([]);
const searchQuery = ref('');
const searchInput = ref(null); // Ref para el input de búsqueda
const searchResults = ref([]);
const isSearching = ref(false);

// Función para enfocar el input de búsqueda
const focusSearchInput = () => {
    // Solo enfocar en desktop donde existe este input específico
    if (window.innerWidth >= 1024) {
        nextTick(() => {
            if (searchInput.value) {
                searchInput.value.focus();
            }
        });
    }
};
const showBarcodeScanner = ref(false);
const barcodeInput = ref('');
const showPaymentModal = ref(false);
const paymentMethod = ref('efectivo');
const amountTendered = ref(0); // Campo para dinero recibido
const isProcessingSale = ref(false);
const saleIdempotencyKey = ref(window.crypto.randomUUID());

// Reset cash input when opening payment modal
watch(showPaymentModal, (newValue) => {
    if (newValue) {
        amountTendered.value = 0;
    }
});
const saleNotes = ref('');
const discount = ref(0);
const discountType = ref('amount'); // 'amount' o 'percentage'
const selectedCategory = ref(null);
const selectedCustomer = ref(null);
const showMobileMenu = ref(false);
const showMobileCart = ref(false);
const showProductDiscountModal = ref(false);
const showGeneralDiscountModal = ref(false);
const showProductCatalogModal = ref(false);
const selectedProductIndex = ref(null);
const productDiscountType = ref('percentage');
const productDiscountValue = ref(0);
const showStockAlertModal = ref(false);
const stockAlertMessage = ref('');
const posNotice = ref({ show: false, type: 'error', title: '', message: '' });
const showPosNotice = (message, title = 'No se pudo completar la accion', type = 'error') => {
    posNotice.value = { show: true, type, title, message };
};
// Referencias para el escáner de código de barras
const html5QrCode = ref(null);

// Computed para el valor máximo del descuento
const maxDiscountValue = computed(() => {
    if (productDiscountType.value === 'percentage') {
        return 100;
    }
    
    if (selectedProductIndex.value === null) return 0;
    
    const item = cartItems.value[selectedProductIndex.value];
    if (!item) return 0;
    
    return item.original_price || item.unit_price || 0;
});

// Computed para calcular el cambio
const changeAmount = computed(() => {
    if (paymentMethod.value !== 'efectivo' || !amountTendered.value) return 0;
    const tendered = parseFloat(amountTendered.value);
    return Math.max(0, tendered - total.value);
});

// Modal de gastos
const showExpenseModal = ref(false);
const showSuccessExpenseModal = ref(false);

const handleExpenseSuccess = () => {
    showSuccessExpenseModal.value = true;
    // router.reload({ only: ['stats'] }); 
};

// Función para reproducir beep
const playBeep = () => {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800; // Frecuencia del beep
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.2);
    } catch (error) {
        // Silenciar error de beep
    }
};

// Calcular totales
const subtotal = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
});
const cartUnits = computed(() => cartItems.value.reduce((sum, item) => sum + item.quantity, 0));

const discountAmount = computed(() => {
    let value;
    if (discountType.value === 'percentage') {
        value = (subtotal.value * discount.value) / 100;
    } else {
        value = Number(discount.value) || 0;
    }

    return Math.min(subtotal.value, Math.max(0, value));
});

// Costo de envío
const includeDeliveryCost = ref(false);
const deliveryCost = computed(() => (
    props.store?.delivery_cost_active ? (parseFloat(props.store.delivery_cost) || 0) : 0
));

const total = computed(() => {
    let t = subtotal.value - discountAmount.value;
    if (includeDeliveryCost.value) {
        t += parseFloat(deliveryCost.value) || 0;
    }
    return t;
});

// Filtrar productos por categoría
const filteredProducts = computed(() => {
    if (!props.products) return [];
    
    let products = props.products;
    
    // Filtrar por categoría si está seleccionada
    if (selectedCategory.value) {
        products = products.filter(p => p.category_id === selectedCategory.value);
    }
    
    // Filtrar por búsqueda si existe
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        products = products.filter(p => 
            p.name.toLowerCase().includes(query) ||
            p.id.toString().includes(query) ||
            (p.barcode && p.barcode.toLowerCase().includes(query)) ||
            // Buscar en opciones de variantes (ej: "Rojo", "XL", o código de barras de variante)
            (p.variant_options && p.variant_options.some(opt => 
                opt.children && opt.children.some(child => 
                    child.name.toLowerCase().includes(query) || 
                    (child.barcode && child.barcode.toLowerCase().includes(query))
                )
            )) || 
            // Buscar en variants (SKU)
            (p.variants && p.variants.some(v => 
                (v.sku && v.sku.toLowerCase().includes(query)) ||
                (v.options && Object.values(v.options).some(val => 
                    String(val).toLowerCase().includes(query)
                ))
            ))
        );
    }
    
    return products;
});

// Buscar productos
const searchProducts = async () => {
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await window.axios.get(route('admin.physical-sales.search-products'), {
            params: { q: searchQuery.value }
        });
        const products = response.data?.products || [];
        searchResults.value = products;
        
        // En móvil, si hay un solo resultado, agregarlo automáticamente
        if (products.length === 1 && window.innerWidth < 1024) {
            handleProductClick(products[0]);
            searchQuery.value = '';
            searchResults.value = [];
        }
    } catch (error) {
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

// Buscar por código de barras
const searchByBarcode = async (barcode) => {
    // Limpiar el código de barras: eliminar todos los espacios
    // Los códigos de barras pueden venir con espacios (ej: "7 898024 397861" -> "7898024397861")
    const cleanedBarcode = barcode.replace(/\s+/g, '').trim();
    
    if (!cleanedBarcode) return;

    try {
        const response = await window.axios.get(route('admin.physical-sales.get-product-by-barcode'), {
            params: { barcode: cleanedBarcode }
        });
        
        if (response.data?.product) {
            const product = response.data.product;
            const matchedOption = response.data.matched_variant_option;
            
            // Lógica para auto-seleccionar variante si viene una opción específica
            let variantToAdd = null;
            if (matchedOption && product.variants && product.variants.length > 0) {
                // Buscar la variante que coincida con esta opción
                // Nota: variant.options es un objeto { "Tamaño": "S", "Color": "Rojo" }
                // matchedOption.name es "S" o "Rojo"
                const matches = product.variants.filter(v => {
                    return v.options && Object.values(v.options).some(val => val === matchedOption.name);
                });
                
                // Si hay EXÁCTAMENTE UNA coincidencia, la usamos.
                // Si hay más (ambigüedad) o cero, dejamos que el usuario elija en el modal.
                if (matches.length === 1) {
                    variantToAdd = matches[0];
                }
            }

            if (variantToAdd) {
                addToCart(product, variantToAdd);
                barcodeInput.value = '';
            } else {
                handleProductClick(product);
                barcodeInput.value = '';
            }
        } else {
            showPosNotice('No encontramos un producto con ese codigo de barras.', 'Producto no encontrado');
            focusSearchInput();
        }
    } catch (error) {
        showPosNotice('Revisa la conexion e intenta buscar el producto nuevamente.', 'No se pudo buscar el producto');
        focusSearchInput();
    }
};

// Calcular precio con descuento
const calculatePriceWithDiscount = (product, variant = null) => {
    // Precio base: variante o producto
    const basePrice = variant?.price ?? product.price;
    let finalPrice = parseFloat(basePrice);
    let discountPercent = 0;
    
    if (props.store?.promo_active && props.store?.promo_discount_percent > 0) {
        discountPercent = parseFloat(props.store.promo_discount_percent);
        finalPrice = Math.round((finalPrice * (100 - discountPercent) / 100) * 100) / 100;
    } else if (product.promo_active && product.promo_discount_percent > 0) {
        discountPercent = parseFloat(product.promo_discount_percent);
        finalPrice = Math.round((finalPrice * (100 - discountPercent) / 100) * 100) / 100;
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
    
    // Enfocar nuevamente el input de búsqueda para permitir escaneo continuo
    focusSearchInput();
};

// Obtener stock disponible de un item
const getAvailableStock = (item) => {
    // PRIMERO: Verificar si el inventario está desactivado globalmente
    // Manejo robusto de falsy/string '0'
    if (item.product) {
        const track = item.product.track_inventory;
        if (!track || track === '0' || track === 0 || track === false) {
            return 'Ilimitado';
        }
    }

    // Si tiene variante, usar el stock de la variante
    if (item.variant && item.variant.stock !== null && item.variant.stock !== undefined) {
        return item.variant.stock;
    }
    
    if (item.product) {
        // Verificar si tiene quantity (stock del producto)
        if (item.product.quantity !== null && item.product.quantity !== undefined) {
            return item.product.quantity;
        }
        // Si no tiene quantity definido, retornar 'N/A' (no se controla inventario)
        return 'N/A';
    }
    return 'N/A';
};

// Verificar si hay stock suficiente
const hasEnoughStock = (item) => {
    const availableStock = getAvailableStock(item);
    if (availableStock === 'Ilimitado' || availableStock === 'N/A') {
        return true; // Permitir si es ilimitado o N/A
    }
    return availableStock >= item.quantity;
};

// Obtener clase CSS para el estado del stock
const getStockStatusClass = (item) => {
    if (!hasEnoughStock(item)) {
        return 'bg-red-50';
    }
    const stock = getAvailableStock(item);
    if (stock === 0 || (typeof stock === 'number' && stock < item.quantity)) {
        return 'bg-yellow-50';
    }
    return '';
};

// Obtener clase CSS para el texto del stock
const getStockTextClass = (item) => {
    const stock = getAvailableStock(item);
    if (stock === 0) {
        return 'text-red-600 font-semibold text-xs';
    }
    if (typeof stock === 'number' && stock < item.quantity) {
        return 'text-red-600 font-semibold text-xs';
    }
    if (typeof stock === 'number' && stock < 5) {
        return 'text-yellow-600 font-medium text-xs';
    }
    return 'text-gray-600 text-xs';
};

// Cancelar venta y limpiar carrito
const cancelSale = () => {
    cartItems.value = [];
    discount.value = 0;
    discountType.value = 'amount';
    focusSearchInput();
};

// Remover item del carrito
const removeFromCart = (index) => {
    cartItems.value.splice(index, 1);
    focusSearchInput(); // Enfocar también al eliminar un item singular
};

// Actualizar cantidad
const updateQuantity = (index, delta) => {
    const item = cartItems.value[index];
    const newQuantity = item.quantity + delta;
    if (newQuantity > 0) {
        const availableStock = getAvailableStock(item);
        // Si hay stock limitado, verificar que no exceda el disponible
        if (typeof availableStock === 'number' && newQuantity > availableStock) {
            stockAlertMessage.value = `No hay suficiente stock para el producto "${item.product_name}".\n\nStock disponible: ${availableStock}\nCantidad solicitada: ${newQuantity}`;
            showStockAlertModal.value = true;
            return;
        }
        item.quantity = newQuantity;
    }
};

// Actualizar cantidad desde input
const updateQuantityInput = (index, event) => {
    const value = parseInt(event.target.value);
    if (value > 0) {
        const item = cartItems.value[index];
        const availableStock = getAvailableStock(item);
        // Si hay stock limitado, verificar que no exceda el disponible
        if (typeof availableStock === 'number' && value > availableStock) {
            stockAlertMessage.value = `No hay suficiente stock para el producto "${item.product_name}".\n\nStock disponible: ${availableStock}\nCantidad solicitada: ${value}`;
            showStockAlertModal.value = true;
            event.target.value = item.quantity; // Restaurar valor anterior
            return;
        }
        cartItems.value[index].quantity = value;
    }
};

// Actualizar precio desde input
const updatePrice = (index, event) => {
    // Implementación antigua reemplazada por updatePriceInput
};

// Formatear valor numérico a moneda para input (sin símbolo $)
const formatNumberForInput = (value) => {
    if (value === null || value === undefined || value === '') return '';
    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
        useGrouping: true,
    }).format(value);
};

// Parsear valor moneda de input a número
const parseMoneyInput = (value) => {
    if (typeof value === 'number') return value;
    if (!value) return 0;
    // Eliminar puntos y otros caracteres no numéricos excepto coma decimal si la hubiera (aunque es CO, usamos enteros mayormente)
    const cleanValue = value.toString().replace(/\./g, '').replace(/[^0-9]/g, '');
    return parseFloat(cleanValue) || 0;
};

// Manejar input de precio (Valor)
const handlePriceInput = (index, event) => {
    let value = event.target.value;
    
    // Guardar posición del cursor para intentar mantenerla (básico)
    // En inputs formateados esto es complejo, pero para añadir ceros al final funciona bien
    
    // Parsear el valor limpio
    const numberValue = parseMoneyInput(value);
    
    // Actualizar el modelo
    const item = cartItems.value[index];
    
    // Si hay descuento aplicado, actualizar el precio original
    if (item.discount_percent > 0) {
        // Recalcular precio original basado en el nuevo precio final
        item.unit_price = numberValue;
        item.original_price = numberValue / (1 - item.discount_percent / 100);
    } else {
        item.unit_price = numberValue;
        item.original_price = numberValue;
    }

    // Formatear el input para visualización inmediata
    // Solo formatear si el usuario no está borrando todo (permitir campo vacío)
    if (value !== '') {
        event.target.value = formatNumberForInput(numberValue);
    }
};

// Manejar input de dinero recibido (Efectivo) con formato
const handleAmountTenderedInput = (event) => {
    let value = event.target.value;
    const numberValue = parseMoneyInput(value);
    amountTendered.value = numberValue;
    
    if (value !== '') {
        event.target.value = formatNumberForInput(numberValue);
    }
};

// Actualizar descuento desde input
const updateDiscountFromInput = (index, event) => {
    let value = parseFloat(event.target.value) || 0;
    
    // Limitar a 2 decimales
    value = Math.round(value * 100) / 100;
    
    // Limitar entre 0 y 100
    const discountPercent = Math.min(100, Math.max(0, value));
    
    const item = cartItems.value[index];
    
    if (!item.original_price) {
        item.original_price = item.unit_price;
    }
    
    // Si se edita manualmente, se asume que es porcentaje
    item.discount_type = 'percentage';
    item.discount_value = discountPercent;
    item.discount_percent = discountPercent;
    item.unit_price = item.original_price * (1 - discountPercent / 100);
    
    // Actualizar el valor en el input si fue modificado por las reglas
    if (value !== discountPercent) {
        event.target.value = discountPercent;
    }
};

// Abrir modal de descuento de producto
const openProductDiscountModal = (index) => {
    selectedProductIndex.value = index;
    const item = cartItems.value[index];
    // Si ya tiene descuento, cargar datos guardados
    if (item.discount_type) {
        productDiscountType.value = item.discount_type;
        productDiscountValue.value = item.discount_value || 0;
    } else if (item.discount_percent > 0) {
        // Compatibilidad con items anteriores
        productDiscountType.value = 'percentage';
        productDiscountValue.value = item.discount_percent;
    } else {
        // Por defecto
        productDiscountType.value = 'percentage';
        productDiscountValue.value = 0;
    }
    showProductDiscountModal.value = true;
};

// Aplicar descuento a producto
const applyProductDiscount = () => {
    if (selectedProductIndex.value === null) return;
    
    const item = cartItems.value[selectedProductIndex.value];
    // Guardar precio original si no existe
    if (!item.original_price) {
        item.original_price = item.unit_price;
    }
    const basePrice = item.original_price;
    
    if (productDiscountType.value === 'percentage') {
        const discountPercent = Math.min(100, Math.max(0, productDiscountValue.value));
        // Redondear a 2 decimales
        const roundedPercent = Math.round(discountPercent * 100) / 100;
        
        item.discount_type = 'percentage';
        item.discount_value = roundedPercent;
        item.discount_percent = roundedPercent;
        item.unit_price = basePrice * (1 - roundedPercent / 100);
    } else {
        const discountAmount = Math.min(basePrice, Math.max(0, productDiscountValue.value));
        
        item.discount_type = 'amount';
        item.discount_value = discountAmount;
        // Calculamos porcentaje solo para referencia interna
        item.discount_percent = (discountAmount / basePrice) * 100;
        item.unit_price = basePrice - discountAmount;
    }
    
    showProductDiscountModal.value = false;
    selectedProductIndex.value = null;
    productDiscountValue.value = 0;
};

// Abrir modal de descuento general
const openGeneralDiscountModal = () => {
    showGeneralDiscountModal.value = true;
};

// Aplicar descuento general
const applyGeneralDiscount = () => {
    showGeneralDiscountModal.value = false;
};

// Actualizar precio desde input (nuevo método)
// La función updatePriceFromInput ha sido reemplazada por handlePriceInput
// La función updateDiscountFromInput ha sido movida arriba

// Recalcular total del item
const recalculateItemTotal = (index) => {
    const item = cartItems.value[index];
    if (item.original_price && item.discount_percent > 0) {
        item.unit_price = item.original_price * (1 - item.discount_percent / 100);
    }
};

// Inicializar escáner de código de barras
const initBarcodeScanner = async () => {
    try {
        showBarcodeScanner.value = true;
        await nextTick();
        
        const { Html5Qrcode } = await import('html5-qrcode');
        const scannerElement = document.getElementById('barcode-scanner-sales');
        if (!scannerElement) return;

        html5QrCode.value = new Html5Qrcode(scannerElement.id);
        
        // Configuración para códigos de barras
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        };

        // Intentar usar cámara trasera primero, luego frontal
        try {
            await html5QrCode.value.start(
                { facingMode: "environment" },
                config,
                async (decodedText) => {
                    // Reproducir beep al detectar código
                    playBeep();
                    // Cuando se detecta un código, buscar el producto y agregarlo al carrito
                    await searchByBarcode(decodedText);
                    closeBarcodeScanner();
                },
                (errorMessage) => {
                    // Ignorar errores de escaneo continuo
                }
            );
        } catch (err) {
            // Si falla con cámara trasera, intentar frontal
            try {
                await html5QrCode.value.start(
                    { facingMode: "user" },
                    config,
                    async (decodedText) => {
                        // Reproducir beep al detectar código
                        playBeep();
                        // Cuando se detecta un código, buscar el producto y agregarlo al carrito
                        await searchByBarcode(decodedText);
                        closeBarcodeScanner();
                    },
                    (errorMessage) => {
                        // Ignorar errores de escaneo continuo
                    }
                );
            } catch (err2) {
                showPosNotice('Permite el acceso a la camara en la configuracion del navegador e intenta nuevamente.', 'Camara no disponible');
                showBarcodeScanner.value = false;
            }
        }
    } catch (error) {
        showPosNotice('Cierra otras aplicaciones que usen la camara e intenta nuevamente.', 'No se pudo iniciar el escaner');
        showBarcodeScanner.value = false;
    }
};

// Cerrar escáner
const closeBarcodeScanner = async () => {
    if (html5QrCode.value) {
        try {
            await html5QrCode.value.stop();
            await html5QrCode.value.clear();
        } catch (err) {
            // Error silenciado
        }
        html5QrCode.value = null;
    }
    showBarcodeScanner.value = false;
};

// Abrir cajón de la registradora
const openCashDrawer = async () => {
    try {
        if (!window.axios) {
            showPosNotice('Recarga la pagina para restablecer la conexion con la impresora.', 'Conexion no disponible');
            return;
        }

        const response = await window.axios.post(route('admin.physical-sales.open-drawer'));
        
        if (response.data && response.data.success) {
            // Éxito silencioso - el cajón debería abrirse
        } else {
            showPosNotice('Verifica que la impresora este conectada y configurada.', 'No se pudo abrir el cajon');
        }
    } catch (error) {
        console.error('Error al intentar abrir el cajón:', error);
        showPosNotice('Verifica que la impresora este conectada y configurada.', 'No se pudo abrir el cajon');
    }
};

// Función para asegurar que el token CSRF esté actualizado
const ensureCsrfToken = () => {
    // Leer directamente del meta tag
    const metaTag = document.head.querySelector('meta[name="csrf-token"]');
    if (metaTag && metaTag.content) {
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = metaTag.content;
            // También actualizar X-XSRF-TOKEN por si acaso
            window.axios.defaults.headers.common['X-XSRF-TOKEN'] = metaTag.content;
        }
        return metaTag.content;
    }
    // Fallback: leer de cookies
    const getCookie = (name) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    };
    const xsrfToken = getCookie('XSRF-TOKEN');
    if (xsrfToken && window.axios) {
        const decodedToken = decodeURIComponent(xsrfToken);
        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = decodedToken;
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = decodedToken;
        return decodedToken;
    }
    return null;
};

// Asegurar que el token CSRF esté actualizado cuando se carga la página
// Esto es especialmente importante después del login cuando el token se regenera
onMounted(() => {
    // Enfocar el input de búsqueda inicialmente
    focusSearchInput();

    // Esperar a que el DOM esté completamente cargado y el meta tag esté actualizado
    // Después del login, Inertia puede tardar un momento en actualizar el meta tag
    setTimeout(() => {
        ensureCsrfToken();
        // También actualizar la función global si existe
        if (window.updateCsrfToken) {
            window.updateCsrfToken();
        }
        // Forzar una segunda actualización después de un breve delay para asegurar
        // que el token del servidor esté disponible
        setTimeout(() => {
            ensureCsrfToken();
        }, 300);
    }, 100);
});

// También escuchar cuando Inertia termina de cargar la página (después de redirecciones)
router.on('finish', () => {
    // Actualizar el token después de cada navegación de Inertia
    // Esto es crítico después del login
    setTimeout(() => {
        // Leer el token desde las props de Inertia si está disponible (más confiable después del login)
        const page = usePage();
        const csrfTokenFromProps = page.props.csrf_token;
        
        if (csrfTokenFromProps) {
            // Actualizar el meta tag con el token de las props
            const metaTag = document.head.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', csrfTokenFromProps);
            }
            // Actualizar axios
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfTokenFromProps;
                window.axios.defaults.headers.common['X-XSRF-TOKEN'] = csrfTokenFromProps;
            }
        }
        
        // También usar la función normal como respaldo
        ensureCsrfToken();
        if (window.updateCsrfToken) {
            window.updateCsrfToken();
        }
    }, 200);
});

// Procesar venta
const processSale = async () => {
    if (isProcessingSale.value) return;

    if (cartItems.value.length === 0) {
        showPosNotice('Agrega al menos un producto antes de procesar la venta.', 'El carrito esta vacio');
        return;
    }

    if (paymentMethod.value === 'efectivo' && Number(amountTendered.value) < total.value) {
        showPosNotice('Ingresa un valor igual o mayor al total de la venta.', 'Efectivo insuficiente');
        return;
    }

    // Validar stock antes de procesar
    const itemsWithoutStock = [];
    for (let i = 0; i < cartItems.value.length; i++) {
        const item = cartItems.value[i];
        if (!hasEnoughStock(item)) {
            const stock = getAvailableStock(item);
            itemsWithoutStock.push({
                name: item.product_name,
                requested: item.quantity,
                available: stock
            });
        }
    }

    if (itemsWithoutStock.length > 0) {
        let message = 'No se puede procesar la venta. Los siguientes productos no tienen stock suficiente:\n\n';
        itemsWithoutStock.forEach(item => {
            message += `• ${item.name}: Solicitado ${item.requested}, Disponible ${item.available}\n`;
        });
        stockAlertMessage.value = message;
        showStockAlertModal.value = true;
        return;
    }

    const saleData = {
        items: cartItems.value.map(item => ({
            product_id: item.product_id,
            variant_id: item.variant_id,
            quantity: item.quantity,
            ...(canOverridePrices.value ? { unit_price: item.unit_price } : {}),
        })),
        ...(canOverridePrices.value ? { discount: discountAmount.value } : {}),
        include_delivery: includeDeliveryCost.value,
        payment_method: paymentMethod.value,
        amount_tendered: paymentMethod.value === 'efectivo' ? Number(amountTendered.value) : null,
        notes: saleNotes.value,
        idempotency_key: saleIdempotencyKey.value,
    };

    try {
        isProcessingSale.value = true;
        // Usar axios que ya está configurado con CSRF
        if (!window.axios) {
            showPosNotice('Recarga la pagina para restablecer la conexion e intenta nuevamente.', 'Conexion no disponible');
            return;
        }

        // CRÍTICO: Asegurar que el token CSRF esté actualizado ANTES de la petición
        // Esto es especialmente importante después del login cuando el token se regenera
        
        // Primero intentar obtener el token de las props de Inertia (más confiable después del login)
        const page = usePage();
        let csrfToken = page.props.csrf_token;
        
        if (csrfToken) {
            // Actualizar el meta tag con el token de las props
            const metaTag = document.head.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', csrfToken);
            }
        } else {
            // Si no hay token en las props, usar la función normal
            csrfToken = ensureCsrfToken();
        }
        
        // Si aún no hay token, esperar un momento y volver a intentar (puede estar cargando después del login)
        if (!csrfToken) {
            await new Promise(resolve => setTimeout(resolve, 200));
            csrfToken = ensureCsrfToken();
        }
        
        if (!csrfToken) {
            showPosNotice('Recarga la pagina antes de volver a procesar la venta.', 'Sesion no disponible');
            return;
        }
        
        // Forzar actualización del token en axios antes de la petición
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = csrfToken;

        // Función para obtener el token CSRF directamente del DOM
        const getCurrentCsrfToken = () => {
            const metaTag = document.head.querySelector('meta[name="csrf-token"]');
            if (metaTag && metaTag.content) {
                return metaTag.content;
            }
            // Fallback: leer de cookies
            const getCookie = (name) => {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) return parts.pop().split(';').shift();
                return null;
            };
            const xsrfToken = getCookie('XSRF-TOKEN');
            return xsrfToken ? decodeURIComponent(xsrfToken) : null;
        };

        // Función para hacer la petición con reintento automático en caso de 419
        const makeRequest = async (retryCount = 0) => {
            try {
                // CRÍTICO: Leer el token directamente del DOM justo antes de enviar
                // No confiar en valores en memoria
                let csrfToken = getCurrentCsrfToken();
                
                // Si no hay token, intentar obtenerlo del servidor haciendo una petición GET
                if (!csrfToken && retryCount === 0) {
                    try {
                        // Hacer una petición GET simple para obtener el token actualizado del servidor
                        await window.axios.get(route('admin.physical-sales.index'), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        // El token debería estar en el meta tag después de esta petición
                        await new Promise(resolve => setTimeout(resolve, 100));
                        csrfToken = getCurrentCsrfToken();
                    } catch (e) {
                        // Si falla, continuar con el token que tengamos
                    }
                }
                
                if (!csrfToken) {
                    throw new Error('No se pudo obtener el token CSRF. Por favor, recarga la página.');
                }
                
                // Configurar headers explícitamente con el token actualizado
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
                window.axios.defaults.headers.common['X-XSRF-TOKEN'] = csrfToken;
                
                const response = await window.axios.post(route('admin.physical-sales.store'), saleData, {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-XSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                // Si la respuesta incluye un nuevo token CSRF, actualizarlo
                if (response && response.headers) {
                    const newToken = response.headers['x-csrf-token'] || response.headers['X-CSRF-TOKEN'];
                    if (newToken) {
                        const metaTag = document.head.querySelector('meta[name="csrf-token"]');
                        if (metaTag) {
                            metaTag.setAttribute('content', newToken);
                        }
                        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
                    }
                }
                
                return response;
            } catch (error) {
                // Verificar si el error tiene response (error de servidor)
                if (error.response) {
                    // Si es error 419 y aún no hemos reintentado, actualizar token y reintentar
                    if (error.response.status === 419 && retryCount === 0) {
                        // Intentar obtener el nuevo token de la respuesta del servidor
                        const headers = error.response.headers || {};
                        const newToken = headers['x-csrf-token'] || headers['X-CSRF-TOKEN'] || null;
                        
                        if (newToken) {
                            // Actualizar el meta tag con el nuevo token
                            const metaTag = document.head.querySelector('meta[name="csrf-token"]');
                            if (metaTag) {
                                metaTag.setAttribute('content', newToken);
                            }
                            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
                        } else {
                            // Si no hay token en la respuesta, forzar recarga del token desde el DOM
                            const currentToken = getCurrentCsrfToken();
                            if (currentToken) {
                                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = currentToken;
                            }
                        }
                        // Esperar un momento para asegurar que el token se actualizó
                        await new Promise(resolve => setTimeout(resolve, 200));
                        // Reintentar una vez
                        return await makeRequest(1);
                    }
                } else {
                    // Si no hay response, puede ser un error de red o de conexión
                    // En este caso, intentar actualizar el token y reintentar una vez
                    if (retryCount === 0) {
                        const currentToken = getCurrentCsrfToken();
                        if (currentToken) {
                            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = currentToken;
                        }
                        await new Promise(resolve => setTimeout(resolve, 200));
                        return await makeRequest(1);
                    }
                }
                // Si no es 419 o ya reintentamos, lanzar el error
                throw error;
            }
        };

        const response = await makeRequest();
        
        // Actualizar token CSRF si viene en la respuesta (después de éxito)
        const newToken = response.headers?.['x-csrf-token'] || response.headers?.['X-CSRF-TOKEN'];
        if (newToken) {
            const metaTag = document.head.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', newToken);
            }
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
        }

        if (response.data && response.data.success) {
            // Guardar la venta creada para mostrar opción de imprimir
            lastCreatedSale.value = response.data.sale;
            showPaymentModal.value = false;
            
            // Limpiar carrito
            cartItems.value = [];
            searchQuery.value = '';
            discount.value = 0;
            discountType.value = 'amount';
            saleNotes.value = '';
            paymentMethod.value = 'efectivo';
            selectedCategory.value = null;
            saleIdempotencyKey.value = window.crypto.randomUUID();
            
            // Actualizar token CSRF después de una petición exitosa
            if (window.updateCsrfToken) {
                window.updateCsrfToken();
            }
            
            // Mostrar modal de éxito con opción de imprimir
            showInvoiceModal.value = true;
            
            // Recargar página para actualizar lista de ventas
            router.reload();
        } else {
            showPosNotice(response.data?.message || 'No se pudo procesar la venta.', 'Venta no procesada');
        }
    } catch (error) {
        // Log completo del error para debugging
        console.error('Error completo al procesar la venta:', {
            error: error,
            message: error.message,
            response: error.response,
            status: error.response?.status,
            data: error.response?.data,
            headers: error.response?.headers
        });
        
        let errorMessage = 'Error al procesar la venta';
        
        if (error.response) {
            // El servidor respondió con un código de error
            const status = error.response.status;
            const data = error.response.data;
            
            if (status === 419) {
                // Error 419: Token CSRF expirado o inválido
                errorMessage = 'Tu sesión ha expirado. Por favor, recarga la página e intenta nuevamente.';
                // Intentar actualizar el token y recargar la página después de un breve delay
                if (window.updateCsrfToken) {
                    window.updateCsrfToken();
                }
                // Recargar la página después de 2 segundos para que el usuario vea el mensaje
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else if (status === 422) {
                // Error de validación
                if (data?.message) {
                    errorMessage = data.message;
                } else if (data?.errors) {
                    // Si hay errores de validación específicos, mostrarlos
                    const errorMessages = Object.entries(data.errors)
                        .map(([key, messages]) => {
                            if (Array.isArray(messages)) {
                                return `${key}: ${messages.join(', ')}`;
                            }
                            return `${key}: ${messages}`;
                        })
                        .join('\n');
                    errorMessage = `Error de validación:\n${errorMessages}`;
                } else {
                    errorMessage = 'Error de validación. Verifica los datos e intenta nuevamente.';
                }
            } else if (data?.message) {
                errorMessage = data.message;
            } else {
                errorMessage = `Error del servidor (${status}). Por favor, intenta nuevamente.`;
            }
        } else if (error.request) {
            errorMessage = 'No se pudo conectar con el servidor. Verifica tu conexión a internet.';
        } else if (error.message) {
            errorMessage = error.message;
        }
        
        showPosNotice(errorMessage, 'Venta no procesada');
    } finally {
        isProcessingSale.value = false;
    }
};

const hasActivePromotion = (product) => (
    (props.store?.promo_active && props.store?.promo_discount_percent > 0)
    || (product.promo_active && product.promo_discount_percent > 0)
);

// Limpiar al desmontar
onBeforeUnmount(() => {
    closeBarcodeScanner();
});

// Watch para búsqueda automática y detección de código de barras
let searchTimeout;
watch(searchQuery, async (newValue) => {
    clearTimeout(searchTimeout);
    
    // Si el valor parece un código de barras (solo números, más de 8 dígitos), intentar buscar directamente
    const cleanedValue = newValue.replace(/\s+/g, '').trim();
    if (cleanedValue.length >= 8 && /^\d+$/.test(cleanedValue)) {
        // Parece un código de barras, intentar buscar y agregar automáticamente
        try {
            const response = await window.axios.get(route('admin.physical-sales.get-product-by-barcode'), {
                params: { barcode: cleanedValue }
            });
            
            if (response.data?.product) {
                const product = response.data.product;
                const matchedOption = response.data.matched_variant_option;
                
                let variantToAdd = null;
                if (matchedOption && product.variants && product.variants.length > 0) {
                    const matches = product.variants.filter(v => {
                         return v.options && Object.values(v.options).some(val => val === matchedOption.name);
                    });
                    if (matches.length === 1) {
                        variantToAdd = matches[0];
                    }
                }
                
                if (variantToAdd) {
                    addToCart(product, variantToAdd);
                } else {
                     // Si tiene variantes y no se encontró una específica única,
                     // handleProductClick abrirá el modal de forma estándar
                     handleProductClick(product);
                }
                
                searchQuery.value = '';
                playBeep();
                return;
            }
        } catch (error) {
            // Si no se encuentra, continuar con búsqueda normal
        }
    }
    
    // Búsqueda normal
    searchTimeout = setTimeout(() => {
        if (searchQuery.value.trim().length >= 2) {
            searchProducts();
        } else {
            searchResults.value = [];
        }
    }, 300);
});

// Watch global para enfocar el buscador al cerrar cualquier modal
watch([
    showVariantSelectorModal, 
    showProductCatalogModal, 
    showProductDiscountModal, 
    showGeneralDiscountModal, 
    showPaymentModal, 
    showInvoiceModal,
    showExpenseModal,
    showStockAlertModal
], (newValues, oldValues) => {
    // Si alguno cambió a false (se cerró), y estamos en desktop, enfocar
    const someClosed = newValues.some((val, index) => !val && oldValues[index]);
    if (someClosed) {
        // Pequeño delay para asegurar que sobrescribimos el "restore focus" de la librería del modal
        setTimeout(() => {
            focusSearchInput();
        }, 150); 
    }
});

</script>

<template>
    <Head title="Ventas Físicas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 v-if="activeTab !== 'sales'" class="font-semibold text-xl text-gray-800 leading-tight">Ventas Físicas</h2>
        </template>

        <!-- Vista de Ventas (POS) -->
        <!-- Vista Desktop -->
        <div v-if="activeTab === 'sales'" class="fixed inset-0 z-10 hidden gap-3 bg-slate-100 p-3 lg:flex">
            <section class="flex h-full w-[58%] min-w-0 flex-col overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
                <header class="shrink-0 border-b border-slate-200 p-4">
                    <div class="flex items-center gap-3">
                        <button type="button" class="flex h-11 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-bold text-slate-600 hover:bg-slate-50" @click="handleExit"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>Salir</button>
                        <div class="relative flex-1"><svg class="absolute left-4 top-3.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg><input ref="searchInput" v-model="searchQuery" type="search" placeholder="Busca por nombre, codigo o variante" class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 pl-11 pr-14 text-sm font-medium focus:border-indigo-500 focus:bg-white focus:ring-indigo-500" @keyup.enter="searchProducts" /><button type="button" class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-lg text-indigo-600 hover:bg-indigo-50" title="Escanear codigo de barras" @click="initBarcodeScanner"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5v4m0-4h4m14 0h-4m4 0v4M3 19v-4m0 4h4m14 0h-4m4 0v-4M7 8v8m3-8v8m4-8v8m3-8v8" /></svg></button></div>
                        <button v-if="canRegisterExpenses" type="button" class="flex h-11 items-center gap-2 rounded-xl bg-rose-50 px-3 text-sm font-bold text-rose-700 hover:bg-rose-100" @click="showExpenseModal = true"><span class="text-lg">+</span> Registrar gasto</button>
                    </div>
                    <div class="mt-4 flex gap-2 overflow-x-auto pb-1"><button type="button" class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-bold transition" :class="selectedCategory === null ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" @click="selectedCategory = null; searchQuery = ''">Todos</button><button v-for="category in categories" :key="category.id" type="button" class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-bold transition" :class="selectedCategory === category.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" @click="selectedCategory = category.id; searchQuery = ''">{{ category.name }}</button></div>
                </header>
                <div class="flex items-center justify-between px-5 pb-1 pt-4"><div><p class="text-xs font-extrabold uppercase tracking-[0.18em] text-indigo-600">Catalogo</p><h1 class="mt-1 text-xl font-black text-slate-950">Elige los productos</h1></div><span class="text-sm font-semibold text-slate-500">{{ filteredProducts.length }} disponibles</span></div>
                <div class="flex-1 overflow-y-auto p-5">
                    <div v-if="!filteredProducts.length" class="flex h-full min-h-64 flex-col items-center justify-center text-center"><span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl">⌕</span><p class="mt-4 font-bold text-slate-800">No encontramos productos</p><p class="mt-1 text-sm text-slate-500">Prueba otra categoria o termino de busqueda.</p></div>
                    <div v-else class="grid grid-cols-2 gap-4 xl:grid-cols-3 2xl:grid-cols-4"><button v-for="product in filteredProducts" :key="product.id" type="button" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white text-left transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-xl hover:shadow-indigo-100/60" @click="handleProductClick(product)"><div class="relative aspect-[4/3] overflow-hidden bg-slate-100"><img v-if="product.main_image_url" :src="product.main_image_url" :alt="product.name" class="h-full w-full object-contain p-2 transition duration-300 group-hover:scale-105" /><div v-else class="flex h-full items-center justify-center text-3xl text-slate-300">□</div><span class="absolute bottom-2 right-2 flex h-9 w-9 items-center justify-center rounded-full bg-slate-950 text-xl font-light text-white shadow-lg transition group-hover:bg-indigo-600">+</span></div><div class="p-3"><h2 class="line-clamp-2 min-h-10 text-sm font-bold leading-5 text-slate-800">{{ product.name }}</h2><div class="mt-2 flex items-end justify-between gap-2"><div><span v-if="hasActivePromotion(product)" class="block text-[11px] text-slate-400 line-through">{{ formatCurrency(calculatePriceWithDiscount(product).originalPrice) }}</span><strong class="text-base text-slate-950">{{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}</strong></div><span v-if="product.track_inventory" class="text-[11px] font-semibold text-slate-400">Stock {{ product.quantity ?? 0 }}</span></div></div></button></div>
                </div>
            </section>

            <section class="flex h-full min-w-0 flex-1 flex-col overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
                <header class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4"><div><div class="flex items-center gap-2"><h2 class="text-xl font-black text-slate-950">Venta actual</h2><span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-extrabold text-indigo-700">{{ cartUnits }} {{ cartUnits === 1 ? 'unidad' : 'unidades' }}</span></div><p class="mt-1 text-sm text-slate-500">{{ page.props.auth?.user?.name || 'Usuario' }} · {{ store?.name }}</p></div><img v-if="store?.logo_url" :src="store.logo_url" :alt="store.name" class="h-11 w-11 rounded-xl border border-slate-200 object-cover" /></header>
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="!cartItems.length" class="flex h-full min-h-72 flex-col items-center justify-center text-center"><span class="flex h-20 w-20 items-center justify-center rounded-3xl bg-indigo-50 text-indigo-500"><svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-1 5h13M9 21h.01M17 21h.01" /></svg></span><p class="mt-5 text-lg font-extrabold text-slate-800">La venta esta lista para comenzar</p><p class="mt-2 max-w-xs text-sm leading-6 text-slate-500">Selecciona un producto del catalogo o escanea su codigo para agregarlo.</p></div>
                    <div v-else class="space-y-3"><article v-for="(item, index) in cartItems" :key="`${item.product_id}-${item.variant_id}`" class="rounded-2xl border p-4" :class="hasEnoughStock(item) ? 'border-slate-200' : 'border-rose-300 bg-rose-50'"><div class="flex gap-3"><img v-if="item.product?.main_image_url" :src="item.product.main_image_url" :alt="item.product_name" class="h-16 w-16 shrink-0 rounded-xl bg-slate-100 object-contain p-1" /><div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-300">□</div><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-3"><div><h3 class="line-clamp-2 font-extrabold text-slate-900">{{ item.product_name }}</h3><p v-if="item.variant_options" class="mt-0.5 text-xs text-slate-500">{{ Object.values(item.variant_options).join(' / ') }}</p><p class="mt-1 text-xs" :class="getStockTextClass(item)">Disponible: {{ getAvailableStock(item) }}</p></div><button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Quitar producto" @click="removeFromCart(index)"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button></div></div></div><div class="mt-4 flex flex-wrap items-end justify-between gap-3 border-t border-slate-100 pt-3"><div><span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Cantidad</span><div class="mt-1 flex items-center rounded-xl border border-slate-200 bg-slate-50 p-1"><button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-lg font-bold text-slate-600 hover:bg-white" @click="updateQuantity(index, -1)">−</button><input type="number" :value="item.quantity" min="1" class="h-8 w-12 border-0 bg-transparent p-0 text-center text-sm font-black focus:ring-0" @change="updateQuantityInput(index, $event)" /><button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-lg font-bold text-slate-600 hover:bg-white" @click="updateQuantity(index, 1)">+</button></div></div><div v-if="canOverridePrices" class="w-32"><label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Precio unitario</label><input type="text" :value="formatNumberForInput(item.original_price || item.unit_price)" class="mt-1 h-10 w-full rounded-xl border-slate-200 text-right text-sm font-bold focus:border-indigo-500 focus:ring-indigo-500" @input="handlePriceInput(index, $event)" /></div><button v-if="canOverridePrices" type="button" class="h-10 rounded-xl px-3 text-xs font-bold" :class="item.discount_percent > 0 ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600'" @click="openProductDiscountModal(index)">{{ item.discount_percent > 0 ? `${formatNumber(item.discount_percent)}% desc.` : 'Descuento' }}</button><div class="ml-auto text-right"><span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Subtotal</span><strong class="mt-1 block text-lg text-slate-950">{{ formatCurrency(item.quantity * item.unit_price) }}</strong></div></div></article></div>
                </div>
                <footer v-if="cartItems.length" class="shrink-0 border-t border-slate-200 bg-slate-50 p-5"><div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm"><span class="text-slate-500">Subtotal</span><strong class="text-right text-slate-800">{{ formatCurrency(subtotal) }}</strong><button v-if="canOverridePrices" type="button" class="text-left font-semibold text-indigo-700" @click="openGeneralDiscountModal">Descuento general</button><span v-else class="text-slate-500">Descuento</span><strong class="text-right" :class="discountAmount ? 'text-rose-600' : 'text-slate-500'">− {{ formatCurrency(discountAmount) }}</strong><label class="flex cursor-pointer items-center gap-2 text-slate-600"><input v-model="includeDeliveryCost" type="checkbox" :disabled="!store?.delivery_cost_active" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />Agregar envio</label><strong class="text-right text-slate-700">{{ includeDeliveryCost ? formatCurrency(deliveryCost) : 'No incluido' }}</strong></div><div class="my-4 flex items-end justify-between border-t border-slate-200 pt-4"><div><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-slate-500">Total a cobrar</p><p class="mt-1 text-3xl font-black tracking-tight text-slate-950">{{ formatCurrency(total) }}</p></div><button type="button" class="text-sm font-bold text-rose-600 hover:text-rose-700" @click="cancelSale">Vaciar venta</button></div><button type="button" class="flex min-h-14 w-full items-center justify-center rounded-2xl bg-indigo-600 px-5 text-lg font-black text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700" @click="showPaymentModal = true">Continuar al pago <span class="ml-2">→</span></button></footer>
            </section>
        </div>

        <!-- Vista Móvil (POS) -->
        <div v-if="activeTab === 'sales'" class="fixed inset-0 flex flex-col bg-slate-50 lg:hidden" style="margin-top: 0; z-index: 10;">
            <!-- Header móvil -->
            <div class="flex-shrink-0 border-b border-slate-200 bg-white px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50"><svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                        </svg></span>
                        <div><strong class="block text-sm text-slate-950">Nueva venta</strong><span class="block max-w-40 truncate text-xs text-slate-500">{{ store?.name }} · {{ page.props.auth?.user?.name || 'Usuario' }}</span></div>
                    </div>
                    <button 
                        @click="handleExit"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600"
                    >
                        Salir
                    </button>
                </div>
            </div>

            <!-- Barra de búsqueda móvil -->
            <div class="flex-shrink-0 border-b border-slate-200 bg-white px-4 py-3">
                <div class="flex items-center gap-2">
                    <!-- Botón catálogo -->
                    <button
                        @click="showProductCatalogModal = true"
                        class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm hover:bg-indigo-700"
                        title="Ver catálogo de productos"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </button>

                    <!-- Botón Gasto Móvil -->
                    <button 
                        v-if="canRegisterExpenses"
                        @click="showExpenseModal = true"
                        class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100"
                        title="Registrar Gasto"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                    
                    <!-- Input de búsqueda -->
                    <div class="flex-1 relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar producto..."
                            class="h-11 w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-12 text-sm font-medium focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
                            @keyup.enter="searchProducts"
                            @input="searchProducts"
                        />
                        <svg class="absolute left-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <button
                            @click="initBarcodeScanner"
                            class="absolute right-2 top-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-indigo-600 hover:bg-indigo-50"
                            title="Escanear código de barras"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2.01M8 8h.01M12 8h.01M16 8h.01M20 8h.01M5 12h2.01M8 12h.01M12 12h2.01M16 12h.01M20 12h.01M5 16h2.01M8 16h.01M12 16h.01M16 16h.01M20 16h.01"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Resultados de búsqueda -->
                <div v-if="searchResults.length > 0 && searchQuery.trim()" class="mt-3 max-h-64 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl">
                    <div
                        v-for="product in searchResults"
                        :key="product.id"
                        @click="handleProductClick(product); searchQuery = ''; searchResults = []"
                        class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-3 hover:bg-indigo-50"
                    >
                        <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center flex-shrink-0">
                            <img 
                                v-if="product.main_image_url" 
                                :src="product.main_image_url" 
                                :alt="product.name"
                                class="w-full h-full object-cover rounded"
                            />
                            <svg v-else class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm text-gray-900 truncate">{{ product.name }}</p>
                            <div>
                                <template v-if="hasActivePromotion(product)">
                                    <p class="text-xs line-through text-red-600">
                                        {{ formatCurrency(calculatePriceWithDiscount(product).originalPrice) }}
                                    </p>
                                    <p class="text-xs text-green-600 font-semibold">
                                        {{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}
                                    </p>
                                </template>
                                <template v-else>
                                    <p class="text-xs text-gray-500">{{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}</p>
                                </template>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Título de información de venta -->
            <div class="flex flex-shrink-0 items-center justify-between bg-slate-100 px-4 py-2.5">
                <h3 class="text-sm font-extrabold text-slate-800">Venta actual</h3>
                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ cartUnits }} {{ cartUnits === 1 ? 'unidad' : 'unidades' }}</span>
            </div>

            <!-- Lista de productos facturados móvil -->
            <div class="flex-1 overflow-y-auto bg-slate-100">
                <div v-if="cartItems.length === 0" class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                    </svg>
                    <p>No hay productos en el carrito</p>
                    <p class="text-xs text-gray-400 mt-2">Busca o escanea un producto para comenzar</p>
                </div>
                
                <div v-else class="space-y-3 p-3">
                    <div
                        v-for="(item, index) in cartItems"
                        :key="index"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <!-- Columna izquierda: Stock, Producto, Precio unitario -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span :class="getStockTextClass(item)">
                                        Stock: {{ getAvailableStock(item) }}
                                    </span>
                                    <span v-if="!hasEnoughStock(item)" class="text-xs text-red-600 font-semibold">
                                        ⚠ Sin stock
                                    </span>
                                </div>
                                <p class="font-medium text-sm text-gray-900 mb-1">{{ item.product_name }}</p>
                                <p class="text-xs text-gray-500">{{ formatCurrency(item.unit_price) }}</p>
                            </div>
                            
                            <!-- Columna derecha: Cantidad y Total -->
                            <div class="flex items-center gap-3">
                                <!-- Cantidad con botones + y - -->
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-xs text-gray-500">Cant</span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            @click="updateQuantity(index, -1)"
                                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-700 hover:bg-slate-200"
                                        >
                                            -
                                        </button>
                                        <span class="flex h-9 w-10 items-center justify-center font-extrabold text-slate-950">
                                            {{ item.quantity }}
                                        </span>
                                        <button
                                            @click="updateQuantity(index, 1)"
                                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-950 font-bold text-white hover:bg-indigo-700"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Total -->
                                <div class="flex flex-col items-end gap-1 min-w-[80px]">

                                    <span class="text-xs text-gray-500">Total</span>
                                    <span class="font-semibold text-sm text-gray-900">
                                        {{ formatCurrency(item.quantity * item.unit_price) }}
                                    </span>
                                </div>
                                
                                <!-- Botón descuento -->
                                <button
                                    v-if="canOverridePrices"
                                    @click="openProductDiscountModal(index)"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-100"
                                    title="Aplicar descuento"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                                
                                <!-- Botón eliminar -->
                                <button
                                    @click="removeFromCart(index)"
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumen y botones móvil -->
            <div v-if="cartItems.length > 0" class="flex-shrink-0 border-t border-slate-200 bg-white px-4 py-4 shadow-[0_-12px_30px_rgba(15,23,42,0.08)]">
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-medium text-green-600">{{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">Descuento:</span>
                        <button
                            v-if="canOverridePrices"
                            type="button"
                            @click="openGeneralDiscountModal"
                            class="font-medium text-green-600 hover:text-blue-600 flex items-center gap-1"
                        >
                            {{ discount > 0 ? '-' + formatCurrency(discountAmount) : formatCurrency(0) }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </button>
                        <span v-else class="font-medium text-green-600">{{ formatCurrency(0) }}</span>
                    </div>

                    <!-- Costo de envío Móvil -->
                    <div class="flex justify-between text-sm items-center">
                        <label class="flex items-center text-gray-700 gap-2 cursor-pointer">
                            <input 
                                type="checkbox" 
                                v-model="includeDeliveryCost"
                                :disabled="!store?.delivery_cost_active"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4"
                            >
                            <span>Incluir Envío:</span>
                        </label>
                        <span v-if="includeDeliveryCost" class="font-medium">{{ formatCurrency(deliveryCost) }}</span>
                        <span v-else class="text-gray-500 text-xs italic">No aplica</span>
                    </div>

                    <div class="flex justify-between items-center pt-2 border-t border-gray-300">
                        <span class="text-sm font-extrabold uppercase tracking-wider text-slate-500">Total a cobrar</span>
                        <span class="text-2xl font-black text-slate-950">{{ formatCurrency(total) }}</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <button
                        @click="showPaymentModal = true"
                        class="min-h-12 flex-1 rounded-2xl bg-indigo-600 px-4 py-3 font-black text-white shadow-lg shadow-indigo-100 transition hover:bg-indigo-700"
                    >
                        Continuar al pago
                    </button>
                    <button
                        @click="cartItems = []; discount = 0; discountType = 'amount'"
                        class="rounded-xl px-3 py-3 text-sm font-bold text-rose-600 hover:bg-rose-50"
                    >
                        Vaciar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modales -->
        <!-- Modal de pago -->
        <Modal :show="showPaymentModal" @close="showPaymentModal = false">
            <div class="p-5 sm:p-7">
                <div class="mb-6 flex items-start justify-between gap-4"><div><p class="text-xs font-extrabold uppercase tracking-[0.18em] text-indigo-600">Ultimo paso</p><h2 class="mt-2 text-2xl font-black text-slate-950">Registrar el pago</h2><p class="mt-1 text-sm text-slate-500">Confirma como recibiste {{ formatCurrency(total) }}.</p></div><button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200" @click="showPaymentModal = false">✕</button></div>
                
                <div class="mb-5">
                    <label class="ui-label">Metodo de pago</label>
                    <div class="grid grid-cols-3 gap-2"><button v-for="method in [{ value: 'efectivo', label: 'Efectivo' }, { value: 'tarjeta', label: 'Tarjeta' }, { value: 'transferencia', label: 'Transferencia' }]" :key="method.value" type="button" class="min-h-12 rounded-xl border px-2 text-sm font-bold transition" :class="paymentMethod === method.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-100' : 'border-slate-200 text-slate-600 hover:border-indigo-200'" @click="paymentMethod = method.value">{{ method.label }}</button></div>
                </div>

                <div v-if="paymentMethod === 'efectivo'" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <label class="ui-label">Efectivo recibido</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-lg font-bold text-slate-400">$</span>
                        <input 
                            :value="formatNumberForInput(amountTendered)"
                            @input="handleAmountTenderedInput"
                            type="text" 
                            inputmode="numeric"
                            class="h-12 w-full rounded-xl border-emerald-200 bg-white pl-9 text-xl font-black focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="0"
                        >
                    </div>
                    
                    <div v-if="amountTendered" class="mt-4 flex items-center justify-between border-t border-emerald-200 pt-3">
                        <span class="text-sm font-semibold text-emerald-900">Cambio a devolver</span>
                        <span class="text-xl font-black text-emerald-700">{{ formatCurrency(changeAmount) }}</span>
                    </div>
                    <p v-if="amountTendered && amountTendered < total" class="mt-2 text-sm font-medium text-red-700">
                        Faltan {{ formatCurrency(total - amountTendered) }} para completar el pago.
                    </p>
                </div>

                <div class="mb-5">
                    <label class="ui-label">Nota para esta venta <span class="font-normal text-slate-400">(opcional)</span></label>
                    <textarea
                        v-model="saleNotes"
                        class="ui-input"
                        rows="2"
                        placeholder="Ej. Pedido para recoger en la tarde"
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

                    <div class="mb-5 rounded-2xl bg-slate-950 p-4 text-white">
                    <div class="flex justify-between mb-2 text-sm text-slate-300">
                        <span>Subtotal</span>
                        <span>{{ formatCurrency(subtotal) }}</span>
                    </div>
                    
                    <!-- Descuento manual si existe -->
                    <div v-if="discountAmount > 0" class="mb-2 flex justify-between text-sm text-rose-300">
                        <span>Descuento ({{ discountType === 'percentage' ? discount + '%' : 'Monto' }}):</span>
                        <span>-{{ formatCurrency(discountAmount) }}</span>
                    </div>

                    <div v-if="includeDeliveryCost && parseFloat(deliveryCost) > 0" class="mb-2 flex justify-between text-sm text-slate-300">
                        <span>Costo de Envío:</span>
                        <span>{{ formatCurrency(deliveryCost) }}</span>
                    </div>
                    
                    <div class="flex justify-between border-t border-white/20 pt-3 text-xl font-black">
                        <span>Total</span>
                        <span>{{ formatCurrency(total) }}</span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" class="ui-secondary-button" @click="showPaymentModal = false">Volver</button>
                    <button type="button" class="ui-primary-button flex-1"
                        @click="processSale"
                        :disabled="isProcessingSale || (paymentMethod === 'efectivo' && amountTendered < total)"
                    >
                        {{ isProcessingSale ? 'Registrando venta...' : 'Confirmar y registrar' }}
                    </button>
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

                <div class="flex gap-2 sm:gap-3 justify-center sm:justify-end">
                    <SecondaryButton @click="showInvoiceModal = false" class="!px-3 sm:!px-4">
                        <span class="sm:hidden">✕</span>
                        <span class="hidden sm:inline">Cerrar</span>
                    </SecondaryButton>
                    
                    <button 
                         v-if="lastCreatedSale"
                         @click="downloadInvoicePDF"
                         class="inline-flex items-center px-3 sm:px-4 py-2 bg-gray-600 border border-transparent rounded-md font-normal text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                         :disabled="isGeneratingPDF"
                         title="Descargar PDF"
                    >
                        <span v-if="isGeneratingPDF" class="animate-pulse">⏳</span>
                        <span v-else>⬇️ <span class="hidden sm:inline ml-1">Descargar PDF</span></span>
                    </button>

                    <button 
                         v-if="lastCreatedSale"
                         @click="shareInvoicePDF"
                         class="inline-flex items-center px-3 sm:px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-normal text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150"
                         :disabled="isGeneratingPDF"
                         title="Compartir Factura"
                    >
                        <span v-if="isGeneratingPDF" class="animate-pulse">⏳</span>
                        <span v-else>🔗 <span class="hidden sm:inline ml-1">Compartir</span></span>
                    </button>

                    <PrimaryButton 
                        v-if="lastCreatedSale"
                        @click="printInvoice(lastCreatedSale)"
                        class="!px-3 sm:!px-4"
                        title="Imprimir Factura"
                    >
                        🖨️ <span class="hidden sm:inline ml-1">Imprimir</span>
                    </PrimaryButton>
                </div>
                
                <!-- Hidden invoice container for PDF generation -->
                <div id="invoice-content-pos" class="fixed top-0 left-0 w-[58mm] bg-white z-[-100] opacity-0 pointer-events-none">
                    <div class="bg-white text-black p-2 font-sans text-xs leading-tight" style="width: 58mm; margin: 0 auto;">
                        <!-- Header -->
                        <div class="text-center mb-4">
                             <img 
                                v-if="store?.logo_url" 
                                :src="store.logo_url" 
                                alt="Logo" 
                                class="h-12 w-auto object-contain mx-auto mb-2 grayscale"
                                crossorigin="anonymous"
                            />
                            <h2 class="font-normal text-base uppercase mb-1">{{ store?.name }}</h2>
                            <div class="text-[11px] space-y-0.5">
                                <p v-if="store?.nit">NIT: {{ store.nit }}</p>
                                <p v-if="store?.address" class="whitespace-normal">{{ store.address }}</p>
                                <p v-if="store?.address_two" class="whitespace-normal">{{ store.address_two }}</p>
                                <p v-if="store?.address_three" class="whitespace-normal">{{ store.address_three }}</p>
                                <p v-if="store?.address_four" class="whitespace-normal">{{ store.address_four }}</p>
                                <p v-if="store?.phone" class="whitespace-normal">Tel: {{ store.phone?.startsWith('57') ? store.phone.substring(2) : store.phone }}</p>
                                <p v-if="store?.email" class="whitespace-normal break-words">{{ store.email }}</p>
                                <p v-if="store?.custom_domain" class="whitespace-normal break-words">{{ store.custom_domain }}</p>
                            </div>
                        </div>

                        <div class="border-b border-dashed border-black my-2"></div>

                        <!-- Info Grid -->
                        <div class="mb-3 text-[11px] grid grid-cols-2 gap-x-2 gap-y-1">
                            <div class="col-span-2 text-center mb-1">
                                <p class="text-sm font-normal">Venta #{{ lastCreatedSale?.sale_number }}</p>
                                <p class="text-[10px]">{{ lastCreatedSale ? formatDate(lastCreatedSale.created_at) : '' }}</p>
                            </div>
                            
                            <div>
                                <span class="font-normal block">Vendedor:</span>
                                <span>{{ lastCreatedSale?.user?.name || $page.props.auth.user.name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-normal block">Método de Pago:</span>
                                <span class="capitalize">{{ lastCreatedSale?.payment_method }}</span>
                            </div>

                            <div v-if="lastCreatedSale?.customer_name" class="col-span-2 mt-1 border-t border-dashed border-gray-300 pt-1">
                                <p><span class="font-normal">Cliente:</span> {{ lastCreatedSale.customer_name }}</p>
                                <p v-if="lastCreatedSale.customer_nit"><span class="font-normal">NIT/CC:</span> {{ lastCreatedSale.customer_nit }}</p>
                            </div>
                        </div>

                        <div class="border-b border-dashed border-black my-2"></div>

                        <!-- Items -->
                        <div class="mb-4">
                             <!-- Simplified Header -->
                             <div class="flex justify-between text-[10px] font-normal mb-2 uppercase">
                                <span>Descripción</span>
                                <span>Total</span>
                            </div>

                            <div v-for="item in lastCreatedSale?.items" :key="item.id" class="mb-3 border-b border-dashed border-gray-200 last:border-0 pb-2">
                                <!-- Top Row: Product Name -->
                                <div class="font-normal text-xs leading-tight mb-0.5">
                                    {{ item.product_name }}
                                </div>
                                
                                <!-- Variant Info -->
                                <div v-if="item.variant_options" class="text-[10px] italic mb-1">
                                    {{ Object.values(item.variant_options).join(' / ') }}
                                </div>

                                <!-- Price / Calculation Row -->
                                <div class="flex justify-between items-start text-[11px] mt-1">
                                     <!-- Left Col: Quantity x Price -->
                                    <div class="flex flex-col">
                                        <!-- Standard calculation line -->
                                        <span>{{ item.quantity }} x {{ formatCurrency(item.unit_price) }}</span>
                                        
                                        <!-- Extended Discount Info -->
                                        <div v-if="item.discount_percent > 0 || (item.original_price && item.original_price > item.unit_price)" 
                                             class="flex flex-col mt-0.5"
                                        >
                                            <!-- Original Price (Strikethrough) -->
                                            <span style="text-decoration: line-through;" class="text-[10px]">
                                                Precio habitual: {{ formatCurrency(item.original_price || (item.unit_price * 100 / (100 - item.discount_percent))) }}
                                            </span>
                                            
                                            <!-- Discount Tag -->
                                            <span class="text-[10px] font-normal">
                                                Desc: {{ item.discount_percent || Math.round((1 - item.unit_price/item.original_price)*100) }}%
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Col: Line Total -->
                                    <div class="font-normal text-xs mt-0.5">
                                        {{ formatCurrency(item.subtotal) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-dashed border-black my-2"></div>

                        <!-- Totals -->
                        <div class="text-right text-xs space-y-1">
                             <div v-if="lastCreatedSale?.discount > 0" class="flex justify-between">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(lastCreatedSale.subtotal) }}</span>
                            </div>
                             <div v-if="lastCreatedSale?.discount > 0" class="flex justify-between">
                                <span>Descuento</span>
                                <span>-{{ formatCurrency(lastCreatedSale.discount) }}</span>
                            </div>
                             <div v-if="parseFloat(lastCreatedSale?.delivery_cost) > 0" class="flex justify-between">
                                <span>Costo de envío</span>
                                <span>{{ formatCurrency(lastCreatedSale.delivery_cost) }}</span>
                            </div>
                             <div class="flex justify-between text-base font-normal pt-1 border-t border-dashed mt-1">
                                <span>TOTAL</span>
                                <span>{{ formatCurrency(lastCreatedSale?.total) }}</span>
                            </div>
                             <!-- Cash/Change Display -->
                             <div class="flex justify-between text-[11px] mt-1 text-green-700 font-normal" v-if="amountTendered > 0 && lastCreatedSale">
                                <span>Efectivo:</span>
                                <span>{{ formatCurrency(amountTendered) }}</span>
                            </div>
                             <div class="flex justify-between text-[11px] text-green-700 font-normal" v-if="amountTendered > lastCreatedSale?.total">
                                <span>Cambio:</span>
                                <span>{{ formatCurrency(amountTendered - lastCreatedSale.total) }}</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-center mt-6 text-[11px] space-y-1 mb-4">
                            <p class="font-normal">¡Gracias por su compra!</p>
                             <div v-if="lastCreatedSale?.notes" class="mt-2 pt-2 border-t border-dashed border-gray-300 text-left">
                                <p class="font-normal text-[10px]">Notas:</p>
                                <p class="italic">{{ lastCreatedSale.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal de escáner de código de barras -->
        <Modal :show="showBarcodeScanner" @close="closeBarcodeScanner">
            <div class="p-6">
                <h2 class="text-lg font-normal mb-4">Escanear Código de Barras</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Apunta la cámara hacia el código de barras. El producto se agregará automáticamente al carrito.
                </p>
                <div id="barcode-scanner-sales" class="w-full rounded-lg overflow-hidden" style="min-height: 300px;"></div>
                <SecondaryButton @click="closeBarcodeScanner" class="mt-4">Cerrar</SecondaryButton>
            </div>
        </Modal>

        <!-- Modal de descuento de producto -->
        <Modal :show="canOverridePrices && showProductDiscountModal" @close="showProductDiscountModal = false">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-normal">
                        {{ selectedProductIndex !== null ? cartItems[selectedProductIndex]?.product_name : 'Descuento de Producto' }}
                    </h2>
                    <button @click="showProductDiscountModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad</label>
                        <input
                            type="number"
                            :value="selectedProductIndex !== null ? cartItems[selectedProductIndex]?.quantity : 1"
                            disabled
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Valor</label>
                        <input
                            type="number"
                            :value="selectedProductIndex !== null ? (cartItems[selectedProductIndex]?.original_price || cartItems[selectedProductIndex]?.unit_price) : 0"
                            disabled
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                        <select 
                            v-model="productDiscountType"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option value="percentage">Porcentaje</option>
                            <option value="amount">Monto</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Descuento {{ productDiscountType === 'percentage' ? '%' : '' }}
                        </label>
                        <input
                            v-model.number="productDiscountValue"
                            type="number"
                            :step="productDiscountType === 'percentage' ? '0.01' : '0.01'"
                            :min="0"
                            :max="maxDiscountValue"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            :placeholder="productDiscountType === 'percentage' ? 'Porcentaje Descuento' : 'Monto Descuento'"
                        />
                    </div>
                    
                    <div v-if="selectedProductIndex !== null" class="p-4 bg-gray-50 rounded-lg space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Subtotal:</span>
                            <span class="font-semibold">
                                {{ formatCurrency((cartItems[selectedProductIndex]?.original_price || cartItems[selectedProductIndex]?.unit_price) * cartItems[selectedProductIndex]?.quantity) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Descuento:</span>
                            <span class="font-semibold">
                                {{ formatCurrency(
                                    productDiscountType === 'percentage' 
                                        ? ((cartItems[selectedProductIndex]?.original_price || cartItems[selectedProductIndex]?.unit_price) * cartItems[selectedProductIndex]?.quantity * productDiscountValue / 100)
                                        : (productDiscountValue * cartItems[selectedProductIndex]?.quantity)
                                ) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-base font-bold pt-2 border-t border-gray-300">
                            <span>Total:</span>
                            <span>
                                {{ formatCurrency(
                                    ((cartItems[selectedProductIndex]?.original_price || cartItems[selectedProductIndex]?.unit_price) * cartItems[selectedProductIndex]?.quantity) - 
                                    (productDiscountType === 'percentage' 
                                        ? ((cartItems[selectedProductIndex]?.original_price || cartItems[selectedProductIndex]?.unit_price) * cartItems[selectedProductIndex]?.quantity * productDiscountValue / 100)
                                        : (productDiscountValue * cartItems[selectedProductIndex]?.quantity))
                                ) }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <SecondaryButton @click="showProductDiscountModal = false">Cerrar</SecondaryButton>
                    <PrimaryButton @click="applyProductDiscount">Aplicar</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal de Gastos -->
        <ExpenseModal 
            :show="canRegisterExpenses && showExpenseModal"
            @close="showExpenseModal = false"
            @success="handleExpenseSuccess"
        />

        <!-- Modal de selección de variantes -->
        <VariantSelectorModal
            :show="showVariantSelectorModal"
            :product="selectedProductForVariant"
            @close="showVariantSelectorModal = false"
            @add-to-cart="handleVariantAddToCart"
        />

        <!-- Modal de catálogo de productos -->
        <Modal :show="showProductCatalogModal" @close="showProductCatalogModal = false" :max-width="'4xl'">
            <div class="flex max-h-[90vh] flex-col p-4 sm:p-6">
                <div class="mb-4 flex flex-shrink-0 items-start justify-between">
                    <div><p class="text-xs font-extrabold uppercase tracking-[0.18em] text-indigo-600">Catalogo</p><h2 class="mt-1 text-xl font-black text-slate-950">Agregar productos</h2><p class="mt-1 text-sm text-slate-500">Toca un producto para sumarlo a la venta.</p></div>
                    <button
                        @click="showProductCatalogModal = false"
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Buscador en el modal -->
                <div class="relative mb-4 flex-shrink-0">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar producto..."
                        class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-4 text-sm font-medium focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
                        @keyup.enter="searchProducts"
                        @input="searchProducts"
                    />
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                
                <!-- Categorías en el modal -->
                <div class="mb-4 flex flex-shrink-0 items-center gap-2 overflow-x-auto pb-2">
                    <button
                        @click="selectedCategory = null"
                        :class="[
                            'whitespace-nowrap rounded-full px-4 py-2 text-sm font-bold',
                            selectedCategory === null ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        TODOS
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        @click="selectedCategory = category.id"
                        :class="[
                            'whitespace-nowrap rounded-full px-4 py-2 text-sm font-bold',
                            selectedCategory === category.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        ]"
                    >
                        {{ category.name }}
                    </button>
                </div>
                
                <!-- Grid de productos en el modal -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="filteredProducts.length === 0" class="text-center py-12 text-gray-500">
                        <p>No se encontraron productos</p>
                    </div>
                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="group flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left transition hover:border-indigo-300 hover:shadow-lg"
                            @click="handleProductClick(product); showProductCatalogModal = false"
                        >
                            <div class="relative flex aspect-square items-center justify-center overflow-hidden bg-slate-100">
                                <img 
                                    v-if="product.main_image_url" 
                                    :src="product.main_image_url" 
                                    :alt="product.name"
                                    class="h-full w-full object-contain p-2 transition group-hover:scale-105"
                                />
                                <svg v-else class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="flex flex-1 flex-col p-3">
                                <div class="mb-1">
                                    <template v-if="hasActivePromotion(product)">
                                        <p class="text-xs line-through text-red-600">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).originalPrice) }}
                                        </p>
                                        <p class="text-sm font-bold text-green-600">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}
                                        </p>
                                    </template>
                                    <template v-else>
                                        <p class="text-sm font-black text-slate-950">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}
                                        </p>
                                    </template>
                                </div>
                                <h3 class="mt-1 line-clamp-2 text-xs font-bold leading-5 text-slate-700">{{ product.name }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal de descuento general de la factura -->
        <Modal :show="canOverridePrices && showGeneralDiscountModal" @close="showGeneralDiscountModal = false">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">Descuento general de la factura</h2>
                    <button @click="showGeneralDiscountModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                        <select 
                            v-model="discountType"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option value="percentage">Porcentaje</option>
                            <option value="amount">Monto</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Descuento {{ discountType === 'percentage' ? '%' : '' }}
                        </label>
                        <input
                            v-model.number="discount"
                            type="number"
                            :step="discountType === 'percentage' ? '0.01' : '0.01'"
                            :min="0"
                            :max="discountType === 'percentage' ? 100 : subtotal"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            :placeholder="discountType === 'percentage' ? 'Porcentaje Descuento' : 'Monto Descuento'"
                        />
                    </div>
                    
                    <div class="p-4 bg-gray-50 rounded-lg space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Subtotal:</span>
                            <span class="font-semibold">{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Descuento:</span>
                            <span class="font-semibold">{{ formatCurrency(discountAmount) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold pt-2 border-t border-gray-300">
                            <span>Total:</span>
                            <span>{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <SecondaryButton @click="showGeneralDiscountModal = false">Cerrar</SecondaryButton>
                    <PrimaryButton @click="applyGeneralDiscount">Aplicar</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal de alerta de stock -->
        <Modal :show="showStockAlertModal" @close="showStockAlertModal = false">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-rose-700">Stock insuficiente</h2>
                    <button @click="showStockAlertModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <div class="text-sm text-gray-700 whitespace-pre-line">{{ stockAlertMessage }}</div>
                </div>
                <div class="flex justify-end">
                    <PrimaryButton @click="showStockAlertModal = false">Entendido</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal de éxito de Gasto -->
        <AlertModal 
            :show="showSuccessExpenseModal"
            type="success"
            title="¡Gasto Guardado!"
            message="El gasto se ha registrado correctamente en el sistema."
            primary-text="Entendido"
            @close="showSuccessExpenseModal = false"
            @primary="showSuccessExpenseModal = false"
        />
        <AlertModal
            :show="posNotice.show"
            :type="posNotice.type"
            :title="posNotice.title"
            :message="posNotice.message"
            primary-text="Entendido"
            @close="posNotice.show = false"
            @primary="posNotice.show = false"
        />
    </AuthenticatedLayout>
</template>

<style>
/* Optimización para impresora térmica: forzar negro y quitar suavizado en PDF y pantalla */
#invoice-content-pos,
#invoice-content-pos * {
    color: black !important;
    -webkit-font-smoothing: none !important;
    -moz-osx-font-smoothing: auto !important;
    text-rendering: optimizeSpeed !important;
    font-family: Arial, Helvetica, sans-serif !important;
    font-weight: normal !important;
    letter-spacing: 0.5px !important;
}

@media print {
    /* Ocultar todo el contenido normal */
    body * {
        visibility: hidden;
    }

    /* Mostrar solo el contenido de la factura nueva */
    #invoice-content-pos, 
    #invoice-content-pos * {
        visibility: visible;
    }

    /* Posicionar la factura correctamente */
    #invoice-content-pos {
        position: absolute;
        left: 0;
        top: 0;
        width: 58mm !important;
        opacity: 1 !important;
        z-index: 9999;
        margin: 0;
        padding: 0;
        background: white !important;
    }
    
    /* Asegurar que no haya márgenes extraños en la página */
    @page {
        margin: 0;
        size: auto; 
    }
}
</style>
