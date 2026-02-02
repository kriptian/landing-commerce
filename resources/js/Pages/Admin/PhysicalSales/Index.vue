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
        alert('Error al generar el PDF');
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
            let cost = 0;
            // Intentar obtener cost de variante o producto
            // Nota: item.variant es relation, item.product es relation
            if (item.variant && item.variant.purchase_price > 0) {
                cost = parseFloat(item.variant.purchase_price);
            } else if (item.product && item.product.purchase_price > 0) {
                cost = parseFloat(item.product.purchase_price);
            }
            
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

const discountAmount = computed(() => {
    if (discountType.value === 'percentage') {
        return (subtotal.value * discount.value) / 100;
    }
    return discount.value;
});

// Costo de envío
const includeDeliveryCost = ref(false);
const deliveryCost = ref(0);

// Inicializar con valores de la tienda si existen, pero solo si no hay items en el carrito (o siempre, según preferencia)
// El usuario reportó que no tomaba el valor. Vamos a asegurar que si activa el check, tome el valor.
onMounted(() => {
    if (props.store?.delivery_cost_active) {
         // Opcional: activar por defecto
         // includeDeliveryCost.value = true; 
         // deliveryCost.value = parseFloat(props.store.delivery_cost);
    }
});

watch(includeDeliveryCost, (newValue) => {
    if (newValue && deliveryCost.value === 0 && props.store?.delivery_cost) {
        deliveryCost.value = parseFloat(props.store.delivery_cost);
    }
});

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
            alert('Producto no encontrado con ese código de barras');
            focusSearchInput();
        }
    } catch (error) {
        alert('Error al buscar producto');
        focusSearchInput();
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
            stockAlertMessage.value = `No hay suficiente stock para el producto "${item.product_name}".<br><br>Stock disponible: ${availableStock}<br>Cantidad solicitada: ${newQuantity}`;
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
            stockAlertMessage.value = `No hay suficiente stock para el producto "${item.product_name}".<br><br>Stock disponible: ${availableStock}<br>Cantidad solicitada: ${value}`;
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
                alert('No se pudo acceder a la cámara. Por favor, permite el acceso a la cámara en la configuración del navegador.');
                showBarcodeScanner.value = false;
            }
        }
    } catch (error) {
        alert('Error al inicializar el escáner. Por favor, intenta nuevamente.');
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
            alert('Error: No se pudo inicializar la conexión. Por favor, recarga la página.');
            return;
        }

        const response = await window.axios.post(route('admin.physical-sales.open-drawer'));
        
        if (response.data && response.data.success) {
            // Éxito silencioso - el cajón debería abrirse
        } else {
            alert('No se pudo abrir el cajón. Asegúrate de que la impresora esté conectada y configurada.');
        }
    } catch (error) {
        console.error('Error al intentar abrir el cajón:', error);
        alert('No se pudo abrir el cajón. Asegúrate de que la impresora esté conectada y configurada.');
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
    if (cartItems.value.length === 0) {
        alert('El carrito está vacío');
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
        stockAlertMessage.value = message.replace(/\n/g, '<br>');
        showStockAlertModal.value = true;
        return;
    }

        const saleData = {
        items: cartItems.value.map(item => ({
            product_id: item.product_id,
            variant_id: item.variant_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
            original_price: item.original_price,
            discount_percent: item.discount_percent,
        })),
        subtotal: subtotal.value,
        tax: 0,
        discount: discountAmount.value,
        total: total.value,
        delivery_cost: includeDeliveryCost.value ? (parseFloat(deliveryCost.value) || 0) : 0,
        payment_method: paymentMethod.value,
        notes: saleNotes.value,
    };

    try {
        // Usar axios que ya está configurado con CSRF
        if (!window.axios) {
            alert('Error: No se pudo inicializar la conexión. Por favor, recarga la página.');
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
            alert('Error: No se pudo obtener el token de seguridad. Por favor, recarga la página.');
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
                        const tokenResponse = await window.axios.get(route('dashboard'), {
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
            
            // Actualizar token CSRF después de una petición exitosa
            if (window.updateCsrfToken) {
                window.updateCsrfToken();
            }
            
            // Mostrar modal de éxito con opción de imprimir
            showInvoiceModal.value = true;
            
            // Recargar página para actualizar lista de ventas
            router.reload();
        } else {
            alert('Error: ' + (response.data?.message || 'No se pudo procesar la venta'));
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
        
        alert(errorMessage);
    }
};

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

// Lógica para redimensionar paneles (Split Pane)
const leftPanelWidth = ref(50); // Porcentaje inicial
const isResizing = ref(false);

const startResize = () => {
    isResizing.value = true;
    document.addEventListener('mousemove', handleResize);
    document.addEventListener('mouseup', stopResize);
    // Evitar selección de texto mientras se arrastra
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'col-resize';
};

const handleResize = (e) => {
    if (!isResizing.value) return;
    const containerWidth = window.innerWidth;
    // Calcular porcentaje basado en la posición del mouse
    const newLeftWidth = (e.clientX / containerWidth) * 100;
    // Limitar entre 20% y 80% para no ocultar ninguno de los dos lados
    if (newLeftWidth > 20 && newLeftWidth < 80) {
        leftPanelWidth.value = newLeftWidth;
    }
};

const stopResize = () => {
    isResizing.value = false;
    document.removeEventListener('mousemove', handleResize);
    document.removeEventListener('mouseup', stopResize);
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
};
</script>

<template>
    <Head title="Ventas Físicas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 v-if="activeTab !== 'sales'" class="font-semibold text-xl text-gray-800 leading-tight">Ventas Físicas</h2>
        </template>

        <!-- Vista de Ventas (POS) -->
        <!-- Vista Desktop -->
        <div v-if="activeTab === 'sales'" class="hidden lg:flex fixed inset-0 bg-white flex-row" style="margin-top: 0; z-index: 10;">
            <!-- Panel Izquierdo: Productos -->
            <div :style="{ width: leftPanelWidth + '%' }" class="h-full flex flex-col border-r border-gray-300">
                <!-- Header con búsqueda -->
                <div class="bg-blue-50 border-b border-blue-100 px-4 py-3 flex-shrink-0">
                    <div class="flex items-center gap-3 mb-3">
                        <button 
                            @click="handleExit"
                            class="px-3 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 text-sm font-medium flex items-center gap-2 border border-gray-200"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Salir
                        </button>
                        
                        <!-- Botón Nuevo Gasto -->
                        <button 
                            @click="showExpenseModal = true"
                            class="px-3 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 text-sm font-medium flex items-center gap-2 border border-red-200"
                            title="Registrar Gasto / Salida"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Gasto
                        </button>

                        <div class="flex-1 relative">
                            <input
                                ref="searchInput"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar producto..."
                                class="w-full pl-10 pr-12 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white"
                                @keyup.enter="searchProducts"
                            />
                            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <button
                                @click="initBarcodeScanner"
                                class="absolute right-2 top-1.5 p-1 text-blue-600 hover:text-blue-700"
                                title="Escanear código de barras"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2.01M8 8h.01M12 8h.01M16 8h.01M20 8h.01M5 12h2.01M8 12h.01M12 12h2.01M16 12h.01M20 12h.01M5 16h2.01M8 16h.01M12 16h.01M16 16h.01M20 16h.01"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- Categorías -->
                    <div class="flex items-center gap-2 overflow-x-auto">
                        <button
                            @click="selectedCategory = null; searchQuery = ''"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap border',
                                selectedCategory === null ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            TODOS
                        </button>
                        <button
                            v-for="category in categories"
                            :key="category.id"
                            @click="selectedCategory = category.id; searchQuery = ''"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap border',
                                selectedCategory === category.id ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            {{ category.name }}
                        </button>
                    </div>
                </div>
                
                <!-- Grid de productos -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="filteredProducts.length === 0" class="text-center py-12 text-gray-500">
                        <p>No se encontraron productos</p>
                    </div>
                    <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md cursor-pointer flex flex-col"
                            @click="handleProductClick(product)"
                        >
                            <div class="aspect-square bg-gray-100 flex items-center justify-center" style="min-height: 120px;">
                                <img 
                                    v-if="product.main_image_url" 
                                    :src="product.main_image_url" 
                                    :alt="product.name"
                                    class="w-full h-full object-cover"
                                />
                                <svg v-else class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="p-2 flex-1 flex flex-col">
                                <div class="mb-1">
                                    <template v-if="product.promo_active && product.promo_discount_percent > 0">
                                        <p class="text-xs line-through text-red-600">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).originalPrice) }}
                                        </p>
                                        <p class="text-sm font-bold text-green-600">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}
                                        </p>
                                    </template>
                                    <template v-else>
                                        <p class="text-sm font-bold text-gray-900">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}
                                        </p>
                                    </template>
                                </div>
                                <h3 class="font-medium text-xs text-gray-900 line-clamp-2" :title="product.name">{{ product.name }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resizer (Separador deslizable) -->
            <div 
                class="w-1 hover:w-2 bg-gray-200 hover:bg-blue-400 cursor-col-resize flex items-center justify-center transition-all z-20 group" 
                @mousedown="startResize"
                title="Arrastra para ajustar el tamaño"
            >
                <div class="h-8 w-1 bg-gray-400 rounded-full group-hover:bg-white"></div>
            </div>

            <!-- Panel Derecho: Facturación -->
            <div class="flex-1 h-full flex flex-col bg-white overflow-hidden" style="min-width: 300px;">
                <!-- Header -->
                <div class="bg-blue-50 border-b border-blue-100 px-4 py-3 flex-shrink-0">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                        </svg>
                        <span class="text-blue-700 font-semibold">{{ page.props.auth?.user?.name || 'Usuario' }}</span>
                        <span class="text-blue-400">|</span>
                        <img 
                            v-if="store && store.logo_url" 
                            :src="store.logo_url" 
                            :alt="`Logo de ${store.name}`" 
                            class="h-6 w-6 rounded-full object-cover"
                        >
                        <span v-if="store" class="text-blue-700 font-medium text-sm">{{ store.name }}</span>
                    </div>
                </div>
                
                <!-- Lista de productos facturados -->
                <div class="flex-1 overflow-y-auto px-3 py-2">
                    <div v-if="cartItems.length === 0" class="text-center py-12 text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                        </svg>
                        <p>El carrito está vacío</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700">Producto</th>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-700 w-16">Stock</th>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-700 w-20">Cant</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-700 w-40">Valor</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-700 w-32">Desc</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-gray-700 w-36">SubTotal</th>
                                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-700 w-16"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="(item, index) in cartItems" :key="index" :class="['hover:bg-gray-50', getStockStatusClass(item)]">
                                    <td class="px-3 py-3 text-left align-middle" :title="item.product_name">
                                        <div class="min-w-0" style="max-width: 140px;">
                                            <div class="font-medium text-gray-900">
                                                {{ item.product_name }}
                                                <span v-if="item.variant_options" class="text-xs text-gray-500 ml-1">
                                                    ({{ Object.values(item.variant_options).join(' / ') }})
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center align-middle w-16" :title="`Stock disponible: ${getAvailableStock(item)}`">
                                        <span :class="getStockTextClass(item)">
                                            {{ getAvailableStock(item) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-center align-middle w-20" :title="`Cantidad: ${item.quantity}`">
                                        <input
                                            type="number"
                                            :value="item.quantity"
                                            @change="updateQuantityInput(index, $event)"
                                            class="w-full text-center border border-gray-300 rounded text-sm py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            min="1"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-right align-middle w-40" :title="`Precio unitario: ${formatCurrency(item.unit_price)}`">
                                        <input
                                            type="text"
                                            :value="formatNumberForInput(item.original_price || item.unit_price)"
                                            @input="handlePriceInput(index, $event)"
                                            class="w-full text-right border border-gray-300 rounded text-sm py-2 px-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="0"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-right align-middle w-32" :title="item.discount_type === 'amount' ? `Descuento: $${item.discount_value}` : `Descuento: ${item.discount_percent}%`">
                                        <!-- Si es monto fijo, mostrar button con valor -->
                                        <button 
                                            v-if="item.discount_type === 'amount'"
                                            @click="openProductDiscountModal(index)"
                                            class="w-full text-right border border-blue-200 bg-blue-50 text-blue-700 font-semibold rounded text-sm py-2 px-2 hover:bg-blue-100 transition-colors"
                                        >
                                            $ {{ formatNumberForInput(item.discount_value) }}
                                        </button>
                                        
                                        <!-- Si es porcentaje, mostrar input original con símbolo % -->
                                        <div v-else class="relative">
                                            <input
                                                type="number"
                                                :value="item.discount_percent"
                                                @change="updateDiscountFromInput(index, $event)"
                                                @click.self="openProductDiscountModal(index)" 
                                                class="w-full text-right border border-gray-300 rounded text-sm py-2 pl-2 pr-6 cursor-pointer focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                            />
                                            <span class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 text-sm font-medium pointer-events-none">%</span>
                                        </div>
                                        <!-- Nota: quitamos @click del input para evitar abrir modal indeseado al editar, pero se puede añadir un icono o doble click -->
                                    </td>
                                    <td class="px-3 py-3 text-right align-middle w-36" :title="`Subtotal: ${formatCurrency(item.quantity * item.unit_price)}`">
                                        <span class="font-semibold text-gray-900 text-sm">
                                            {{ formatCurrency(item.quantity * item.unit_price) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-center align-middle w-16">
                                        <button
                                            @click="removeFromCart(index)"
                                            class="flex items-center justify-center w-8 h-8 bg-red-500 text-white rounded hover:bg-red-600 transition-colors mx-auto"
                                            title="Eliminar producto"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Resumen de pago -->
                <div v-if="cartItems.length > 0" class="border-t border-gray-200 px-4 py-3 bg-gray-50 space-y-3 flex-shrink-0">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">Total Bruto:</span>
                        <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm items-center">
                        <span class="text-gray-700">Descuento:</span>
                        <span 
                            @click="openGeneralDiscountModal"
                            class="font-medium cursor-pointer hover:text-blue-600 flex items-center gap-1"
                        >
                            {{ discount > 0 ? '-' + formatCurrency(discountAmount) : formatCurrency(0) }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </span>
                    </div>
                    
                    <!-- Costo de envío -->
                    <div class="flex justify-between text-sm items-center">
                        <label class="flex items-center text-gray-700 gap-2 cursor-pointer">
                            <input 
                                type="checkbox" 
                                v-model="includeDeliveryCost"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4"
                            >
                            <span>Incluir Envío:</span>
                        </label>
                        <div v-if="includeDeliveryCost" class="relative w-32">
                            <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                            <input 
                                type="number" 
                                v-model="deliveryCost" 
                                class="w-full text-right border border-gray-300 rounded text-sm py-1 pl-6 pr-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0"
                                min="0"
                            >
                        </div>
                        <span v-else class="text-gray-500 text-xs italic">No aplica</span>
                    </div>

                    <div class="pt-2 border-t border-gray-300">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-base font-bold text-gray-900">Total Compra:</span>
                            <span class="text-base font-bold text-green-600">{{ formatCurrency(total) }}</span>
                        </div>
                        <div class="flex gap-3">
                            <button
                                @click="showPaymentModal = true"
                                class="flex-1 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-base"
                            >
                                Procesar Venta
                            </button>
                            <button
                                @click="cancelSale"
                                class="flex-1 py-3 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 font-semibold text-base border border-red-300"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Móvil (POS) -->
        <div v-if="activeTab === 'sales'" class="lg:hidden fixed inset-0 bg-white flex flex-col" style="margin-top: 0; z-index: 10;">
            <!-- Header móvil -->
            <div class="bg-blue-500 px-4 py-3 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                        </svg>
                        <span class="text-white font-semibold">{{ page.props.auth?.user?.name || 'Usuario' }}</span>
                        <span class="text-white">|</span>
                        <img 
                            v-if="store && store.logo_url" 
                            :src="store.logo_url" 
                            :alt="`Logo de ${store.name}`" 
                            class="h-5 w-5 rounded-full object-cover"
                        >
                        <span v-if="store" class="text-white font-medium text-sm">{{ store.name }}</span>
                    </div>
                    <button 
                        @click="handleExit"
                        class="px-3 py-1.5 bg-teal-500 text-white rounded text-sm font-medium"
                    >
                        « Regresar
                    </button>
                </div>
            </div>

            <!-- Barra de búsqueda móvil -->
            <div class="bg-gray-100 px-4 py-3 flex-shrink-0 border-b border-gray-200">
                <div class="flex items-center gap-2">
                    <!-- Botón catálogo -->
                    <button
                        @click="showProductCatalogModal = true"
                        class="px-3 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center justify-center flex-shrink-0"
                        title="Ver catálogo de productos"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </button>

                    <!-- Botón Gasto Móvil -->
                    <button 
                        @click="showExpenseModal = true"
                        class="px-3 py-2.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 flex items-center justify-center flex-shrink-0 border border-red-200"
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
                            class="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white"
                            @keyup.enter="searchProducts"
                            @input="searchProducts"
                        />
                        <svg class="absolute left-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <button
                            @click="initBarcodeScanner"
                            class="absolute right-2 top-2 p-1.5 text-blue-600 hover:text-blue-700"
                            title="Escanear código de barras"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2.01M8 8h.01M12 8h.01M16 8h.01M20 8h.01M5 12h2.01M8 12h.01M12 12h2.01M16 12h.01M20 12h.01M5 16h2.01M8 16h.01M12 16h.01M16 16h.01M20 16h.01"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Resultados de búsqueda -->
                <div v-if="searchResults.length > 0 && searchQuery.trim()" class="mt-3 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                    <div
                        v-for="product in searchResults"
                        :key="product.id"
                        @click="handleProductClick(product); searchQuery = ''; searchResults = []"
                        class="px-4 py-3 border-b border-gray-100 hover:bg-blue-50 cursor-pointer flex items-center gap-3"
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
                                <template v-if="product.promo_active && product.promo_discount_percent > 0">
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
            <div class="bg-gray-200 px-4 py-2 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-400 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-gray-700 font-semibold text-sm">INFORMACION DE LA VENTA</h3>
                </div>
            </div>

            <!-- Lista de productos facturados móvil -->
            <div class="flex-1 overflow-y-auto bg-white">
                <div v-if="cartItems.length === 0" class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/>
                    </svg>
                    <p>No hay productos en el carrito</p>
                    <p class="text-xs text-gray-400 mt-2">Busca o escanea un producto para comenzar</p>
                </div>
                
                <div v-else class="divide-y divide-gray-200">
                    <div
                        v-for="(item, index) in cartItems"
                        :key="index"
                        class="px-4 py-3 bg-white"
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
                                            class="w-8 h-8 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 font-medium text-sm"
                                        >
                                            -
                                        </button>
                                        <span class="w-10 h-8 flex items-center justify-center bg-blue-600 text-white rounded-full font-semibold text-sm">
                                            {{ item.quantity }}
                                        </span>
                                        <button
                                            @click="updateQuantity(index, 1)"
                                            class="w-8 h-8 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 font-medium text-sm"
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
                                    @click="openProductDiscountModal(index)"
                                    class="w-8 h-8 flex items-center justify-center bg-gray-200 text-gray-600 rounded hover:bg-gray-300"
                                    title="Aplicar descuento"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                                
                                <!-- Botón eliminar -->
                                <button
                                    @click="removeFromCart(index)"
                                    class="w-8 h-8 flex items-center justify-center bg-red-500 text-white rounded hover:bg-red-600"
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
            <div v-if="cartItems.length > 0" class="border-t border-gray-300 bg-white px-4 py-4 flex-shrink-0">
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-medium text-green-600">{{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">Descuento:</span>
                        <span 
                            @click="openGeneralDiscountModal"
                            class="font-medium text-green-600 cursor-pointer hover:text-blue-600 flex items-center gap-1"
                        >
                            {{ discount > 0 ? '-' + formatCurrency(discountAmount) : formatCurrency(0) }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </span>
                    </div>

                    <!-- Costo de envío Móvil -->
                    <div class="flex justify-between text-sm items-center">
                        <label class="flex items-center text-gray-700 gap-2 cursor-pointer">
                            <input 
                                type="checkbox" 
                                v-model="includeDeliveryCost"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4"
                            >
                            <span>Incluir Envío:</span>
                        </label>
                        <div v-if="includeDeliveryCost" class="relative w-32">
                            <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                            <input 
                                type="number" 
                                v-model="deliveryCost" 
                                class="w-full text-right border border-gray-300 rounded text-sm py-1 pl-6 pr-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0"
                                min="0"
                            >
                        </div>
                        <span v-else class="text-gray-500 text-xs italic">No aplica</span>
                    </div>

                    <div class="flex justify-between items-center pt-2 border-t border-gray-300">
                        <span class="text-base font-bold text-gray-900">Total Compra:</span>
                        <span class="text-base font-bold text-green-600">{{ formatCurrency(total) }}</span>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button
                        @click="showPaymentModal = true"
                        class="flex-1 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-base transition-colors"
                    >
                        PROCESAR VENTA
                    </button>
                    <button
                        @click="cartItems = []; discount = 0; discountType = 'amount'"
                        class="flex-1 py-3 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 font-semibold text-base transition-colors border border-red-300"
                    >
                        CANCELAR
                    </button>
                </div>
            </div>
        </div>

        <!-- Modales -->
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

                <!-- New: Cash Payment Fields -->
                <div v-if="paymentMethod === 'efectivo'" class="mb-4 p-4 bg-green-50 rounded-lg border border-green-100">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dinero Recibido</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input 
                            :value="formatNumberForInput(amountTendered)"
                            @input="handleAmountTenderedInput"
                            type="text" 
                            inputmode="numeric"
                            class="w-full pl-7 rounded-md border-gray-300 focus:ring-green-500 focus:border-green-500 font-bold text-lg"
                            placeholder="0"
                        >
                    </div>
                    
                    <div v-if="amountTendered" class="mt-3 flex justify-between items-center text-lg">
                        <span class="font-medium text-gray-700">Cambio a devolver:</span>
                        <span class="font-bold text-green-700">{{ formatCurrency(changeAmount) }}</span>
                    </div>
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
                        <span>{{ formatCurrency(subtotal) }}</span>
                    </div>
                    
                    <!-- Descuento manual si existe -->
                    <div v-if="discountAmount > 0" class="flex justify-between mb-2 text-red-600">
                        <span>Descuento ({{ discountType === 'percentage' ? discount + '%' : 'Monto' }}):</span>
                        <span>-{{ formatCurrency(discountAmount) }}</span>
                    </div>

                    <div v-if="includeDeliveryCost && parseFloat(deliveryCost) > 0" class="flex justify-between mb-2 text-gray-600">
                        <span>Costo de Envío:</span>
                        <span>{{ formatCurrency(deliveryCost) }}</span>
                    </div>
                    
                    <div class="flex justify-between font-bold text-lg pt-2 border-t">
                        <span>Total:</span>
                        <span>{{ formatCurrency(total) }}</span>
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

                <div class="flex gap-2 sm:gap-3 justify-center sm:justify-end">
                    <SecondaryButton @click="showInvoiceModal = false" class="!px-3 sm:!px-4">
                        <span class="sm:hidden">✕</span>
                        <span class="hidden sm:inline">Cerrar</span>
                    </SecondaryButton>
                    
                    <button 
                         v-if="lastCreatedSale"
                         @click="downloadInvoicePDF"
                         class="inline-flex items-center px-3 sm:px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                         :disabled="isGeneratingPDF"
                         title="Descargar PDF"
                    >
                        <span v-if="isGeneratingPDF" class="animate-pulse">⏳</span>
                        <span v-else>⬇️ <span class="hidden sm:inline ml-1">Descargar PDF</span></span>
                    </button>

                    <button 
                         v-if="lastCreatedSale"
                         @click="shareInvoicePDF"
                         class="inline-flex items-center px-3 sm:px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150"
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
                <div id="invoice-content-pos" class="fixed top-0 left-0 w-[80mm] bg-white z-[-100] opacity-0 pointer-events-none">
                    <div class="bg-white text-black p-2 font-mono text-[11px] leading-tight" style="width: 80mm; margin: 0 auto; font-family: 'Courier New', Courier, monospace;">
                        <!-- Header -->
                        <div class="text-center mb-4">
                             <img 
                                v-if="store?.logo_url" 
                                :src="store.logo_url" 
                                alt="Logo" 
                                class="h-12 w-auto object-contain mx-auto mb-2 grayscale"
                                crossorigin="anonymous"
                            />
                            <h2 class="font-bold text-base uppercase mb-1">{{ store?.name }}</h2>
                            <div class="text-[10px] space-y-0.5" style="font-size: 10px;">
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

                        <div class="border-b-2 border-dashed border-black my-2"></div>

                        <!-- Info Grid -->
                        <div class="mb-3 text-[10px] grid grid-cols-2 gap-x-2 gap-y-1">
                            <div class="col-span-2 text-center mb-1">
                                <p class="text-sm font-bold">Venta #{{ lastCreatedSale?.sale_number }}</p>
                                <p class="text-[9px] text-gray-500">{{ lastCreatedSale ? formatDate(lastCreatedSale.created_at) : '' }}</p>
                            </div>
                            
                            <div>
                                <span class="font-bold block text-gray-600">Vendedor:</span>
                                <span>{{ lastCreatedSale?.user?.name || $page.props.auth.user.name }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold block text-gray-600">Método de Pago:</span>
                                <span class="capitalize">{{ lastCreatedSale?.payment_method }}</span>
                            </div>

                            <div v-if="lastCreatedSale?.customer_name" class="col-span-2 mt-1 border-t border-dotted border-gray-300 pt-1">
                                <p><span class="font-bold text-gray-600">Cliente:</span> {{ lastCreatedSale.customer_name }}</p>
                                <p v-if="lastCreatedSale.customer_nit"><span class="font-bold text-gray-600">NIT/CC:</span> {{ lastCreatedSale.customer_nit }}</p>
                            </div>
                        </div>

                        <div class="border-b border-black my-2"></div>

                        <!-- Items -->
                        <div class="mb-4">
                             <!-- Simplified Header -->
                             <div class="flex justify-between text-[9px] font-bold mb-2 uppercase text-gray-800">
                                <span>Descripción</span>
                                <span>Total</span>
                            </div>

                            <div v-for="item in lastCreatedSale?.items" :key="item.id" class="mb-3 border-b border-gray-200 last:border-0 pb-2">
                                <!-- Top Row: Product Name -->
                                <div class="font-bold text-[11px] leading-tight mb-0.5">
                                    {{ item.product_name }}
                                </div>
                                
                                <!-- Variant Info -->
                                <div v-if="item.variant_options" class="text-[9px] text-gray-500 italic mb-1">
                                    {{ Object.values(item.variant_options).join(' / ') }}
                                </div>

                                <!-- Price / Calculation Row -->
                                <div class="flex justify-between items-start text-[10px] mt-1">
                                     <!-- Left Col: Quantity x Price -->
                                    <div class="flex flex-col">
                                        <!-- Standard calculation line -->
                                        <span class="text-gray-800">{{ item.quantity }} x {{ formatCurrency(item.unit_price) }}</span>
                                        
                                        <!-- Extended Discount Info -->
                                        <div v-if="item.discount_percent > 0 || (item.original_price && item.original_price > item.unit_price)" 
                                             class="flex flex-col mt-0.5"
                                        >
                                            <!-- Original Price (Strikethrough) -->
                                            <span style="text-decoration: line-through; color: #9ca3af;" class="text-[9px]">
                                                Precio habitual: {{ formatCurrency(item.original_price || (item.unit_price * 100 / (100 - item.discount_percent))) }}
                                            </span>
                                            
                                            <!-- Discount Tag -->
                                            <span class="text-[9px] font-bold text-gray-800">
                                                Desc: {{ item.discount_percent || Math.round((1 - item.unit_price/item.original_price)*100) }}%
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Col: Line Total -->
                                    <div class="font-bold text-[11px] mt-0.5">
                                        {{ formatCurrency(item.subtotal) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-black my-2 dashed"></div>

                        <!-- Totals -->
                        <div class="text-right text-[11px] space-y-1">
                             <div v-if="lastCreatedSale?.discount > 0" class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(lastCreatedSale.subtotal) }}</span>
                            </div>
                             <div v-if="lastCreatedSale?.discount > 0" class="flex justify-between text-gray-600">
                                <span>Descuento</span>
                                <span>-{{ formatCurrency(lastCreatedSale.discount) }}</span>
                            </div>
                             <div v-if="parseFloat(lastCreatedSale?.delivery_cost) > 0" class="flex justify-between text-gray-600">
                                <span>Costo de envío</span>
                                <span>{{ formatCurrency(lastCreatedSale.delivery_cost) }}</span>
                            </div>
                             <div class="flex justify-between text-base font-black pt-1">
                                <span>TOTAL</span>
                                <span>{{ formatCurrency(lastCreatedSale?.total) }}</span>
                            </div>
                             <!-- Cash/Change Display -->
                             <div class="flex justify-between text-[10px] mt-1 text-green-700 font-bold" v-if="amountTendered > 0 && lastCreatedSale">
                                <span>Efectivo:</span>
                                <span>{{ formatCurrency(amountTendered) }}</span>
                            </div>
                             <div class="flex justify-between text-[10px] text-green-700 font-bold" v-if="amountTendered > lastCreatedSale?.total">
                                <span>Cambio:</span>
                                <span>{{ formatCurrency(amountTendered - lastCreatedSale.total) }}</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-center mt-6 text-[10px] space-y-1 mb-4">
                            <p class="font-medium">¡Gracias por su compra!</p>
                             <div v-if="lastCreatedSale?.notes" class="mt-2 pt-2 border-t border-dotted border-gray-300 text-left">
                                <p class="font-bold text-[9px] text-gray-500">Notas:</p>
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
                <h2 class="text-lg font-semibold mb-4">Escanear Código de Barras</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Apunta la cámara hacia el código de barras. El producto se agregará automáticamente al carrito.
                </p>
                <div id="barcode-scanner-sales" class="w-full rounded-lg overflow-hidden" style="min-height: 300px;"></div>
                <SecondaryButton @click="closeBarcodeScanner" class="mt-4">Cerrar</SecondaryButton>
            </div>
        </Modal>

        <!-- Modal de descuento de producto -->
        <Modal :show="showProductDiscountModal" @close="showProductDiscountModal = false">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">
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
            :show="showExpenseModal" 
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
            <div class="p-6 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between mb-4 flex-shrink-0">
                    <h2 class="text-lg font-semibold">Catálogo de Productos</h2>
                    <button
                        @click="showProductCatalogModal = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Buscador en el modal -->
                <div class="mb-4 relative flex-shrink-0">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar producto..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        @keyup.enter="searchProducts"
                        @input="searchProducts"
                    />
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                
                <!-- Categorías en el modal -->
                <div class="mb-4 flex items-center gap-2 overflow-x-auto pb-2 flex-shrink-0">
                    <button
                        @click="selectedCategory = null"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap border',
                            selectedCategory === null ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                        ]"
                    >
                        TODOS
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        @click="selectedCategory = category.id"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap border',
                            selectedCategory === category.id ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
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
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md cursor-pointer flex flex-col"
                            @click="handleProductClick(product); showProductCatalogModal = false"
                        >
                            <div class="aspect-square bg-gray-100 flex items-center justify-center" style="min-height: 120px;">
                                <img 
                                    v-if="product.main_image_url" 
                                    :src="product.main_image_url" 
                                    :alt="product.name"
                                    class="w-full h-full object-cover"
                                />
                                <svg v-else class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="p-2 flex-1 flex flex-col">
                                <div class="mb-1">
                                    <template v-if="product.promo_active && product.promo_discount_percent > 0">
                                        <p class="text-xs line-through text-red-600">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).originalPrice) }}
                                        </p>
                                        <p class="text-sm font-bold text-green-600">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}
                                        </p>
                                    </template>
                                    <template v-else>
                                        <p class="text-sm font-bold text-gray-900">
                                            {{ formatCurrency(calculatePriceWithDiscount(product).finalPrice) }}
                                        </p>
                                    </template>
                                </div>
                                <h3 class="font-medium text-xs text-gray-900 line-clamp-2">{{ product.name }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal de descuento general de la factura -->
        <Modal :show="showGeneralDiscountModal" @close="showGeneralDiscountModal = false">
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
                    <h2 class="text-lg font-semibold text-red-600">⚠️ Stock Insuficiente</h2>
                    <button @click="showStockAlertModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <div class="text-sm text-gray-700 whitespace-pre-line" v-html="stockAlertMessage"></div>
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
    </AuthenticatedLayout>
</template>

