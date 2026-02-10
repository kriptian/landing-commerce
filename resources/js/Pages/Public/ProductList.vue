<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed, h, onMounted, onBeforeUnmount } from 'vue';
import PopupModal from '@/Components/Public/PopupModal.vue';
import RegisterModal from '@/Components/Public/RegisterModal.vue';
import LoginModal from '@/Components/Public/LoginModal.vue';

// Window width para responsive
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const updateWindowWidth = () => {
    if (typeof window !== 'undefined') {
        windowWidth.value = window.innerWidth;
    }
};
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Pagination from '@/Components/Pagination.vue';
import CookieConsent from '@/Components/CookieConsent.vue';

// Navegación por niveles: cache de hijos y pila de navegación para categorías principales
const childrenCache = ref(new Map()); // parentId -> items
const cacheUpdateKey = ref(0); // Para forzar reactividad cuando cambia el cache
const menuStack = ref([]); // [{ id, name }]
const isLevelLoading = ref(false);
const currentParentId = computed(() => menuStack.value.length ? menuStack.value[menuStack.value.length - 1].id : null);
const currentTitle = computed(() => menuStack.value.length ? menuStack.value[menuStack.value.length - 1].name : 'Categorías');
const currentItems = computed(() => {
  if (!currentParentId.value) return props.categories;
  return childrenCache.value.get(currentParentId.value) || [];
});
// Nivel actual del menú para el efecto de deslizamiento (0 = raíz, 1+ = niveles anidados)
const currentMenuLevel = computed(() => menuStack.value.length);
// Total de niveles (raíz + niveles navegados)
const totalMenuLevels = computed(() => menuStack.value.length + 1);
// Estilo para el ancho de cada slide
const slideWidth = computed(() => `${100 / totalMenuLevels.value}%`);
// Porcentaje de desplazamiento del contenedor de slides
const slideTransform = computed(() => {
    if (totalMenuLevels.value === 0) return '0%';
    return `${(-currentMenuLevel.value * 100) / totalMenuLevels.value}%`;
});

// Función helper para obtener hijos de forma segura (para uso en computed o métodos)
const getLevelChildren = (categoryId) => {
    if (!childrenCache.value || typeof childrenCache.value.get !== 'function') {
        return [];
    }
    return childrenCache.value.get(categoryId) || [];
};

// Computed para obtener los hijos de cada nivel (para reactividad)
const levelChildrenData = computed(() => {
    // Incluir cacheUpdateKey para forzar reactividad
    cacheUpdateKey.value; // Dependencia reactiva
    return menuStack.value.map(level => {
        const children = getLevelChildren(level.id);
        const isLoading = isLevelLoading.value && menuStack.value[menuStack.value.length - 1]?.id === level.id;
        return {
            levelId: level.id,
            levelName: level.name,
            children: children,
            isLoading: isLoading
        };
    });
});

const openNode = async (cat) => {
  if (!cat.has_children_with_products) return;
  menuStack.value.push({ id: cat.id, name: cat.name });
  if (!childrenCache.value || typeof childrenCache.value.has !== 'function') {
    // Si el cache no está inicializado, inicializarlo
    if (!childrenCache.value) {
      childrenCache.value = new Map();
    }
  }
  if (!childrenCache.value.has(cat.id)) {
    isLevelLoading.value = true;
    try {
    const res = await fetch(route('catalog.categories.children', { store: props.store.slug, category: cat.id }));
    const json = await res.json();
      const children = Array.isArray(json.data) ? json.data : [];
      childrenCache.value.set(cat.id, children);
      cacheUpdateKey.value++; // Forzar reactividad
      // Forzar actualización del DOM
      await nextTick();
    } catch (error) {
      childrenCache.value.set(cat.id, []);
      cacheUpdateKey.value++; // Forzar reactividad incluso en caso de error
    } finally {
    isLevelLoading.value = false;
    }
  } else {
    // Si ya están en cache, igual forzar reactividad para asegurar que se muestren
    cacheUpdateKey.value++;
  }
};
const goBack = () => { if (menuStack.value.length) menuStack.value.pop(); };

// Sistema de acordeón solo para subcategorías (nivel 2+)
const loadingCategories = ref(new Set()); // IDs de categorías cargando

// Función para cargar hijos de una subcategoría (para acordeón)
const loadSubChildren = async (categoryId) => {
    if (!childrenCache.value || typeof childrenCache.value.has !== 'function') {
        return [];
    }
    if (childrenCache.value.has(categoryId) || loadingCategories.value.has(categoryId)) {
        return childrenCache.value.get(categoryId) || [];
    }
    
    loadingCategories.value.add(categoryId);
    try {
        const res = await fetch(route('catalog.categories.children', { store: props.store.slug, category: categoryId }));
        const json = await res.json();
        const children = Array.isArray(json.data) ? json.data : [];
        childrenCache.value.set(categoryId, children);
        cacheUpdateKey.value++; // Forzar reactividad
        return children;
    } catch (e) {
        childrenCache.value.set(categoryId, []);
        cacheUpdateKey.value++; // Forzar reactividad incluso en caso de error
        return [];
    } finally {
        loadingCategories.value.delete(categoryId);
    }
};

const props = defineProps({
    products: Object,
    store: Object,
    categories: Array,
    filters: Object,
    hasProductsWithPromo: Boolean, // Información global sobre si hay productos con promoción en toda la tienda
    maxProductPromoPercent: Number, // Porcentaje máximo de promoción de todos los productos con promoción
});

const search = ref(props.filters.search);
const sort = ref(props.filters.sort || 'latest');

// --- LÓGICA PARA LA BÚSQUEDA ANIMADA ---
const isSearchActive = ref(false);
const isClosingSearch = ref(false); // Bandera para evitar que blur reabra la búsqueda
const drawerOpen = ref(false);
const showNotifications = ref(false);
const showSortMenu = ref(false); // Estado para mostrar/ocultar el menú de ordenamiento
const expanded = ref({});
const showRegisterModal = ref(false);
const showLoginModal = ref(false);

// Funciones para notificaciones
const formatNotificationDate = (date) => {
    const now = new Date();
    const notificationDate = new Date(date);
    const diffInSeconds = Math.floor((now - notificationDate) / 1000);
    
    if (diffInSeconds < 60) return 'Hace unos segundos';
    if (diffInSeconds < 3600) return `Hace ${Math.floor(diffInSeconds / 60)} minutos`;
    if (diffInSeconds < 86400) return `Hace ${Math.floor(diffInSeconds / 3600)} horas`;
    if (diffInSeconds < 604800) return `Hace ${Math.floor(diffInSeconds / 86400)} días`;
    
    return notificationDate.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const handleNotificationClick = (notification) => {
    showNotifications.value = false;
    
    // Marcar como leída si no está leída
    if (!notification.is_read) {
        // Usar fetch directamente ya que el backend devuelve JSON
        fetch(route('customer.notifications.mark-read', { 
            store: props.store.slug, 
            notificationId: notification.id 
        }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
        })
        .then(response => response.json())
        .then(data => {
            // Actualizar la notificación localmente
            notification.is_read = true;
            
            // Recargar los datos del customer para actualizar el contador
            router.reload({ 
                only: ['customer'],
                preserveState: false,
            });
        })
        .catch(error => {
            // Intentar recargar de todas formas
            router.reload({ 
                only: ['customer'],
                preserveState: false,
            });
        });
        
        // Si tiene order_id, navegar después de marcar como leída
        if (notification.order_id) {
            setTimeout(() => {
                router.visit(route('customer.orders', { store: props.store.slug }));
            }, 300);
        }
    } else {
        // Si ya está leída, solo navegar
        if (notification.order_id) {
            router.visit(route('customer.orders', { store: props.store.slug }));
        }
    }
};


// Modo navegación: aplicamos filtro inmediato sin checkboxes/botones
const selected = ref(new Set());
// Dropdown para menú completo - rastrea qué categoría tiene su dropdown abierto
const openDropdownCategory = ref(null);
const dropdownExpanded = ref({}); // Para acordeón dentro del dropdown
const toggleNode = async (cat) => {
  if (!cat.has_children_with_products) return;
  
  const isExpanding = !expanded.value[cat.id];
  expanded.value[cat.id] = isExpanding;
  
  // Si estamos expandiendo y no tenemos los hijos, cargarlos
  if (isExpanding && !childrenCache.value.has(cat.id)) {
    await loadSubChildren(cat.id);
  }
};
const applyImmediate = (categoryId) => {
  router.get(route('catalogo.index', { store: props.store.slug }), { 
    category: categoryId, 
    search: search.value || undefined,
    sort: sort.value !== 'latest' ? sort.value : undefined
  }, { preserveState: true, replace: true, preserveScroll: true });
  drawerOpen.value = false;
};
// Función para manejar hover/click en categorías del menú completo - abre dropdown
const handleCategoryHover = async (cat) => {
  if (!cat.has_children_with_products) return;
  openDropdownCategory.value = cat.id;
  // Cargar subcategorías si no están en cache
  if (!childrenCache.value.has(cat.id)) {
    await loadSubChildren(cat.id);
  }
};

const handleCategoryLeave = (event) => {
  // Solo cerrar si no estamos moviendo el mouse hacia el dropdown
  // Pequeño delay para permitir movimiento al dropdown (solo en desktop)
  if (windowWidth.value >= 768) {
    setTimeout(() => {
      const dropdown = document.querySelector('.category-dropdown:hover');
      const relatedTarget = event.relatedTarget;
      // No cerrar si el mouse está yendo hacia el dropdown o si el dropdown está siendo hovered
      if (!dropdown && relatedTarget && !relatedTarget.closest('.category-dropdown')) {
        openDropdownCategory.value = null;
      }
    }, 200);
  }
};

// Función para toggle del dropdown con click (funciona en desktop y móvil)
const handleCategoryClick = async (event, cat) => {
  event.stopPropagation(); // Prevenir que el evento se propague
  event.preventDefault(); // Prevenir comportamiento por defecto
  
  if (!cat.has_children_with_products) {
    applyImmediate(cat.id);
    return;
  }
  
  // Si el dropdown ya está abierto para esta categoría, mantenerlo abierto (no cerrarlo)
  // Solo cerrarlo si se hace clic en otra parte
  if (openDropdownCategory.value === cat.id) {
    // Si ya está abierto, mantenerlo abierto (no hacer toggle)
    return;
  }
  
  // Abrir el dropdown para esta categoría
  openDropdownCategory.value = cat.id;
  
  // Cargar subcategorías si no están en cache
  if (!childrenCache.value.has(cat.id)) {
    await loadSubChildren(cat.id);
  }
  
  // Forzar actualización del DOM
  await nextTick();
};

// Función para calcular la posición del dropdown (fixed positioning)
const getDropdownPosition = (cat) => {
  // Retornar estilos básicos, la posición se calculará en el watch
  return {
    zIndex: '99999',
    position: 'fixed',
    display: 'block',
    visibility: 'visible',
    opacity: '1',
    backgroundColor: 'white'
  };
};

// Watch para actualizar la posición del dropdown cuando se abre
watch(openDropdownCategory, async (newVal) => {
  if (newVal !== null) {
    await nextTick();
    const cat = props.categories.find(c => c.id === newVal);
    if (cat) {
      const buttonElement = document.querySelector(`[data-category-button-id="${cat.id}"]`);
      if (buttonElement) {
        const rect = buttonElement.getBoundingClientRect();
        const dropdown = document.querySelector(`[data-cat-id="${cat.id}"].category-dropdown`);
        if (dropdown) {
          dropdown.style.top = `${rect.bottom + 4}px`;
          dropdown.style.left = `${rect.left}px`;
        }
      }
    }
  }
});

// Función para toggle del acordeón dentro del dropdown
const toggleDropdownItem = async (cat) => {
  if (!cat.has_children_with_products) {
    applyImmediate(cat.id);
    openDropdownCategory.value = null;
    return;
  }
  
  const isExpanding = !dropdownExpanded.value[cat.id];
  dropdownExpanded.value[cat.id] = isExpanding;
  
  // Si estamos expandiendo y no tenemos los hijos, cargarlos
  if (isExpanding && !childrenCache.value.has(cat.id)) {
    await loadSubChildren(cat.id);
  }
};

// Función para manejar clics en categorías del menú dropdown: si tiene subcategorías, abre drawer; si no, aplica filtro
const handleDropdownMenuClick = async (cat) => {
  if (cat.has_children_with_products) {
    // Si tiene subcategorías, abrir drawer y navegar a esa categoría
    if (!drawerOpen.value) {
      resetMenuToRoot();
      drawerOpen.value = true;
      await nextTick();
    } else {
      resetMenuToRoot();
      await nextTick();
    }
    // Navegar a la categoría (abrirá el nivel de subcategorías)
    await openNode(cat);
  } else {
    // Si no tiene subcategorías, aplicar filtro directamente
    applyImmediate(cat.id);
  }
};
const goToHome = () => {
  // Siempre regresar a la página principal sin filtros
  router.get(route('catalogo.index', { store: props.store.slug }), {}, { preserveState: true, replace: true, preserveScroll: true });
  drawerOpen.value = false;
  sort.value = 'latest'; // Resetear ordenamiento al ir a inicio
};
const applySearch = () => {
  router.get(route('catalogo.index', { store: props.store.slug }), { 
    search: search.value || undefined, 
    category: props.filters.category || undefined,
    sort: sort.value !== 'latest' ? sort.value : undefined
  }, { preserveState: true, replace: true, preserveScroll: true });
};

const applySort = () => {
  router.get(route('catalogo.index', { store: props.store.slug }), { 
    search: search.value || undefined, 
    category: props.filters.category || undefined,
    categories: props.filters.categories || undefined,
    promo: props.filters.promo || undefined,
    sort: sort.value !== 'latest' ? sort.value : undefined
  }, { preserveState: true, replace: true, preserveScroll: true });
  showSortMenu.value = false; // Cerrar el menú después de aplicar
};

const resetFilters = () => {
  sort.value = 'latest';
  search.value = '';
  router.get(route('catalogo.index', { store: props.store.slug }), {}, { preserveState: true, replace: true, preserveScroll: true });
  showSortMenu.value = false;
};

const toggleSortMenu = () => {
  showSortMenu.value = !showSortMenu.value;
};

// Función para obtener el texto del ordenamiento actual
const getSortLabel = () => {
  const labels = {
    'latest': 'Más recientes',
    'name_asc': 'Nombre (A-Z)',
    'name_desc': 'Nombre (Z-A)',
    'price_asc': 'Precio: menor a mayor',
    'price_desc': 'Precio: mayor a menor',
    'category_asc': 'Categoría (A-Z)',
    'category_desc': 'Categoría (Z-A)'
  };
  return labels[sort.value] || 'Ordenar';
};
const searchInput = ref(null); // Referencia al input de búsqueda

const toggleSearch = () => {
    if (isSearchActive.value) {
        // Si estamos cerrando, marcar la bandera
        isClosingSearch.value = true;
        isSearchActive.value = false;
        search.value = '';
        // Resetear la bandera después de un pequeño delay
        setTimeout(() => {
            isClosingSearch.value = false;
        }, 200);
    } else {
        isSearchActive.value = true;
        // nextTick espera a que Vue actualice el DOM (muestre el input)
        // antes de intentar ponerle el foco.
        nextTick(() => {
            if (searchInput.value) {
            searchInput.value.focus();
            }
        });
    }
};

const handleSearchBlur = (event) => {
    // No hacer nada si estamos cerrando manualmente o si el clic fue en el botón de cerrar
    if (isClosingSearch.value) {
        return;
    }
    if (event.relatedTarget && event.relatedTarget.closest('button[aria-label="Cerrar búsqueda"]')) {
        return;
    }
    // Solo cerrar si no hay texto en la búsqueda
    if (!search.value) {
        setTimeout(() => {
            if (!isClosingSearch.value && !search.value) {
                isSearchActive.value = false;
            }
        }, 100);
    }
};
// --- FIN LÓGICA ---

// Helpers de promoción: la promo global de tienda tiene prioridad
const hasPromo = (product) => {
  return (props.store?.promo_active && props.store?.promo_discount_percent) || (product.promo_active && product.promo_discount_percent);
};
const promoPercent = (product) => {
  if (props.store?.promo_active && props.store?.promo_discount_percent) return Number(props.store.promo_discount_percent);
  if (product.promo_active && product.promo_discount_percent) return Number(product.promo_discount_percent);
  return 0;
};

// Ref para forzar re-render del banner cuando cambian las promociones
const promoUpdateKey = ref(0);

// Layout personalizado - SOLO se aplican si NO está en modo por defecto
const logoPosition = computed(() => {
    if (props.store?.catalog_use_default) return 'center'; // Modo por defecto: siempre centro
    return props.store?.catalog_logo_position || 'center';
});

const menuType = computed(() => {
    if (props.store?.catalog_use_default) return 'hamburger'; // Modo por defecto: siempre hamburguesa
    return props.store?.catalog_menu_type || 'hamburger';
});

const productTemplate = computed(() => {
    if (props.store?.catalog_use_default) return 'default'; // Modo por defecto: siempre default
    return props.store?.catalog_product_template || 'default';
});

const headerStyle = computed(() => {
    if (props.store?.catalog_use_default) return 'default'; // Modo por defecto: siempre default
    return props.store?.catalog_header_style || 'default';
});

const logoClasses = computed(() => {
    const base = 'flex items-center justify-center z-10 transition-all duration-300 ease-out';
    let position = '';
    if (logoPosition.value === 'left') {
        position = 'absolute left-0';
    } else if (logoPosition.value === 'right') {
        // Cuando está a la derecha, dar espacio para la lupa (mínimo 60px desde el borde derecho)
        position = 'absolute right-16 sm:right-20';
    } else {
        position = 'absolute left-1/2';
    }
    const searchState = isSearchActive.value 
        ? 'opacity-0 scale-75 pointer-events-none' 
        : 'opacity-100 scale-100 pointer-events-auto';
    return `${base} ${position} ${searchState}`;
});

const logoStyle = computed(() => {
    if (logoPosition.value === 'center') {
        return isSearchActive.value 
            ? { transform: 'translate(-50%, 0) translateX(-100px)' } 
            : { transform: 'translate(-50%, 0)' };
    }
    return {};
});

// Colores personalizados del catálogo
const catalogUseDefault = computed(() => props.store?.catalog_use_default ?? true);

// Colores granulares
const headerBgColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_header_bg_color || '#FFFFFF';
});

const headerTextColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_header_text_color || '#1F2937';
});

const buttonBgColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_button_bg_color || '#2563EB';
});

const buttonTextColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_button_text_color || '#FFFFFF';
});

const bodyBgColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_body_bg_color || '#FFFFFF';
});

const bodyTextColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_body_text_color || '#1F2937';
});

const inputBgColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_input_bg_color || '#FFFFFF';
});

const inputTextColor = computed(() => {
    if (catalogUseDefault.value) return null;
    return props.store?.catalog_input_text_color || '#1F2937';
});

// Estilos dinámicos - SOLO se aplican si NO está en modo por defecto
const headerStyleObj = computed(() => {
    if (catalogUseDefault.value) return {}; // Modo por defecto: no aplicar estilos personalizados
    if (!headerBgColor.value || !headerTextColor.value) return {};
    return {
        backgroundColor: headerBgColor.value,
        color: headerTextColor.value,
    };
});

const bodyStyleObj = computed(() => {
    if (catalogUseDefault.value) return {}; // Modo por defecto: no aplicar estilos personalizados
    if (!bodyBgColor.value || !bodyTextColor.value) return {};
    return {
        backgroundColor: bodyBgColor.value,
        color: bodyTextColor.value,
    };
});

const inputStyleObj = computed(() => {
    if (catalogUseDefault.value) return {}; // Modo por defecto: no aplicar estilos personalizados
    if (!inputBgColor.value || !inputTextColor.value) return {};
    return {
        backgroundColor: inputBgColor.value,
        color: inputTextColor.value,
    };
});

const buttonStyleObj = computed(() => {
    if (catalogUseDefault.value) return {}; // Modo por defecto: no aplicar estilos personalizados
    if (!buttonBgColor.value || !buttonTextColor.value) return {};
    return {
        backgroundColor: buttonBgColor.value,
        color: buttonTextColor.value,
    };
});
const catalogButtonColor = computed(() => {
    if (catalogUseDefault.value) {
        return '#1F2937'; // gray-800 por defecto
    }
    return props.store?.catalog_button_color || '#1F2937';
});

// Usar los colores de botones para las burbujas flotantes
const cartBubbleStyle = computed(() => {
    if (catalogUseDefault.value) return {};
    if (!buttonBgColor.value || !buttonTextColor.value) return {};
    return {
        backgroundColor: buttonBgColor.value + '70',
        ringColor: buttonBgColor.value + '50',
        color: buttonTextColor.value,
    };
});

const socialButtonStyle = computed(() => {
    if (catalogUseDefault.value) return {};
    if (!buttonBgColor.value || !buttonTextColor.value) return {};
    return {
        backgroundColor: buttonBgColor.value + '70',
        ringColor: buttonBgColor.value + '50',
        color: buttonTextColor.value,
    };
});

const catalogPromoBannerColor = computed(() => {
    if (props.store?.catalog_use_default) {
        return '#DC2626'; // red-600 por defecto
    }
    return props.store?.catalog_promo_banner_color || '#DC2626';
});

const catalogPromoBannerTextColor = computed(() => {
    if (props.store?.catalog_use_default) {
        return '#FFFFFF'; // white por defecto
    }
    return props.store?.catalog_promo_banner_text_color || '#FFFFFF';
});

// Estilos dinámicos para la cinta de ofertas
const promoBannerStyle = computed(() => {
    if (props.store?.catalog_use_default) {
        return {}; // Usa clases de Tailwind por defecto
    }
    return {
        backgroundColor: catalogPromoBannerColor.value + '60', // Agregar opacidad 60%
        color: catalogPromoBannerTextColor.value,
    };
});

const promoBannerHoverStyle = computed(() => {
    if (props.store?.catalog_use_default) {
        return {}; // Usa clases de Tailwind por defecto
    }
    return {
        backgroundColor: catalogPromoBannerColor.value + '70', // Agregar opacidad 70% en hover
        color: catalogPromoBannerTextColor.value,
    };
});

// Estilos dinámicos para los botones de comprar
const buttonStyle = computed(() => {
    if (props.store?.catalog_use_default) {
        return {}; // Usa clases de Tailwind por defecto
    }
    // Usar los colores granulares de botones
    if (!buttonBgColor.value || !buttonTextColor.value) return {};
    return {
        backgroundColor: buttonBgColor.value,
        color: buttonTextColor.value,
    };
});

// Estilos dinámicos para badges de promoción
const promoBadgeStyle = computed(() => {
    if (props.store?.catalog_use_default) {
        return {}; // Usa clases de Tailwind por defecto
    }
    return {
        backgroundColor: catalogPromoBannerColor.value,
        color: '#FFFFFF',
    };
});

// Estado global de promos - asegurar que siempre tenga acceso a props.store
const storePromoActive = computed(() => {
    try {
        // Forzar reactividad accediendo directamente a props.store y todos sus valores relevantes
        const store = props.store;
        const _filters = props.filters?.category; // Forzar reactividad cuando cambia la categoría
        const _updateKey = promoUpdateKey.value; // Forzar reactividad cuando cambia
        const _promoActive = store?.promo_active; // Acceder explícitamente para forzar reactividad
        const _promoPercent = store?.promo_discount_percent; // Acceder explícitamente para forzar reactividad
        
        if (!store) return false;
        
        // Verificar promo_active: puede venir como 1, true, "1", o cualquier valor truthy
        const promoActiveValue = store.promo_active;
        const isPromoActive = promoActiveValue === 1 || 
                             promoActiveValue === true || 
                             promoActiveValue === "1" ||
                             (promoActiveValue && promoActiveValue !== 0 && promoActiveValue !== "0");
        
        // Verificar que haya un porcentaje > 0
        const percent = Number(store.promo_discount_percent || 0);
        
        // Debug temporal - activar para ver valores

        
        // Solo mostrar si está activo Y tiene porcentaje configurado
        return isPromoActive && percent > 0;
    } catch (e) { 
        return false; 
    }
});
const anyProductPromo = computed(() => {
    try {
        const arr = props.products?.data || [];
        return arr.some(p => Boolean(p?.promo_active) && Number(p?.promo_discount_percent || 0) > 0);
    } catch (e) { return false; }
});
const maxPromoPercent = computed(() => {
    let max = 0;
    // Siempre verificar promociones de la tienda primero (globales)
    if (storePromoActive.value && props.store?.promo_discount_percent) {
        max = Number(props.store.promo_discount_percent) || 0;
    }
    // Si hay productos con promoción en toda la tienda, usar el máximo global
    if (props.hasProductsWithPromo === true && props.maxProductPromoPercent) {
        max = Math.max(max, Number(props.maxProductPromoPercent) || 0);
    }
    // También verificar productos de la página actual como respaldo
    try {
        const arr = props.products?.data || [];
        for (const p of arr) {
            const pct = Number(p?.promo_discount_percent || 0);
            if (Boolean(p?.promo_active) && pct > 0) max = Math.max(max, pct);
        }
    } catch (e) {}
    // Retornar max solo si es > 0, para no mostrar "0%" cuando no hay promociones
    return max;
});

// Computed para verificar promociones directamente desde props (respaldo para reactividad)
const checkStorePromoDirect = computed(() => {
    try {
        // Forzar reactividad accediendo directamente a props
        const store = props.store;
        const _updateKey = promoUpdateKey.value; // Forzar reactividad
        const _filters = props.filters?.category; // Forzar reactividad cuando cambia categoría
        
        if (!store) return false;
        const promoActiveValue = store.promo_active;
        const isPromoActive = promoActiveValue === 1 || 
                             promoActiveValue === true || 
                             promoActiveValue === "1" ||
                             (promoActiveValue && promoActiveValue !== 0 && promoActiveValue !== "0");
        const percent = Number(store.promo_discount_percent || 0);
        return isPromoActive && percent > 0;
    } catch (e) {
        return false;
    }
});

const hasAnyPromoGlobally = computed(() => {
    // Forzar reactividad accediendo a props.filters, props.store y promoUpdateKey
    const _filters = props.filters?.category || props.filters?.search || props.filters?.promo;
    const _store = props.store;
    const _updateKey = promoUpdateKey.value; // Forzar reactividad cuando cambia
    const _hasProductsPromo = props.hasProductsWithPromo; // Forzar reactividad cuando cambia
    
    // SIEMPRE verificar primero las promociones de la tienda (globales)
    // Estas deben mostrarse sin importar la categoría actual o los productos visibles
    const storeHasPromo = storePromoActive.value;
    // También verificar directamente desde props como respaldo
    const storeHasPromoDirect = checkStorePromoDirect.value;
    
    if (storeHasPromo || storeHasPromoDirect) {
        return true;
    }
    
    // Si no hay promociones globales activas, verificar si hay productos con promoción en toda la tienda
    // (no solo en la página actual)
    if (props.hasProductsWithPromo === true) {
        return true;
    }
    
    // Como respaldo, también verificar productos individuales en la página actual
    return anyProductPromo.value;
});

// Watch para detectar cambios en props.store completo y forzar actualización
watch(() => props.store, (newStore, oldStore) => {
    // Forzar re-evaluación de computed cuando cambia el store (por ejemplo, al navegar)
    nextTick(() => {
        // Siempre incrementar la key cuando cambia el store para forzar actualización
        // Esto asegura que los banners se actualicen incluso si los valores no cambian pero el objeto sí
        promoUpdateKey.value++;
    });
}, { immediate: true, deep: true });

// --- LÓGICA PARA CARGAR MÁS PRODUCTOS (INFINITE SCROLL) ---
const allProducts = ref([]);
const nextPageUrl = ref(null);
const isLoadingMore = ref(false);

// Inicializar y resetear cuando cambian los productos base (filtros o navegación normal)
watch(() => props.products, (newProducts) => {
    if (newProducts) {
        // Si estamos en la página 1, reiniciamos la lista completa
        if (newProducts.current_page === 1) {
             console.log("Reiniciando productos (Pagina 1)");
             allProducts.value = newProducts.data ? [...newProducts.data] : [];
        } else {
             // Si recibimos una página > 1 desde Inertia (por navegación normal),
             // decidimos si reemplazar o anexar.
             // Para evitar inconsistencias, si la navegación fue via router (cambio de URL),
             // lo mejor es confiar en lo que llega.
             // PERO, para nuestro "Load More" manual, usaremos fetch abajo.
             
             // Si allProducts está vacío (aterrizaje directo en pg 2), llenarlo.
             if (allProducts.value.length === 0) {
                 allProducts.value = newProducts.data ? [...newProducts.data] : [];
             } 
             // Si ya tenemos productos, y llega nueva data via props (por ejemplo, el usuario cambia filtro),
             // el watcher de arriba (current_page === 1) lo manejaría si el filtro resetea la página.
             // Este bloque else es por si acaso.
        }
        nextPageUrl.value = newProducts.next_page_url;
    }
}, { immediate: true, deep: true });

const loadMore = async () => {
    if (!nextPageUrl.value || isLoadingMore.value) return;

    isLoadingMore.value = true;
    
    // Usamos fetch simple pidiendo JSON.
    // Laravel retorna paginación en JSON si se pide 'Accept: application/json'.
    // Esto evita la recarga de Inertia y hace la transición 100% fluida.
    try {
        const response = await fetch(nextPageUrl.value, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            // La respuesta de paginación de Laravel trae los datos directamente o dentro de .data
            // Usualmente es un objeto Paginator.
            const newItems = data.data || [];
            
            if (newItems.length > 0) {
                // Filtrar duplicados para seguridad
                const existingIds = new Set(allProducts.value.map(p => p.id));
                const uniqueNewItems = newItems.filter(p => !existingIds.has(p.id));
                allProducts.value = [...allProducts.value, ...uniqueNewItems];
            }
            
            nextPageUrl.value = data.next_page_url;
            
            // Actualizar la URL del navegador sin recargar (opcional, para que si refresca quede ahí)
            // window.history.replaceState({}, '', nextPageUrl.value); 
        } else {
            console.error("Error al cargar más productos:", response.status);
        }
    } catch (error) {
        console.error("Error de red al cargar más productos:", error);
    } finally {
        isLoadingMore.value = false;
    }
};

// Watch para sincronizar el valor de sort cuando cambien los props.filters
watch(() => props.filters?.sort, (newSort) => {
    if (newSort) {
        sort.value = newSort;
    } else {
        sort.value = 'latest';
    }
}, { immediate: true });

// Watch adicional para props.filters para detectar cambios de categoría
watch(() => props.filters?.category, () => {
    // Forzar re-evaluación cuando cambia la categoría
    nextTick(() => {
        // Forzar actualización del banner cuando cambia la categoría
        promoUpdateKey.value++;
    });
}, { immediate: true });

// Watch para hasProductsWithPromo para detectar cambios en productos con promoción
watch(() => props.hasProductsWithPromo, () => {
    // Forzar actualización cuando cambia el estado de productos con promoción
    nextTick(() => {
        promoUpdateKey.value++;
    });
}, { immediate: true });
const goToPromo = () => {
    router.get(route('catalogo.index', { store: props.store.slug }), { 
        promo: true,
        sort: sort.value !== 'latest' ? sort.value : undefined
    }, { preserveState: true, replace: true, preserveScroll: true });
    drawerOpen.value = false;
};


watch(search, (value) => {
    setTimeout(() => {
        router.get(route('catalogo.index', { store: props.store.slug }), { 
            search: value,
            category: props.filters.category || undefined,
            sort: sort.value !== 'latest' ? sort.value : undefined
        }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    }, 500);
});

// Loading state (Inertia navigation)
const isLoading = ref(false);
router.on('start', () => { isLoading.value = true; });
router.on('finish', () => { 
    isLoading.value = false;
    // Forzar re-evaluación de computed después de cada navegación
    // Esto asegura que los banners de promociones se actualicen correctamente
    nextTick(() => {
        // Forzar actualización del banner después de cada navegación
        promoUpdateKey.value++;
    });
});

// Escuchar cuando Inertia actualiza los props (success event)
router.on('success', () => {
    // Forzar actualización cuando los props se actualizan exitosamente
    nextTick(() => {
        promoUpdateKey.value++;
    });
});

// Al abrir el drawer, reiniciar navegación al nivel raíz y forzar re-render para animación
const drawerItemsKey = ref(0);
const showMenuItems = ref(false);

// Función para resetear el menú al nivel raíz
const resetMenuToRoot = () => {
        menuStack.value = [];
    expanded.value = {}; // Resetear todas las expansiones de acordeón
        isLevelLoading.value = false;
    showMenuItems.value = false;
    // Forzar re-render completo (incluyendo banner de promociones)
    drawerItemsKey.value++;
    cacheUpdateKey.value++; // Forzar actualización del computed
};

// Función para abrir el drawer y resetear al nivel raíz
const openDrawer = () => {
    // Primero cerrar el drawer si está abierto (para forzar re-render completo)
    if (drawerOpen.value) {
        drawerOpen.value = false;
        // Pequeño delay para que el cierre se complete
        setTimeout(() => {
            resetMenuToRoot();
            drawerOpen.value = true;
            nextTick(() => {
                setTimeout(() => {
                    showMenuItems.value = true;
                }, 100);
            });
        }, 50);
    } else {
        resetMenuToRoot();
        drawerOpen.value = true;
        // Usar nextTick para asegurar que el reset se aplique antes de renderizar
        nextTick(() => {
            // Pequeño delay para que el DOM se actualice completamente
            setTimeout(() => {
                showMenuItems.value = true;
            }, 100);
        });
    }
};

watch(drawerOpen, (open) => {
    if (open) {
        // Asegurar que siempre esté en el nivel raíz al abrir
        if (menuStack.value.length > 0) {
            resetMenuToRoot();
        }
        // Si no hay items mostrándose, mostrarlos
        if (!showMenuItems.value) {
            nextTick(() => {
                setTimeout(() => {
                    showMenuItems.value = true;
                }, 100);
            });
        }
    } else {
        showMenuItems.value = false;
    }
});

watch(menuStack, () => {
    // Cuando cambia el menuStack (navegación entre niveles), forzar re-render
    if (drawerOpen.value) {
        drawerItemsKey.value++;
    }
}, { deep: true });

// Helpers de stock para badges en cards
// Helpers de stock para badges en cards
const isOutOfStock = (product) => {
    try {
        if (product && product.track_inventory === false) return false;
        
        // Si tiene variantes y opciones definidas (ignorar ghost variants)
        if (product.variants && product.variants.length > 0 && product.variant_options && product.variant_options.length > 0) {
            
            // FALLBACK: Si variantes suman 0 pero hay stock global, ignorar variantes
            const variantSum = product.variants.reduce((acc, v) => acc + (Number(v.stock)||0), 0);
            if (variantSum === 0 && (Number(product.quantity)||0) > 0) {
                return false;
            }

            // Verificar si alguna variante tiene stock > 0
            const hasStock = product.variants.some(v => {
                 // Si la variante no trackea inventario, se asume con stock
                if (v.track_inventory === false) return true;
                return Number(v.stock || 0) > 0;
            });
            // Si tiene variantes y al menos una tiene stock, NO está agotado
            if (hasStock) return false;
            
            // Si tiene variantes y NINGUNA tiene stock, está agotado
            return true;
        }

        // Si no tiene variantes, usar la cantidad del producto principal
        const qty = Number(product?.quantity || 0);
        return qty <= 0;
    } catch (e) { return false; }
};

const isLowStock = (product) => {
    try {
        if (product && product.track_inventory === false) return false;

        // Si tiene variantes y opciones definidas (ignorar ghost variants)
        if (product.variants && product.variants.length > 0 && product.variant_options && product.variant_options.length > 0) {
            
            // FALLBACK: Si variantes suman 0 pero hay stock global, tratar como simple
            const variantSum = product.variants.reduce((acc, v) => acc + (Number(v.stock)||0), 0);
            if (variantSum === 0 && (Number(product.quantity)||0) > 0) {
                 // Caer al block "else" (lógica simple)… o duplicarla aquí.
                 // Duplicamos lógica simple para evitar refactor masivo de ifs
                const qty = Number(product.quantity || 0);
                const alert = Number(product.alert || 0);
                if (alert <= 0) return false;
                return qty <= alert;
            }

            // Verificar primero si está totalmente agotado
            if (isOutOfStock(product)) return false;

            // Filtrar variantes que están "disponibles" (stock > 0 o sin tracking)
            const availableVariants = product.variants.filter(v => 
                v.track_inventory === false || Number(v.stock || 0) > 0
            );

            // Si no hay disponibles, debería haber caído en isOutOfStock, pero por seguridad:
            if (availableVariants.length === 0) return false;

            // Verificar si TODAS las variantes disponibles están en "picas unidades"
            // Una variante está baja si trackea inventario, tiene alerta > 0 y stock <= alerta
            const allLow = availableVariants.every(v => {
                // Si no trackea, nunca es 'low stock'
                if (v.track_inventory === false) return false;
                
                const variantAlert = Number(v.alert || 0);
                // Si no tiene alerta configurada, no es 'low stock'
                if (variantAlert <= 0) return false;
                
                return Number(v.stock || 0) <= variantAlert;
            });

            return allLow;
        } else {
             // Si no tiene variantes
            const qty = Number(product?.quantity || 0);
            const alert = Number(product?.alert || 0);
            if (qty <= 0) return false; // Si es 0 o menos, es "agotado"
            if (alert <= 0) return false;
            return qty <= alert;
        }
    } catch (e) { return false; }
};

// (El botón de acción en la tarjeta redirige directamente al detalle del producto)

// FAB de redes sociales (móvil)
const showSocialFab = ref(false);
// Menú desplegable
const showDropdownMenu = ref(false);
const hasAnySocial = computed(() => {
    try {
        const store = props.store || {};
        const phone = (store.phone ?? '').toString().replace(/[^0-9]/g, '');
        const hasFacebook = Boolean(store.facebook_url && String(store.facebook_url).trim());
        const hasInstagram = Boolean(store.instagram_url && String(store.instagram_url).trim());
        const hasTiktok = Boolean(store.tiktok_url && String(store.tiktok_url).trim());
        const hasPhone = Boolean(phone && phone.length > 0);
        const result = hasFacebook || hasInstagram || hasTiktok || hasPhone;
        return result;
    } catch (e) {
        return false;
    }
});

const socialLinks = computed(() => {
    const links = [];
    const s = props.store || {};
    const fb = (s.facebook_url ?? '').toString().trim();
    const ig = (s.instagram_url ?? '').toString().trim();
    const tt = (s.tiktok_url ?? s.tiktok ?? s.tik_tok_url ?? '').toString().trim();
    const phone = (s.phone ?? '').toString().replace(/[^0-9]/g, '');
    if (fb) links.push({ key: 'fb', href: fb, iconClass: 'text-blue-500' });
    if (ig) links.push({ key: 'ig', href: ig, iconClass: 'text-pink-500' });
    if (tt) links.push({ key: 'tt', href: tt, iconClass: 'text-black' });
    if (phone) links.push({ key: 'wa', href: `https://wa.me/${phone}` , iconClass: 'text-green-500' });
    return links;
});

const fabItems = computed(() => {
    const links = socialLinks.value;
    const spacing = 66; // px entre burbujas
    return links.map((l, i) => ({
        ...l,
        style: `transform: translate(0, -${spacing * (i + 1)}px)`,
    }));
});

// Galería de productos destacados o imágenes personalizadas
const galleryItems = computed(() => {
    // Debug: verificar valores
    
    // Si el tipo es 'custom' y hay imágenes, usar imágenes personalizadas
    if (props.store?.gallery_type === 'custom') {
        if (props.store?.gallery_images && props.store.gallery_images.length > 0) {
            return props.store.gallery_images;
        }
        // Si es 'custom' pero no hay imágenes, retornar array vacío para no mostrar nada
        return [];
    }
    // Si es tipo 'products' o no está configurado, usar productos destacados
    const allProducts = props.products?.data || [];
    const featured = allProducts.filter(p => hasPromo(p)).slice(0, 5);
    if (featured.length < 5) {
        const remaining = allProducts.filter(p => !featured.some(fp => fp.id === p.id)).slice(0, 5 - featured.length);
        return [...featured, ...remaining];
    }
    return featured.slice(0, 5);
});

const featuredProducts = computed(() => galleryItems.value);

const currentSlide = ref(0);
const autoPlayInterval = ref(null);

// Estado para el swipe/drag manual
const galleryContainer = ref(null);
const isDragging = ref(false);
const dragStart = ref(0);
const dragCurrent = ref(0);
const dragOffset = ref(0);

const nextSlide = () => {
    if (galleryItems.value.length > 0 && !isDragging.value) {
        currentSlide.value = (currentSlide.value + 1) % galleryItems.value.length;
        // Reiniciar autoplay después de navegación manual
        resetAutoPlay();
    }
};

const prevSlide = () => {
    if (galleryItems.value.length > 0 && !isDragging.value) {
        currentSlide.value = currentSlide.value === 0 ? galleryItems.value.length - 1 : currentSlide.value - 1;
        // Reiniciar autoplay después de navegación manual
        resetAutoPlay();
    }
};

const goToSlide = (index) => {
    currentSlide.value = index;
    resetAutoPlay();
};

const resetAutoPlay = () => {
    if (autoPlayInterval.value) {
        clearInterval(autoPlayInterval.value);
    }
    if (galleryItems.value.length > 1 && !isDragging.value) {
        autoPlayInterval.value = setInterval(nextSlide, 3000); // Cambia cada 3 segundos
    }
};

// Funciones para el swipe/drag manual
const handleDragStart = (e) => {
    // Ignorar si es un clic en el botón (excepto en móvil donde sí queremos arrastrar)
    if (e.target.closest('button') && windowWidth.value >= 768) return;
    
    isDragging.value = true;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    dragStart.value = clientX;
    dragCurrent.value = clientX;
    dragOffset.value = 0;
    
    // Pausar autoplay mientras se arrastra
    if (autoPlayInterval.value) {
        clearInterval(autoPlayInterval.value);
    }
};

const handleDragMove = (e) => {
    if (!isDragging.value) return;
    
    e.preventDefault();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    dragCurrent.value = clientX;
    dragOffset.value = dragCurrent.value - dragStart.value;
};

const handleDragEnd = (e) => {
    if (!isDragging.value) return;
    
    // Ignorar si fue un clic (no hubo movimiento significativo)
    if (Math.abs(dragOffset.value) < 5) {
        isDragging.value = false;
        dragStart.value = 0;
        dragCurrent.value = 0;
        dragOffset.value = 0;
        resetAutoPlay();
        return;
    }
    
    const threshold = 50; // Mínimo de píxeles para cambiar de slide
    const offset = dragOffset.value;
    
    if (Math.abs(offset) > threshold) {
        if (offset > 0) {
            // Arrastró hacia la derecha, ir al slide anterior
            prevSlide();
        } else {
            // Arrastró hacia la izquierda, ir al slide siguiente
            nextSlide();
        }
    }
    
    // Resetear estado
    isDragging.value = false;
    dragStart.value = 0;
    dragCurrent.value = 0;
    dragOffset.value = 0;
    
    // Reanudar autoplay después de un pequeño delay
    setTimeout(() => {
        resetAutoPlay();
    }, 300);
};

const handleGalleryBuy = (item) => {
    // Si es una imagen personalizada con producto linkeado
    if (props.store?.gallery_type === 'custom' && item.product_id && item.product) {
        router.visit(route('catalogo.show', { store: props.store.slug, product: item.product.id }));
    } else if (props.store?.gallery_type === 'products') {
        // Si es un producto normal
        buyNowFromGallery(item);
    }
};

const buyNowFromGallery = (product) => {
    router.post(route('cart.store'), {
        product_id: product.id,
        product_variant_id: null,
        quantity: 1,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Redirigir directamente al checkout después de agregar al carrito
            router.visit(route('checkout.index', { store: props.store.slug }), {
                preserveScroll: false,
            });
        }
    });
};

// Cerrar menú desplegable al hacer clic fuera
const handleClickOutside = (event) => {
    // Cerrar menú dropdown de categorías
    if (showDropdownMenu.value && !event.target.closest('[data-dropdown-menu]')) {
        showDropdownMenu.value = false;
    }
    // Cerrar dropdown de categorías del menú completo
    if (openDropdownCategory.value !== null && !event.target.closest('.category-dropdown') && !event.target.closest('.menu-full-container')) {
        openDropdownCategory.value = null;
    }
};

// Iniciar autoplay al montar y configurar listener del menú
onMounted(() => {
    resetAutoPlay();
    document.addEventListener('click', handleClickOutside);
    
    // Cerrar dropdown de notificaciones al hacer clic fuera
    window.handleClickOutsideNotifications = (e) => {
        if (showNotifications.value && !e.target.closest('.notification-dropdown-container')) {
            showNotifications.value = false;
        }
    };
    document.addEventListener('click', window.handleClickOutsideNotifications);
    
    // Listener para cerrar el menú de ordenamiento al hacer clic fuera
    window.handleClickOutsideSortMenu = (e) => {
        if (showSortMenu.value && !e.target.closest('[data-sort-menu]')) {
            showSortMenu.value = false;
        }
    };
    document.addEventListener('click', window.handleClickOutsideSortMenu);
    
    // Configurar listener para window width
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', updateWindowWidth);
        updateWindowWidth();
    }
});

onBeforeUnmount(() => {
    if (autoPlayInterval.value) {
        clearInterval(autoPlayInterval.value);
    }
    // Limpiar listener del menú desplegable
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateWindowWidth);
        if (window.handleClickOutsideNotifications) {
            document.removeEventListener('click', window.handleClickOutsideNotifications);
        }
        if (window.handleClickOutsideSortMenu) {
            document.removeEventListener('click', window.handleClickOutsideSortMenu);
        }
    }
    document.removeEventListener('click', handleClickOutside);
});

// Reset autoplay cuando cambian los items de la galería y resetear slide actual
watch(galleryItems, (newItems, oldItems) => {
    // Resetear currentSlide a 0 cuando cambian los items para evitar índices fuera de rango
    if (newItems.length > 0) {
        currentSlide.value = 0;
    }
    resetAutoPlay();
});
</script>

<template>
    <Head :title="`Catálogo de ${store.name}`">
        <template #default>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        </template>
    </Head>

    <!-- CRITICAL: Modals placed at top to guarantee rendering -->
    <RegisterModal 
        v-if="true"
        :show="showRegisterModal" 
        :store="store" 
        @close="showRegisterModal = false" 
    />
    <LoginModal
        v-if="true"
        :show="showLoginModal"
        :store="store"
        @close="showLoginModal = false"
        @switch-to-register="showLoginModal = false; showRegisterModal = true"
    />
    <PopupModal 
        v-if="store.popup_active"
        :store="store" 
        @open-register="showRegisterModal = true"
    />

    <!-- Cinta de ofertas arriba del todo (fixed) - siempre visible cuando hay promociones activas -->
    <div v-if="hasAnyPromoGlobally || checkStorePromoDirect" :key="`promo-banner-${storePromoActive || checkStorePromoDirect ? 'store' : 'products'}-${props.filters?.category || 'all'}-${props.filters?.search || ''}-${promoUpdateKey}`" class="fixed top-0 left-0 right-0 z-[60] backdrop-blur-sm" :class="store?.catalog_use_default ? 'bg-red-600/60' : ''" :style="promoBannerStyle">
        <button @click="goToPromo" class="w-full py-2 sm:py-3 shadow-lg transition-all font-extrabold uppercase tracking-wider text-xs sm:text-sm" :class="store?.catalog_use_default ? 'text-white hover:bg-red-600/70' : ''" :style="store?.catalog_use_default ? {} : promoBannerHoverStyle">
				<div class="marquee">
                <div class="marquee__inner" :class="store?.catalog_use_default ? 'text-white' : ''" :style="store?.catalog_use_default ? {} : { color: catalogPromoBannerTextColor }">
                    <span class="flex items-center gap-2 whitespace-nowrap">
							<span class="blink">🔥</span>
							Ofertas<span v-if="maxPromoPercent > 0"> hasta {{ maxPromoPercent }}%</span>
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
                    <span class="flex items-center gap-2 whitespace-nowrap">
							<span class="blink">🔥</span>
							Ofertas<span v-if="maxPromoPercent > 0"> hasta {{ maxPromoPercent }}%</span>
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
                    <span class="flex items-center gap-2 whitespace-nowrap">
							<span class="blink">🔥</span>
							Ofertas<span v-if="maxPromoPercent > 0"> hasta {{ maxPromoPercent }}%</span>
							<span aria-hidden="true">•</span>
							Toca para ver
							<span aria-hidden="true">↗</span>
						</span>
					</div>
				</div>
			</button>
		</div>

    <header 
        class="shadow-sm sticky z-50" 
        :class="[
            hasAnyPromoGlobally ? 'top-[44px] sm:top-[52px]' : 'top-0',
            catalogUseDefault ? 'bg-white' : '',
            headerStyle === 'banner_logo' ? 'bg-transparent' : ''
        ]"
        :style="headerStyleObj"
    >
        <!-- Header Banner & Logo (estilo especial) - SOLO si NO está en modo por defecto -->
        <template v-if="headerStyle === 'banner_logo' && !catalogUseDefault">
            <!-- Banner superior oscuro con dirección, menú y carrito -->
            <div class="bg-gray-800 text-white py-2 px-4">
                <div class="flex justify-between items-center gap-3">
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <!-- Botones de autenticación -->
                        <template v-if="!$page.props.customer?.user">
                            <button @click="console.log('Click Login Header'); showLoginModal = true" class="text-xs sm:text-sm hover:underline">
                                Iniciar sesión
                            </button>
                            <button @click="console.log('Click Register Header'); showRegisterModal = true" class="text-xs sm:text-sm hover:underline">
                                Registrarse
                            </button>
                        </template>
                        <template v-else>
                            <Link :href="route('customer.account', { store: store.slug })" class="text-xs sm:text-sm hover:underline">
                                Mi Cuenta
                            </Link>
                            <form @submit.prevent="router.post(route('customer.logout', { store: store.slug }))" class="inline">
                                <button type="submit" class="text-xs sm:text-sm hover:underline">
                                    Cerrar sesión
                                </button>
                            </form>
                        </template>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                        <!-- Dirección del cliente si está logueado (junto a los iconos) -->
                        <div v-if="$page.props.customer?.user && $page.props.customer?.defaultAddress" class="flex items-center gap-1.5 text-xs sm:text-sm px-2 py-1 rounded bg-gray-700/50 max-w-[120px] sm:max-w-[180px]">
                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="truncate">{{ $page.props.customer.defaultAddress.city }}</span>
                        </div>
                        <!-- Campanita de notificaciones (solo si el cliente está logueado) -->
                        <div v-if="$page.props.customer?.user" class="relative notification-dropdown-container">
                            <button 
                                @click.stop="showNotifications = !showNotifications" 
                                class="relative p-1.5 rounded-lg hover:bg-gray-700 transition-colors" 
                                aria-label="Notificaciones"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                                </svg>
                                <span v-if="$page.props.customer?.notificationsCount > 0" 
                                      class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white min-w-[1.25rem]">
                                    {{ $page.props.customer.notificationsCount > 99 ? '99+' : $page.props.customer.notificationsCount }}
                                </span>
                            </button>
                            
                            <!-- Dropdown de notificaciones -->
                            <div v-if="showNotifications" class="notification-dropdown-container absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 overflow-y-auto">
                                <div class="p-4 border-b border-gray-200">
                                    <h3 class="font-semibold text-gray-900">Notificaciones</h3>
                                </div>
                                <div v-if="$page.props.customer?.notifications && $page.props.customer.notifications.length > 0" class="divide-y divide-gray-200">
                                    <div 
                                        v-for="notification in $page.props.customer.notifications" 
                                        :key="notification.id"
                                        class="p-4 hover:bg-gray-50 cursor-pointer"
                                        @click="handleNotificationClick(notification)"
                                    >
                                        <p class="text-sm font-medium text-gray-900">{{ notification.title }}</p>
                                        <p class="text-xs text-gray-600 mt-1">{{ notification.message }}</p>
                                        <p class="text-xs text-gray-400 mt-2">{{ formatNotificationDate(notification.created_at) }}</p>
                                    </div>
                                </div>
                                <div v-else class="p-8 text-center text-sm text-gray-500">
                                    No tienes notificaciones
                                </div>
                            </div>
                        </div>
                        <Link :href="route('cart.index', { store: store.slug })" class="relative p-1.5 rounded-lg hover:bg-gray-700 transition-colors" aria-label="Carrito de compras">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
                            <span v-if="$page.props.cart.count > 0" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-4 w-4 flex items-center justify-center">
                                {{ $page.props.cart.count }}
                            </span>
                        </Link>
                        <button @click="openDrawer" class="p-1.5 rounded-lg hover:bg-gray-700 transition-colors" aria-label="Abrir menú">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Área principal con logo centrado -->
            <div class="container mx-auto px-3 sm:px-4 md:px-6 py-6 sm:py-8" :style="headerStyleObj">
                <div class="flex flex-col items-center gap-4">
            <!-- Logo centrado -->
                    <div class="flex items-center justify-center">
                        <img 
                            v-if="store.logo_url" 
                            :src="store.logo_url" 
                            :alt="`Logo de ${store.name}`" 
                            class="h-20 w-20 sm:h-24 sm:w-24 md:h-28 md:w-28 rounded-full object-cover ring-3 ring-gray-200 shadow-xl"
                        >
                    </div>
                    
                    <!-- Nombre de la tienda (opcional) -->
                    <h1 
                        v-if="store.name"
                        class="font-serif font-light text-lg sm:text-xl md:text-2xl tracking-wider text-center"
                        :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : { color: '#1F2937' }"
                    >
                        {{ store.name }}
                    </h1>
                </div>
            </div>
            
            <!-- Barra de navegación inferior con iconos -->
            <div class="container mx-auto px-3 sm:px-4 md:px-6 py-3 border-t border-gray-200" :style="headerStyleObj">
                <div class="flex items-center justify-between gap-4">
                    <!-- Iconos de navegación a la izquierda -->
                    <div class="flex items-center gap-2">
                        <button @click="goToHome" class="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Inicio" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        </button>
                        <!-- Búsqueda en header Banner & Logo -->
                        <transition name="search-expand">
                            <div v-if="!isSearchActive" class="flex items-center gap-2">
                                <button @click="toggleSearch" class="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Buscar" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </button>
                                <!-- Botón de filtro/ordenamiento -->
                                <div class="relative" @click.stop data-sort-menu>
                                    <button 
                                        @click="toggleSortMenu" 
                                        class="p-2 rounded-lg hover:bg-gray-100 transition-colors relative" 
                                        aria-label="Ordenar productos"
                                        :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                                        </svg>
                                        <span v-if="sort !== 'latest'" class="absolute -top-1 -right-1 w-2 h-2 bg-blue-500 rounded-full"></span>
                                    </button>
                                    <!-- Menú dropdown de ordenamiento -->
                                    <transition
                                        enter-active-class="transition ease-out duration-200"
                                        enter-from-class="opacity-0 scale-95 translate-y-1"
                                        enter-to-class="opacity-100 scale-100 translate-y-0"
                                        leave-active-class="transition ease-in duration-150"
                                        leave-from-class="opacity-100 scale-100 translate-y-0"
                                        leave-to-class="opacity-0 scale-95 translate-y-1"
                                    >
                                        <div 
                                            v-if="showSortMenu" 
                                            class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50"
                                            @click.stop
                                        >
                                            <div class="px-4 py-2 border-b border-gray-200">
                                                <h3 class="text-sm font-semibold text-gray-900">Ordenar por</h3>
                                            </div>
                                            <div class="py-1">
                                                <button 
                                                    @click="sort = 'latest'; applySort()" 
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    :class="sort === 'latest' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                                >
                                                    <span>Más recientes</span>
                                                    <svg v-if="sort === 'latest'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="sort = 'name_asc'; applySort()" 
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    :class="sort === 'name_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                                >
                                                    <span>Nombre (A-Z)</span>
                                                    <svg v-if="sort === 'name_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="sort = 'name_desc'; applySort()" 
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    :class="sort === 'name_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                                >
                                                    <span>Nombre (Z-A)</span>
                                                    <svg v-if="sort === 'name_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="sort = 'category_asc'; applySort()" 
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    :class="sort === 'category_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                                >
                                                    <span>Categoría (A-Z)</span>
                                                    <svg v-if="sort === 'category_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="sort = 'category_desc'; applySort()" 
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    :class="sort === 'category_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                                >
                                                    <span>Categoría (Z-A)</span>
                                                    <svg v-if="sort === 'category_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="sort = 'price_asc'; applySort()" 
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    :class="sort === 'price_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                                >
                                                    <span>Precio: menor a mayor</span>
                                                    <svg v-if="sort === 'price_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                                <button 
                                                    @click="sort = 'price_desc'; applySort()" 
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    :class="sort === 'price_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                                >
                                                    <span>Precio: mayor a menor</span>
                                                    <svg v-if="sort === 'price_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="border-t border-gray-200 py-1">
                                                <button 
                                                    @click="resetFilters()" 
                                                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                    <span>Deshacer filtros</span>
                                                </button>
                                            </div>
                                        </div>
                                    </transition>
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2 min-w-[200px] sm:min-w-[280px]">
                                <input 
                                    ref="searchInput" 
                                    v-model="search" 
                                    type="text" 
                                    placeholder="Buscar..." 
                                    @blur="handleSearchBlur"
                                    class="border border-gray-300 rounded-lg px-4 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300" 
                                    :style="inputStyleObj"
                                />
                                <button @click.stop.prevent="toggleSearch" @mousedown.prevent class="p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0" aria-label="Cerrar búsqueda">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </transition>
                    </div>
                    
                    <!-- Redes sociales a la derecha -->
                    <div class="flex items-center gap-2">
                        <a v-if="store.facebook_url" :href="store.facebook_url" target="_blank" class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors" :style="!catalogUseDefault && buttonBgColor && buttonTextColor ? { backgroundColor: buttonBgColor + '20', color: buttonBgColor } : {}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                        </a>
                        <a v-if="store.instagram_url" :href="store.instagram_url" target="_blank" class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors" :style="!catalogUseDefault && buttonBgColor && buttonTextColor ? { backgroundColor: buttonBgColor + '20', color: buttonBgColor } : {}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.012-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.08 2.525c.636-.247 1.363-.416 2.427-.465C9.53 2.013 9.884 2 12.315 2zm-1.161 1.545a1.12 1.12 0 10-1.584 1.584 1.12 1.12 0 001.584-1.584zm-3.097 3.569a3.468 3.468 0 106.937 0 3.468 3.468 0 00-6.937 0z"/></svg>
                        </a>
                        <a v-if="store.tiktok_url" :href="store.tiktok_url" target="_blank" class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors" :style="!catalogUseDefault && buttonBgColor && buttonTextColor ? { backgroundColor: buttonBgColor + '20', color: buttonBgColor } : {}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.01-1.58-.31-3.15-.82-4.7-.52-1.56-1.23-3.04-2.1-4.42a.1.1 0 00-.2-.04c-.02.13-.03.26-.05.39v7.24a.26.26 0 00.27.27c.82.04 1.63.16 2.42.37.04.83.16 1.66.36 2.47.19.82.49 1.6.86 2.33.36.73.81 1.41 1.32 2.02-.17.1-.34.19-.51.28a4.26 4.26 0 01-1.93.52c-1.37.04-2.73-.06-4.1-.23a9.8 9.8 0 01-3.49-1.26c-.96-.54-1.8-1.23-2.52-2.03-.72-.8-1.3-1.7-1.77-2.69-.47-.99-.8-2.06-1.02-3.13a.15.15 0 01.04-.15.24.24 0 01.2-.09c.64-.02 1.28-.04 1.92-.05.1 0 .19-.01 .28-.01.07 .01 .13 .02 .2 .04 .19 .04 .38 .09 .57 .14a5.2 5.2 0 005.02-5.22v-.02a.23 .23 0 00-.23-.23 .2 .2 0 00-.2-.02c-.83-.06-1.66-.13-2.49-.22-.05-.01-.1-.01-.15-.02-1.12-.13-2.25-.26-3.37-.44a.2 .2 0 01-.16-.24 .22 .22 0 01.23-.18c.41-.06 .82-.12 1.23-.18C9.9 .01 11.21 0 12.525 .02z"/></svg>
                        </a>
                        <a v-if="store.phone" :href="`https://wa.me/${String(store.phone).replace(/[^0-9]/g,'')}`" target="_blank" class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors" :style="!catalogUseDefault && buttonBgColor && buttonTextColor ? { backgroundColor: buttonBgColor + '20', color: buttonBgColor } : {}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74.97.99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64.15-.19.29-.74.94-.9 1.13-.17 .19-.33 .22-.62 .07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45 .13-.6 .13-.13 .29-.33 .43-.5 .15-.17 .19-.29 .29-.48 .1-.19 .05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0 -.5 .07-.76 .36-.26 .29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15 .19 2 3.06 4.84 4.29 .68 .29 1.21 .46 1.62 .59 .68 .22 1.3 .19 1.79 .12 .55-.08 1.7-.7 1.94-1.38 .24-.68 .24-1.26 .17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </template>
        
        <!-- Header Default y Fit (estilos normales) -->
        <nav v-else class="container mx-auto px-3 sm:px-4 md:px-6 py-3 sm:py-4 flex items-center justify-between gap-2 relative" :class="{ 'flex-wrap': menuType === 'full' && categories.length > 5 }">
            <div class="flex items-center gap-2">
                <!-- Menú hamburguesa - solo visible si menuType es hamburger -->
                <button v-if="menuType === 'hamburger' && headerStyle !== 'fit'" @click="openDrawer" class="p-2 rounded-lg hover:bg-gray-100 transition-colors z-10 flex-shrink-0 text-gray-700" aria-label="Abrir menú" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                    </svg>
                </button>

                <!-- Dirección del cliente si está logueado (móvil y desktop) - MOVIDO AQUÍ -->
                <div v-if="$page.props.customer?.user && $page.props.customer?.defaultAddress" class="flex items-center gap-1.5 sm:gap-2 text-xs px-2 sm:px-3 py-1.5 rounded-lg bg-gray-100 flex-shrink-0 max-w-[120px] sm:max-w-[150px]" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor, backgroundColor: 'rgba(0,0,0,0.05)' } : {}">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate">{{ $page.props.customer.defaultAddress.city }}</span>
                </div>
            </div>
            
            <!-- Header Fit - solo iconos - SOLO si NO está en modo por defecto -->
            <template v-if="headerStyle === 'fit' && !catalogUseDefault">
                <div class="flex items-center gap-2 z-10">
                    <button @click="goToHome" class="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-700" aria-label="Inicio" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </button>
                    <!-- Búsqueda en header Fit -->
                    <transition name="search-expand">
                        <div v-if="!isSearchActive" class="flex items-center gap-2">
                            <button @click="toggleSearch" class="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-700" aria-label="Buscar" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </button>
                            <!-- Botón de filtro/ordenamiento -->
                            <div class="relative" @click.stop data-sort-menu>
                                <button 
                                    @click="toggleSortMenu" 
                                    class="p-2 rounded-lg hover:bg-gray-100 transition-colors relative" 
                                    aria-label="Ordenar productos"
                                    :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                                    </svg>
                                    <span v-if="sort !== 'latest'" class="absolute -top-1 -right-1 w-2 h-2 bg-blue-500 rounded-full"></span>
                                </button>
                                <!-- Menú dropdown de ordenamiento (mismo que en header Banner) -->
                                <transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 scale-95 translate-y-1"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 scale-100 translate-y-0"
                                    leave-to-class="opacity-0 scale-95 translate-y-1"
                                >
                                    <div 
                                        v-if="showSortMenu" 
                                        class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50"
                                        @click.stop
                                    >
                                        <div class="px-4 py-2 border-b border-gray-200">
                                            <h3 class="text-sm font-semibold text-gray-900">Ordenar por</h3>
                                        </div>
                                        <div class="py-1">
                                            <button 
                                                @click="sort = 'latest'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'latest' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Más recientes</span>
                                                <svg v-if="sort === 'latest'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'name_asc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'name_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Nombre (A-Z)</span>
                                                <svg v-if="sort === 'name_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'name_desc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'name_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Nombre (Z-A)</span>
                                                <svg v-if="sort === 'name_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'category_asc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'category_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Categoría (A-Z)</span>
                                                <svg v-if="sort === 'category_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'category_desc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'category_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Categoría (Z-A)</span>
                                                <svg v-if="sort === 'category_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'price_asc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'price_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Precio: menor a mayor</span>
                                                <svg v-if="sort === 'price_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'price_desc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'price_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Precio: mayor a menor</span>
                                                <svg v-if="sort === 'price_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="border-t border-gray-200 py-1">
                                            <button 
                                                @click="resetFilters()" 
                                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                <span>Deshacer filtros</span>
                                            </button>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>
                        <div v-else class="flex items-center gap-2 min-w-[200px] sm:min-w-[280px]">
                            <input 
                                ref="searchInput" 
                                v-model="search" 
                                type="text" 
                                placeholder="Buscar..." 
                                @blur="handleSearchBlur"
                                class="border border-gray-300 rounded-lg px-4 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300" 
                                :style="inputStyleObj"
                            />
                            <button 
                                @click.stop.prevent="toggleSearch" 
                                @mousedown.prevent
                                class="p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0" 
                                aria-label="Cerrar búsqueda"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </transition>
                    <button @click="openDrawer" class="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-700" aria-label="Menú" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                        </svg>
                    </button>
                </div>
                <!-- Logo y nombre de la tienda en header Fit (ocultos en móvil cuando la búsqueda está activa) -->
                <div class="flex-1 items-center justify-end gap-2 sm:gap-3 z-10" :class="isSearchActive ? 'hidden sm:flex' : 'flex'">
                    <h1 
                        class="font-serif font-light text-sm sm:text-base tracking-wider"
                        :class="!catalogUseDefault && headerTextColor ? '' : 'text-gray-700'"
                        :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}"
                    >
                        {{ store.name }}
                    </h1>
                    <img 
                        v-if="store.logo_url" 
                        :src="store.logo_url" 
                        :alt="`Logo de ${store.name}`" 
                        class="h-8 w-8 sm:h-10 sm:w-10 rounded-full object-cover ring-1 ring-gray-200 shadow-sm"
                    >
                </div>
            </template>

            <!-- Menú dropdown - solo visible si menuType es dropdown y NO es header Fit -->
            <div v-if="menuType === 'dropdown' && (headerStyle !== 'fit' || catalogUseDefault)" class="relative z-10 flex-shrink-0" data-dropdown-menu @click.stop>
                <button @click="showDropdownMenu = !showDropdownMenu" class="p-2 rounded-lg hover:bg-gray-100 transition-colors flex items-center gap-2" aria-label="Abrir menú">
                    <span class="text-sm font-medium text-gray-700">Categorías</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-700 transition-transform duration-200" :class="{ 'rotate-180': showDropdownMenu }">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>
                <!-- Menú desplegable -->
                <transition 
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95 translate-y-1"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-1"
                >
                    <div v-if="showDropdownMenu" class="absolute top-full left-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50 max-h-96 overflow-y-auto dropdown-menu">
                        <button @click="goToHome(); showDropdownMenu = false;" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            Inicio
                        </button>
                        <div class="border-t my-1"></div>
                        <button 
                            v-for="cat in categories" 
                            :key="cat.id" 
                            @click="showDropdownMenu = false; handleDropdownMenuClick(cat);" 
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors flex items-center justify-between"
                        >
                            <span>{{ cat.name }}</span>
                            <span v-if="cat.has_children_with_products" class="text-xs opacity-60">›</span>
                        </button>
                    </div>
                </transition>
            </div>

            <!-- Menú completo - solo visible si menuType es full y NO es header Fit -->
            <div v-if="menuType === 'full' && (headerStyle !== 'fit' || catalogUseDefault)" class="flex-1 flex items-center gap-1 sm:gap-2 md:gap-4 z-10 overflow-x-auto scrollbar-hide menu-full-container min-w-0" style="position: relative;">
                <button @click="goToHome" class="text-xs sm:text-sm font-medium text-gray-700 hover:text-gray-900 whitespace-nowrap px-2 sm:px-3 py-1.5 sm:py-2 flex-shrink-0 transition-colors rounded-md hover:bg-gray-50 active:bg-gray-100">Inicio</button>
                <div 
                    v-for="cat in categories" 
                    :key="cat.id" 
                    class="relative"
                    @mouseenter="handleCategoryHover(cat)"
                    @mouseleave="handleCategoryLeave"
                >
                    <button 
                        @click="(e) => handleCategoryClick(e, cat)"
                        :data-category-button-id="cat.id"
                        class="text-xs sm:text-sm font-medium text-gray-700 hover:text-gray-900 whitespace-nowrap px-2 sm:px-3 py-1.5 sm:py-2 flex-shrink-0 transition-colors rounded-md hover:bg-gray-50 active:bg-gray-100 flex items-center gap-1"
                        :class="{ 'bg-gray-50': openDropdownCategory === cat.id }"
                    >
                        {{ cat.name }}
                        <span v-if="cat.has_children_with_products" class="text-xs opacity-60">▼</span>
                    </button>
                    
                    <!-- Dropdown con subcategorías -->
                    <div 
                        v-if="Number(openDropdownCategory) === Number(cat.id) && cat.has_children_with_products" 
                        class="category-dropdown fixed bg-white rounded-lg shadow-xl border border-gray-200 py-2 min-w-[280px] max-w-[400px] max-h-[500px] overflow-y-auto"
                        @mouseenter="handleCategoryHover(cat)"
                        @mouseleave="handleCategoryLeave"
                        :style="getDropdownPosition(cat)"
                        :data-cat-id="cat.id"
                        :data-open-id="openDropdownCategory"
                    >
                            <div class="px-3 py-2 border-b border-gray-200">
                                <h3 class="font-semibold text-gray-900 text-sm">{{ cat.name }}</h3>
                            </div>
                            <div class="py-1">
                                <template v-if="loadingCategories.has(cat.id)">
                                    <div class="px-4 py-3 text-sm text-gray-500">Cargando...</div>
                                </template>
                                <template v-else>
                                    <div v-for="subcat in getLevelChildren(cat.id)" :key="subcat.id" class="category-dropdown-item">
                                        <button
                                            @click="toggleDropdownItem(subcat)"
                                            class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center justify-between group"
                                        >
                                            <span>{{ subcat.name }}</span>
                                            <div class="flex items-center gap-2">
                                                <span v-if="subcat.products_count" class="text-xs text-gray-400">{{ subcat.products_count }}</span>
                                                <span v-if="subcat.has_children_with_products" class="text-gray-400 text-xs transition-transform duration-200" :class="{ 'rotate-90': dropdownExpanded[subcat.id] }">›</span>
                                            </div>
                                        </button>
                                        <!-- Sub-subcategorías (nietas) -->
                                        <transition name="submenu">
                                            <div v-if="dropdownExpanded[subcat.id] && subcat.has_children_with_products" class="bg-gray-50/50">
                                                <template v-if="loadingCategories.has(subcat.id)">
                                                    <div class="px-4 py-2 pl-8 text-xs text-gray-500">Cargando...</div>
                                                </template>
                                                <template v-else>
                                                    <button
                                                        v-for="subsubcat in getLevelChildren(subcat.id)"
                                                        :key="subsubcat.id"
                                                        @click="applyImmediate(subsubcat.id); openDropdownCategory = null"
                                                        class="w-full text-left px-4 py-2 pl-8 text-sm text-gray-600 hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                    >
                                                        <span>{{ subsubcat.name }}</span>
                                                        <span v-if="subsubcat.products_count" class="text-xs text-gray-400">{{ subsubcat.products_count }}</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </transition>
                                    </div>
                                    <div v-if="getLevelChildren(cat.id).length === 0" class="px-4 py-3 text-sm text-gray-500">No hay subcategorías disponibles</div>
                                </template>
                            </div>
                        </div>
                </div>
            </div>

            <!-- Logo - posición dinámica (oculto en header Fit) -->
            <div v-if="headerStyle !== 'fit' || catalogUseDefault" :class="logoClasses" :style="logoStyle">
                <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-12 w-12 sm:h-14 sm:w-14 md:h-16 md:w-16 rounded-full object-cover ring-2 ring-gray-100 shadow-sm">
            </div>

            <!-- Lupa / Búsqueda expandible (oculta en header Fit, ya que tiene su propia búsqueda) -->
            <div v-if="headerStyle !== 'fit' || catalogUseDefault" class="flex items-center justify-end gap-2 flex-shrink-0 z-10">
                <!-- Dirección del cliente MOVIDO a la izquierda -->
                
                <!-- Botones de autenticación -->
                <div class="hidden sm:flex items-center gap-2 text-xs" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : { color: '#6B7280' }">
                    <template v-if="!$page.props.customer?.user">
                        <button @click="console.log('Click Login Mobile'); showLoginModal = true" class="hover:underline px-2 py-1">
                            Iniciar sesión
                        </button>
                        <span>|</span>
                        <button @click="console.log('Click Register Mobile'); showRegisterModal = true" class="hover:underline px-2 py-1">
                            Registrarse
                        </button>
                    </template>
                    <template v-else>
                        <Link :href="route('customer.account', { store: store.slug })" class="hover:underline px-2 py-1">
                            Mi Cuenta
                        </Link>
                        <span>|</span>
                        <form @submit.prevent="router.post(route('customer.logout', { store: store.slug }))" class="inline">
                            <button type="submit" class="hover:underline px-2 py-1">
                                Salir
                            </button>
                        </form>
                    </template>
                </div>
                
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <!-- Campanita de notificaciones (solo si el cliente está logueado) -->
                    <div v-if="$page.props.customer?.user" class="relative notification-dropdown-container">
                        <button 
                            @click.stop="showNotifications = !showNotifications" 
                            class="relative p-2 rounded-lg hover:bg-gray-100 transition-colors" 
                            aria-label="Notificaciones"
                            :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 sm:w-6 sm:h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                            </svg>
                            <span v-if="$page.props.customer?.notificationsCount > 0" 
                                  class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white min-w-[1.25rem]">
                                {{ $page.props.customer.notificationsCount > 99 ? '99+' : $page.props.customer.notificationsCount }}
                            </span>
                        </button>
                        
                        <!-- Dropdown de notificaciones -->
                        <div v-if="showNotifications" class="notification-dropdown-container absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 overflow-y-auto">
                            <div class="p-4 border-b border-gray-200">
                                <h3 class="font-semibold text-gray-900">Notificaciones</h3>
                            </div>
                            <div v-if="$page.props.customer?.notifications && $page.props.customer.notifications.length > 0" class="divide-y divide-gray-200">
                                <div 
                                    v-for="notification in $page.props.customer.notifications" 
                                    :key="notification.id"
                                    class="p-4 hover:bg-gray-50 cursor-pointer"
                                    @click="handleNotificationClick(notification)"
                                >
                                    <p class="text-sm font-medium text-gray-900">{{ notification.title }}</p>
                                    <p class="text-xs text-gray-600 mt-1">{{ notification.message }}</p>
                                    <p class="text-xs text-gray-400 mt-2">{{ formatNotificationDate(notification.created_at) }}</p>
                                </div>
                            </div>
                            <div v-else class="p-8 text-center text-sm text-gray-500">
                                No tienes notificaciones
                            </div>
                        </div>
                    </div>
                    
                    <transition name="search-expand">
                        <div v-if="!isSearchActive" class="flex items-center gap-2">
                            <button @click="toggleSearch" class="p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Buscar" :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                                </svg>
                            </button>
                            <!-- Botón de filtro/ordenamiento -->
                            <div class="relative" @click.stop data-sort-menu>
                                <button 
                                    @click="toggleSortMenu" 
                                    class="p-2 rounded-lg hover:bg-gray-100 transition-colors relative" 
                                    aria-label="Ordenar productos"
                                    :style="!catalogUseDefault && headerTextColor ? { color: headerTextColor } : {}"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                                    </svg>
                                    <span v-if="sort !== 'latest'" class="absolute -top-1 -right-1 w-2 h-2 bg-blue-500 rounded-full"></span>
                                </button>
                                <!-- Menú dropdown de ordenamiento (reutilizar el mismo componente) -->
                                <transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 scale-95 translate-y-1"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 scale-100 translate-y-0"
                                    leave-to-class="opacity-0 scale-95 translate-y-1"
                                >
                                    <div 
                                        v-if="showSortMenu" 
                                        class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50"
                                        @click.stop
                                    >
                                        <div class="px-4 py-2 border-b border-gray-200">
                                            <h3 class="text-sm font-semibold text-gray-900">Ordenar por</h3>
                                        </div>
                                        <div class="py-1">
                                            <button 
                                                @click="sort = 'latest'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'latest' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Más recientes</span>
                                                <svg v-if="sort === 'latest'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'name_asc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'name_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Nombre (A-Z)</span>
                                                <svg v-if="sort === 'name_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'name_desc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'name_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Nombre (Z-A)</span>
                                                <svg v-if="sort === 'name_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'category_asc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'category_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Categoría (A-Z)</span>
                                                <svg v-if="sort === 'category_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'category_desc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'category_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Categoría (Z-A)</span>
                                                <svg v-if="sort === 'category_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'price_asc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'price_asc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Precio: menor a mayor</span>
                                                <svg v-if="sort === 'price_asc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button 
                                                @click="sort = 'price_desc'; applySort()" 
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors flex items-center justify-between"
                                                :class="sort === 'price_desc' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'"
                                            >
                                                <span>Precio: mayor a menor</span>
                                                <svg v-if="sort === 'price_desc'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="border-t border-gray-200 py-1">
                                            <button 
                                                @click="resetFilters()" 
                                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                <span>Deshacer filtros</span>
                                            </button>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>
                        <div v-else class="flex items-center gap-2 min-w-[200px] sm:min-w-[280px]">
                            <input 
                                ref="searchInput" 
                                v-model="search" 
                                type="text" 
                                placeholder="Buscar..." 
                                @blur="handleSearchBlur"
                                class="border border-gray-300 rounded-lg px-4 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300" 
                                :style="inputStyleObj"
                            />
                            <button @click="toggleSearch" class="p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0" aria-label="Cerrar búsqueda">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </transition>
                </div>
            </div>
        </nav>
    </header>
    <main 
        class="container mx-auto px-6 py-12" 
        :class="[
            hasAnyPromoGlobally ? 'pt-16 sm:pt-20' : 'pt-12',
            catalogUseDefault ? '' : ''
        ]"
        :style="bodyStyleObj"
    >
		<!-- Galería de productos destacados o imágenes personalizadas -->
		<div 
			v-if="galleryItems.length > 0 && !isSearchActive && !search" 
			ref="galleryContainer"
			class="mb-8 relative overflow-hidden w-full md:cursor-default cursor-grab active:cursor-grabbing"
			@touchstart="handleDragStart"
			@touchmove="handleDragMove"
			@touchend="handleDragEnd"
			@mousedown="handleDragStart"
			@mousemove="handleDragMove"
			@mouseup="handleDragEnd"
			@mouseleave="handleDragEnd"
		>
			<!-- Flechas de navegación - visibles en web y móvil -->
			<button 
				v-if="galleryItems.length > 1"
				@click="prevSlide"
				class="flex absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 z-10 bg-white/90 backdrop-blur-sm hover:bg-white text-gray-800 p-2 sm:p-3 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-110 active:scale-95"
				aria-label="Anterior"
			>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 sm:w-6 sm:h-6">
					<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
				</svg>
			</button>
			<button 
				v-if="galleryItems.length > 1"
				@click="nextSlide"
				class="flex absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 z-10 bg-white/90 backdrop-blur-sm hover:bg-white text-gray-800 p-2 sm:p-3 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-110 active:scale-95"
				aria-label="Siguiente"
			>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 sm:w-6 sm:h-6">
					<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
				</svg>
			</button>
			<div class="relative h-[300px] sm:h-[400px] md:h-[500px] lg:h-[600px]">
				<!-- Slides -->
				<div class="relative h-full overflow-hidden">
					<transition name="slide-fade" mode="out-in">
						<div 
							v-if="galleryItems[currentSlide]"
							:key="`${galleryItems[currentSlide].id || galleryItems[currentSlide].slug || currentSlide}-${currentSlide}`"
							class="absolute inset-0 gallery-slide"
							:style="isDragging ? { 
								transform: `translateX(${dragOffset}px)`,
								transition: 'none'
							} : {}"
						>
						<div class="relative h-full w-full overflow-hidden">
							<!-- Imagen o Video -->
							<div class="absolute inset-0">
								<!-- Imagen -->
								<img 
									v-if="store?.gallery_type === 'custom' ? (galleryItems[currentSlide].media_type === 'image' || !galleryItems[currentSlide].media_type) : true"
									:src="store?.gallery_type === 'custom' ? galleryItems[currentSlide].image_url : galleryItems[currentSlide].main_image_url" 
									:alt="store?.gallery_type === 'custom' ? (galleryItems[currentSlide].title || 'Imagen') : galleryItems[currentSlide].name"
									class="w-full h-full object-cover object-center"
								>
								<!-- Video local -->
								<video 
									v-else-if="store?.gallery_type === 'custom' && galleryItems[currentSlide].media_type === 'video' && galleryItems[currentSlide].video_url"
									:src="galleryItems[currentSlide].video_url"
									class="w-full h-full object-cover object-center"
									autoplay
									loop
									muted
									playsinline
								></video>
							</div>
							
							<!-- Overlay con información y botón -->
							<div v-if="store?.gallery_type === 'products' || (store?.gallery_type === 'custom' && (galleryItems[currentSlide].title || galleryItems[currentSlide].description))" class="absolute inset-0 flex flex-col justify-between p-4 sm:p-6 md:p-8 pointer-events-none">
								<!-- Título/Información -->
								<div v-if="store?.gallery_type === 'products' || galleryItems[currentSlide].title" class="text-gray-900 bg-white/70 backdrop-blur-sm rounded-lg px-4 py-3 max-w-full pointer-events-auto">
									<h3 v-if="store?.gallery_type === 'products'" class="text-xl sm:text-2xl md:text-3xl font-bold mb-2 truncate">{{ galleryItems[currentSlide].name }}</h3>
									<h3 v-else-if="galleryItems[currentSlide].title" class="text-xl sm:text-2xl md:text-3xl font-bold mb-2 truncate">{{ galleryItems[currentSlide].title }}</h3>
									<div v-if="store?.gallery_type === 'products'" class="flex items-center gap-2 sm:gap-3 flex-wrap">
										<p class="text-lg sm:text-xl md:text-2xl font-extrabold whitespace-nowrap">
											{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
												hasPromo(galleryItems[currentSlide]) ? Math.round(galleryItems[currentSlide].price * (100 - promoPercent(galleryItems[currentSlide])) / 100) : galleryItems[currentSlide].price
											) }}
										</p>
										<span v-if="hasPromo(galleryItems[currentSlide])" class="inline-flex items-center rounded text-white font-bold px-2 sm:px-3 py-1 text-xs sm:text-sm whitespace-nowrap" :class="store?.catalog_use_default ? 'bg-red-600' : ''" :style="promoBadgeStyle">
											-{{ promoPercent(galleryItems[currentSlide]) }}%
										</span>
									</div>
									<p v-if="store?.gallery_type === 'custom' && galleryItems[currentSlide].description" class="text-sm sm:text-base text-gray-700 mt-1">{{ galleryItems[currentSlide].description }}</p>
								</div>
								
								<!-- Botón comprar -->
								<div v-if="(store?.gallery_type === 'products' && store?.gallery_show_buy_button !== false) || (store?.gallery_type === 'custom' && galleryItems[currentSlide].show_buy_button && galleryItems[currentSlide].product_id)" class="flex justify-center pointer-events-auto mb-8 sm:mb-12 md:mb-16">
									<button 
										@click="handleGalleryBuy(galleryItems[currentSlide])"
										class="buy-now-gallery font-bold py-3 px-6 sm:px-8 rounded-full shadow-2xl transition-all transform hover:scale-105 active:scale-95 text-base sm:text-lg md:text-xl"
										:class="store?.catalog_use_default ? 'bg-white/80 backdrop-blur-sm text-gray-900 hover:bg-white/90 border-2 border-gray-200' : 'text-white'"
										:style="buttonStyle"
									>
										COMPRAR AHORA
									</button>
								</div>
							</div>
							
						</div>
						</div>
					</transition>
				</div>
				
				<!-- Indicadores de paginación -->
				<div v-if="galleryItems.length > 1" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2 z-10">
					<button 
						v-for="(item, index) in galleryItems" 
						:key="item.id || item.slug || index"
						@click="goToSlide(index)"
						:class="currentSlide === index ? 'bg-white w-8' : 'bg-white/50 w-2'"
						class="h-2 rounded-full transition-all duration-300"
						:aria-label="`Ir a slide ${index + 1}`"
					></button>
				</div>
			</div>
                        </div>

        <transition name="drawer">
            <div v-if="drawerOpen" class="fixed inset-0 z-[70] flex">
                <!-- Overlay con efecto cortina translúcida -->
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="drawerOpen=false"></div>
                
                <!-- Panel del menú - diseño diferente para header Fit -->
                <div 
                    class="relative h-full shadow-2xl overflow-hidden"
                    :class="headerStyle === 'fit' && !catalogUseDefault ? 'w-[75%] max-w-xs bg-gradient-to-br from-gray-50 to-white rounded-r-3xl' : 'w-[85%] max-w-sm bg-white rounded-r-2xl'"
                >
                    <!-- Header fijo -->
                    <div 
                        class="sticky top-0 z-20 border-b px-4 py-4 flex items-center justify-between"
                        :class="headerStyle === 'fit' && !catalogUseDefault ? 'bg-gradient-to-r from-gray-100 to-transparent border-gray-300' : 'bg-white border-gray-200'"
                    >
                        <div class="flex items-center gap-3">
                            <button 
                                v-if="menuStack.length" 
                                @click="goBack" 
                                class="p-1.5 rounded-lg transition-colors"
                                :class="headerStyle === 'fit' && !catalogUseDefault ? 'hover:bg-gray-200/50' : 'hover:bg-gray-100'"
                                aria-label="Volver"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-700">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                                </svg>
                            </button>
                            <h3 
                                class="font-semibold text-base"
                                :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-800' : 'text-gray-900'"
                            >{{ currentTitle }}</h3>
                        </div>
                        <button 
                            @click="drawerOpen=false" 
                            class="p-1.5 rounded-lg transition-colors"
                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'hover:bg-gray-200/50' : 'hover:bg-gray-100'"
                            aria-label="Cerrar"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-gray-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Información del cliente y autenticación -->
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 flex-shrink-0">
                        <div v-if="$page.props.customer?.user" class="space-y-2">
                            <div class="flex items-center gap-2 text-sm">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="font-medium text-gray-900">{{ $page.props.customer.user.name }}</span>
                            </div>
                            <div v-if="$page.props.customer?.defaultAddress" class="flex items-start gap-2 text-xs text-gray-600">
                                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="flex-1">{{ $page.props.customer.defaultAddress.address_line_1 }}, {{ $page.props.customer.defaultAddress.city }}</span>
                            </div>
                            <div class="flex flex-col gap-2 pt-1">
                                <div class="flex gap-2">
                                    <Link :href="route('customer.account', { store: store.slug })" @click="drawerOpen=false" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                        Mi Cuenta
                                    </Link>
                                    <span class="text-gray-400">|</span>
                                    <Link :href="route('customer.orders', { store: store.slug })" @click="drawerOpen=false" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                        Mis Pedidos
                                    </Link>
                                </div>
                                <form @submit.prevent="router.post(route('customer.logout', { store: store.slug }))" class="inline">
                                    <button type="submit" @click="drawerOpen=false" class="text-xs text-red-600 hover:text-red-700 font-medium">
                                        Cerrar sesión
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div v-else class="flex gap-3">
                            <button @click="drawerOpen=false; showLoginModal = true" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                Iniciar sesión
                            </button>
                            <span class="text-gray-400">|</span>
                            <button @click="drawerOpen=false; showRegisterModal = true" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                Registrarse
                            </button>
                        </div>
                    </div>
                    
                    <!-- Banner de Promociones (siempre visible en todos los niveles cuando hay promociones activas) -->
                    <div v-if="hasAnyPromoGlobally || checkStorePromoDirect" :key="`promo-drawer-${storePromoActive || checkStorePromoDirect ? 'store' : 'products'}-${drawerItemsKey}-${promoUpdateKey}`" class="border-b border-gray-200 bg-white flex-shrink-0">
                        <button class="w-full flex items-center justify-between px-4 py-3.5 hover:bg-gray-50 active:bg-gray-100 transition-colors" @click="goToPromo">
                            <span class="font-semibold text-red-700 uppercase text-sm">Promociones</span>
                            <span v-if="maxPromoPercent > 0" class="text-xs text-white rounded-full px-2.5 py-1 font-medium" :class="store?.catalog_use_default ? 'bg-red-600' : ''" :style="promoBadgeStyle">Hasta {{ maxPromoPercent }}%</span>
                        </button>
                        <div class="border-b border-gray-200"></div>
                    </div>
                    
                    <!-- Contenedor de slides con deslizamiento horizontal -->
                    <div class="relative overflow-hidden" :style="{ height: hasAnyPromoGlobally ? 'calc(100% - 116px)' : 'calc(100% - 73px)' }">
                        <div 
                            class="menu-slides-container"
                            :style="{ 
                                transform: `translateX(${slideTransform})`,
                                width: `${totalMenuLevels * 100}%`
                            }"
                        >
                            <!-- Nivel 1: Categorías principales -->
                            <div class="menu-slide" :style="{ width: slideWidth }">
                                <div class="h-full overflow-y-auto flex flex-col">
                                    <!-- Botón HOME (siempre visible) -->
                                    <div 
                                        class="border-b flex-shrink-0"
                                        :class="headerStyle === 'fit' && !catalogUseDefault ? 'border-gray-300' : 'border-gray-200'"
                                    >
                                        <button 
                                            @click="goToHome" 
                                            class="w-full flex items-center justify-between px-4 py-3.5 transition-colors"
                                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'hover:bg-gray-200/50 active:bg-gray-300/50' : 'hover:bg-blue-50 active:bg-blue-100'"
                                        >
                                            <span 
                                                class="font-semibold text-sm uppercase"
                                                :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-800' : 'text-blue-700'"
                                            >HOME</span>
                                            <span 
                                                class="text-xs"
                                                :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-600' : 'text-blue-600'"
                                            >›</span>
                                        </button>
                                        <div 
                                            class="border-b"
                                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'border-gray-300' : 'border-gray-200'"
                                        ></div>
                                    </div>
                                    <!-- Contenido de categorías -->
                                    <div class="flex-1 overflow-y-auto">
                                        <transition-group name="menu-item" tag="div" :key="drawerItemsKey">
                                            <div v-for="(cat, index) in props.categories" :key="`${cat.id}-${drawerItemsKey}`" class="menu-item-wrapper" :style="{ '--i': index }">
                                                <button 
                                                    class="w-full flex items-center justify-between px-4 py-3.5 transition-colors border-b" 
                                                    :class="headerStyle === 'fit' && !catalogUseDefault ? 'hover:bg-gray-200/50 active:bg-gray-300/50 border-gray-300' : 'hover:bg-gray-50 active:bg-gray-100 border-gray-200'"
                                                    @click="cat.has_children_with_products ? openNode(cat) : applyImmediate(cat.id)"
                                                >
                                                    <span 
                                                        class="font-medium text-sm"
                                                        :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-700' : 'text-gray-800 uppercase'"
                                                    >{{ cat.name }}</span>
                        <div class="flex items-center gap-2">
                                                        <span 
                                                            class="text-xs"
                                                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-400' : 'text-gray-500'"
                                                        >{{ cat.products_count }}</span>
                                                        <span 
                                                            v-if="cat.has_children_with_products" 
                                                            class="text-lg leading-none"
                                                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-500' : 'text-gray-400'"
                                                        >›</span>
                        </div>
                                                </button>
                    </div>
                                        </transition-group>
                        </div>
                                </div>
                            </div>
                            
                            <!-- Niveles anidados: Subcategorías -->
                            <template v-for="(levelData, levelIndex) in levelChildrenData" :key="`level-${levelData.levelId}-${levelIndex}-${cacheUpdateKey}`">
                                <div class="menu-slide" :style="{ width: slideWidth }">
                                    <div class="h-full overflow-y-auto flex flex-col">
                                        <!-- Botón HOME (siempre visible en niveles anidados) -->
                                        <div 
                                            class="border-b flex-shrink-0"
                                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'border-gray-300' : 'border-gray-200'"
                                        >
                                            <button 
                                                @click="goToHome" 
                                                class="w-full flex items-center justify-between px-4 py-3.5 transition-colors"
                                                :class="headerStyle === 'fit' && !catalogUseDefault ? 'hover:bg-gray-200/50 active:bg-gray-300/50' : 'hover:bg-blue-50 active:bg-blue-100'"
                                            >
                                                <span 
                                                    class="font-semibold text-sm uppercase"
                                                    :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-800' : 'text-blue-700'"
                                                >HOME</span>
                                                <span 
                                                    class="text-xs"
                                                    :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-600' : 'text-blue-600'"
                                                >›</span>
                            </button>
                                            <div 
                                                class="border-b"
                                                :class="headerStyle === 'fit' && !catalogUseDefault ? 'border-gray-300' : 'border-gray-200'"
                                            ></div>
                        </div>
                                        <!-- Contenido de categorías -->
                                        <div v-if="levelData.children.length > 0" class="flex-1 overflow-y-auto">
                                            <transition-group name="menu-item" tag="div" :key="`${levelData.levelId}-${drawerItemsKey}-${cacheUpdateKey}`">
                                                <div v-for="(cat, index) in levelData.children" :key="`${cat.id}-${drawerItemsKey}`" class="menu-item-wrapper" :style="{ '--i': index }">
                                                    <!-- Acordeón para subcategorías (dentro de niveles navegados) -->
                                                    <button 
                                                        class="w-full flex items-center justify-between px-4 py-3.5 transition-colors border-b" 
                                                        :class="headerStyle === 'fit' && !catalogUseDefault ? 'hover:bg-gray-200/50 active:bg-gray-300/50 border-gray-300' : 'hover:bg-gray-50 active:bg-gray-100 border-gray-200'"
                                                        @click="cat.has_children_with_products ? toggleNode(cat) : applyImmediate(cat.id)"
                                                    >
                                                        <span 
                                                            class="font-medium text-sm"
                                                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-700' : 'text-gray-800'"
                                                        >{{ cat.name }}</span>
                                <div class="flex items-center gap-3">
                                                            <span 
                                                                v-if="cat.has_children_with_products" 
                                                                class="text-lg font-light leading-none min-w-[20px] text-center"
                                                                :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-600' : 'text-gray-800'"
                                                            >
                                                                {{ expanded[cat.id] ? '−' : '+' }}
                                                            </span>
                                </div>
                            </button>
                                                    <!-- Sub-subcategorías indentadas cuando está expandido -->
                                                    <transition name="submenu">
                                                        <div 
                                                            v-if="expanded[cat.id] && cat.has_children_with_products" 
                                                            :class="headerStyle === 'fit' && !catalogUseDefault ? 'bg-gray-200/30' : 'bg-gray-50/50'"
                                                        >
                                                            <template v-if="loadingCategories.has(cat.id)">
                                                                <div class="px-4 py-3 pl-8 text-xs text-gray-500">Cargando...</div>
                                                            </template>
                                                            <transition-group v-else name="submenu-item" tag="div">
                                                                <button
                                                                    v-for="(subcat, subIndex) in getLevelChildren(cat.id)"
                                                                    :key="subcat.id"
                                                                    class="submenu-item-wrapper w-full flex items-center justify-between px-4 py-2.5 pl-8 transition-colors border-b"
                                                                    :class="headerStyle === 'fit' && !catalogUseDefault ? 'hover:bg-gray-300/50 active:bg-gray-400/50 border-gray-400/50' : 'hover:bg-gray-100 active:bg-gray-150 border-gray-200/50'"
                                                                    @click="applyImmediate(subcat.id)"
                                                                    :style="{ '--i': subIndex }"
                                                                >
                                                                    <span 
                                                                        class="font-normal text-sm"
                                                                        :class="headerStyle === 'fit' && !catalogUseDefault ? 'text-gray-600' : 'text-gray-700'"
                                                                    >{{ subcat.name }}</span>
                                                                </button>
                                                            </transition-group>
                        </div>
                                                    </transition>
                    </div>
                                            </transition-group>
                </div>
                                        <!-- Estados cuando no hay hijos o está cargando -->
                                        <div v-else-if="levelData.isLoading" class="flex-1 flex items-center justify-center px-4 py-6 text-sm text-gray-500">Cargando...</div>
                                        <div v-else class="flex-1 flex items-center justify-center px-4 py-6 text-sm text-gray-500">No hay categorías disponibles</div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
            <div v-for="i in 6" :key="i" class="animate-pulse border rounded-xl shadow-sm overflow-hidden bg-white">
                <div class="w-full h-40 sm:h-48 md:h-56 bg-gray-200"></div>
                <div class="p-4 space-y-3">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-6 bg-gray-200 rounded w-1/3"></div>
                    <div class="h-10 bg-gray-200 rounded w-full"></div>
                </div>
            </div>
        </div>

		<!-- Plantilla Default (Grid 3x3) -->
		<div v-else-if="allProducts.length > 0 && productTemplate === 'default'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
			<Link v-for="product in allProducts" :key="product.id" :href="route('catalogo.show', { store: store.slug, product: product.id })" class="group block border rounded-xl shadow-sm overflow-hidden bg-white hover:shadow-md transition">
				<div class="relative">
					<img v-if="product.main_image_url" :src="product.main_image_url" alt="Imagen del producto" class="w-full h-40 sm:h-48 md:h-56 object-cover transform group-hover:scale-105 transition duration-300">
					<span v-if="isOutOfStock(product)" class="absolute top-3 left-3 bg-red-600 text-white text-xs font-semibold px-2 py-1 rounded">Agotado</span>
					<span v-else-if="isLowStock(product)" class="absolute top-3 left-3 bg-yellow-500 text-white text-xs font-semibold px-2 py-1 rounded">¡Pocas unidades!</span>
				</div>
				<div class="p-4 flex flex-col gap-3">
					<h3 class="text-base sm:text-lg font-semibold text-gray-900 line-clamp-2" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor } : {}">{{ product.name }}</h3>
					<div class="flex items-center gap-2">
						<p class="text-lg sm:text-xl text-gray-900 font-extrabold" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor } : {}">
							{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
								hasPromo(product) ? Math.round(product.price * (100 - promoPercent(product)) / 100) : product.price
							) }}
						</p>
						<span v-if="hasPromo(product)" class="inline-flex items-center rounded text-white font-bold px-1.5 py-0.5 text-[11px] sm:text-xs" :class="store?.catalog_use_default ? 'bg-red-600' : ''" :style="promoBadgeStyle">
							-{{ promoPercent(product) }}%
						</span>
					</div>
					<p v-if="hasPromo(product)" class="text-xs sm:text-sm text-gray-400 line-through">
						{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(product.price) }}
					</p>
					<button v-if="store?.catalog_show_buy_button" @click.stop.prevent="buyNowFromGallery(product)" class="w-full py-2 px-4 rounded-lg font-semibold text-sm text-center transition duration-300 hover:opacity-90" :class="catalogUseDefault ? 'bg-blue-600 text-white hover:bg-blue-700' : ''" :style="buttonStyleObj">
						Comprar Ahora
					</button>
				</div>
			</Link>
		</div>
		
		<!-- Plantilla Big (Galería dinámica + Grid de tarjetas grandes) -->
		<div v-else-if="allProducts.length > 0 && productTemplate === 'big'" class="space-y-6">
			<!-- Los productos destacados ya se muestran en la galería dinámica arriba -->
			<!-- Mostrar el resto de productos en formato de grid de tarjetas grandes (2 columnas) -->
			<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
				<Link v-for="product in allProducts.filter(p => !featuredProducts.some(fp => fp.id === p.id))" :key="product.id" :href="route('catalogo.show', { store: store.slug, product: product.id })" class="group block border rounded-xl shadow-lg overflow-hidden bg-white hover:shadow-xl transition">
					<div class="relative h-56 bg-gray-200">
						<img v-if="product.main_image_url" :src="product.main_image_url" alt="Imagen del producto" class="w-full h-full object-cover transform group-hover:scale-105 transition duration-300">
						<span v-if="isOutOfStock(product)" class="absolute top-3 left-3 bg-red-600 text-white text-xs font-semibold px-2 py-1 rounded">Agotado</span>
						<span v-else-if="isLowStock(product)" class="absolute top-3 left-3 bg-yellow-500 text-white text-xs font-semibold px-2 py-1 rounded">¡Pocas unidades!</span>
					</div>
					<div class="p-5 flex flex-col justify-between min-h-[180px]">
						<div>
							<h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-3 line-clamp-2" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor } : {}">{{ product.name }}</h3>
							<p v-if="product.short_description" class="text-sm text-gray-600 mb-4 line-clamp-2" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor, opacity: 0.7 } : {}">
								{{ product.short_description }}
							</p>
							<div class="flex items-center gap-2 mb-2">
								<p class="text-xl sm:text-2xl text-gray-900 font-extrabold" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor } : {}">
									{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
										hasPromo(product) ? Math.round(product.price * (100 - promoPercent(product)) / 100) : product.price
									) }}
								</p>
								<span v-if="hasPromo(product)" class="inline-flex items-center rounded text-white font-bold px-2 py-1 text-xs" :class="store?.catalog_use_default ? 'bg-red-600' : ''" :style="promoBadgeStyle">
									-{{ promoPercent(product) }}%
								</span>
							</div>
							<p v-if="hasPromo(product)" class="text-sm text-gray-400 line-through mb-4">
								{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(product.price) }}
							</p>
						</div>
						<button v-if="store?.catalog_show_buy_button" @click.stop.prevent="buyNowFromGallery(product)" class="w-full py-3 px-6 rounded-lg font-bold text-center transition duration-300 hover:opacity-90" :class="catalogUseDefault ? 'bg-blue-600 text-white hover:bg-blue-700' : ''" :style="buttonStyleObj">
							Comprar Ahora
						</button>
					</div>
				</Link>
			</div>
		</div>
		
		<!-- Plantilla Full Text (Lista horizontal con texto completo) -->
		<div v-else-if="allProducts.length > 0 && productTemplate === 'full_text'" class="space-y-4">
			<Link v-for="product in allProducts" :key="product.id" :href="route('catalogo.show', { store: store.slug, product: product.id })" class="group flex gap-4 border rounded-xl shadow-sm overflow-hidden bg-white hover:shadow-md transition">
				<div class="relative w-24 h-24 sm:w-32 sm:h-32 flex-shrink-0">
					<img v-if="product.main_image_url" :src="product.main_image_url" alt="Imagen del producto" class="w-full h-full object-cover rounded-l-xl transform group-hover:scale-105 transition duration-300">
					<span v-if="isOutOfStock(product)" class="absolute top-1 left-1 bg-red-600 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded">Agotado</span>
					<span v-else-if="isLowStock(product)" class="absolute top-1 left-1 bg-yellow-500 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded">¡Pocas!</span>
				</div>
				<div class="flex-1 p-4 flex flex-col justify-between">
					<div>
						<h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2 line-clamp-2" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor } : {}">{{ product.name }}</h3>
						<div class="flex items-center gap-2 mb-1">
							<p class="text-lg sm:text-xl text-gray-900 font-extrabold" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor } : {}">
								{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(
									hasPromo(product) ? Math.round(product.price * (100 - promoPercent(product)) / 100) : product.price
								) }}
							</p>
							<span v-if="hasPromo(product)" class="inline-flex items-center rounded text-white font-bold px-1.5 py-0.5 text-[11px] sm:text-xs" :class="store?.catalog_use_default ? 'bg-red-600' : ''" :style="promoBadgeStyle">
								-{{ promoPercent(product) }}%
							</span>
						</div>
						<p v-if="hasPromo(product)" class="text-xs sm:text-sm text-gray-400 line-through mb-2">
							{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(product.price) }}
						</p>
						<p v-if="product.short_description" class="text-xs sm:text-sm text-gray-600 line-clamp-2 mb-2" :style="!catalogUseDefault && bodyTextColor ? { color: bodyTextColor, opacity: 0.7 } : {}">
							{{ product.short_description }}
						</p>
					</div>
					<button v-if="store?.catalog_show_buy_button" @click.stop.prevent="buyNowFromGallery(product)" class="w-full py-2 px-4 rounded-lg font-semibold text-sm text-center transition duration-300 hover:opacity-90" :class="catalogUseDefault ? 'bg-blue-600 text-white hover:bg-blue-700' : ''" :style="buttonStyleObj">
						Comprar Ahora
					</button>
				</div>
			</Link>
		</div>
        <div v-else>
            <div class="text-center py-16">
                <p class="text-xl font-semibold text-gray-700">No se encontraron productos</p>
                <p class="text-gray-500 mt-2">Intenta con otra búsqueda o categoría.</p>
                <Link :href="route('catalogo.index', { store: store.slug })" class="mt-4 inline-block text-blue-600 hover:underline font-semibold">
                    Limpiar filtros
                </Link>
            </div>
        </div>
        
        
        <!-- Botón Ver Más -->
        <div v-if="nextPageUrl" class="mt-8 flex justify-center">
            <button 
                @click="loadMore" 
                :disabled="isLoadingMore"
                class="px-8 py-3 font-bold text-lg rounded shadow-lg transition-transform transform active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed flex items-center gap-2 italic"
                :class="catalogUseDefault ? 'bg-blue-600 hover:bg-blue-700 text-white' : ''"
                :style="buttonStyleObj"
            >
                <svg v-if="isLoadingMore" class="animate-spin h-5 w-5" :class="catalogUseDefault ? 'text-white' : ''" :style="!catalogUseDefault ? { color: buttonTextColor } : {}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span v-else>¡Ver más!</span>
            </button>
        </div>

    </main>

    <footer class="bg-white mt-16 border-t">
        <div class="container mx-auto px-6 py-4 text-center text-gray-500">
            <p>&copy; {{ new Date().getFullYear() }} {{ store.name }}</p>
            <p class="text-xs mt-1 text-gray-400">Powered by ondigitalsolution.com</p>
        </div>
    </footer>

    <!-- FAB Social (todas las vistas, móvil y desktop) - Oculto en header Banner & Logo -->
    <div v-if="hasAnySocial && !(headerStyle === 'banner_logo' && !catalogUseDefault)" class="fixed bottom-6 left-6 z-[60]">
        <div class="relative">
            <!-- Burbujas -->
            <transition name="fade">
                <div v-if="showSocialFab" class="absolute right-0 bottom-0 flex flex-col items-end gap-3 -translate-y-14 z-10">
<a v-for="item in socialLinks" :key="item.key" :href="item.href" target="_blank" class="w-11 h-11 rounded-full bg-white/70 backdrop-blur ring-1 ring-blue-500/50 flex items-center justify-center shadow-2xl active:scale-95">
                        <svg v-if="item.key==='fb'" class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
                        <svg v-else-if="item.key==='ig'" class="w-5 h-5 text-pink-500" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.012-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.08 2.525c.636-.247 1.363-.416 2.427-.465C9.53 2.013 9.884 2 12.315 2zm-1.161 1.545a1.12 1.12 0 10-1.584 1.584 1.12 1.12 0 001.584-1.584zm-3.097 3.569a3.468 3.468 0 106.937 0 3.468 3.468 0 00-6.937 0z" clip-rule="evenodd" /><path d="M12 6.166a5.834 5.834 0 100 11.668 5.834 5.834 0 000-11.668zm0 1.545a4.289 4.289 0 110 8.578 4.289 4.289 0 010-8.578z" /></svg>
                        <svg v-else-if="item.key==='tt'" class="w-5 h-5 text-black" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.01-1.58-.31-3.15-.82-4.7-.52-1.56-1.23-3.04-2.1-4.42a.1.1 0 00-.2-.04c-.02.13-.03.26-.05.39v7.24a.26.26 0 00.27.27c.82.04 1.63.16 2.42.37.04.83.16 1.66.36 2.47.19.82.49 1.6.86 2.33.36.73.81 1.41 1.32 2.02-.17.1-.34.19-.51.28a4.26 4.26 0 01-1.93.52c-1.37.04-2.73-.06-4.1-.23a9.8 9.8 0 01-3.49-1.26c-.96-.54-1.8-1.23-2.52-2.03-.72-.8-1.3-1.7-1.77-2.69-.47-.99-.8-2.06-1.02-3.13a.15.15 0 01.04-.15.24.24 0 01.2-.09c.64-.02 1.28-.04 1.92-.05 .1 0 .19-.01 .28-.01 .07 .01 .13 .02 .2 .04 .19 .04 .38 .09 .57 .14a5.2 5.2 0 005.02-5.22v-.02a.23 .23 0 00-.23-.23 .2 .2 0 00-.2-.02c-.83-.06-1.66-.13-2.49-.22-.05-.01-.1-.01-.15-.02-1.12-.13-2.25-.26-3.37-.44a.2 .2 0 01-.16-.24 .22 .22 0 01.23-.18c.41-.06 .82-.12 1.23-.18C9.9 .01 11.21 0 12.525 .02z"/></svg>
                        <svg v-else-if="item.key==='wa'" class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74.97.99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64 .15-.19 .29-.74 .94-.9 1.13-.17 .19-.33 .22-.62 .07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45 .13-.6 .13-.13 .29-.33 .43-.5 .15-.17 .19-.29 .29-.48 .1-.19 .05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0 -.5.07-.76 .36-.26 .29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15 .19 2 3.06 4.84 4.29 .68 .29 1.21 .46 1.62 .59 .68 .22 1.3 .19 1.79 .12 .55-.08 1.7-.7 1.94-1.38 .24-.68 .24-1.26 .17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                        <svg v-else class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM13.5 19a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z"/></svg>
                    </a>
                </div>
            </transition>

            <!-- Trigger -->
            <button @click="showSocialFab = !showSocialFab" class="w-12 h-12 rounded-full backdrop-blur ring-1 text-white flex items-center justify-center shadow-2xl active:scale-95 transition-transform duration-300" :class="[catalogUseDefault ? 'bg-blue-600/70 ring-blue-500/50' : '', { 'scale-95': showSocialFab }]" :style="socialButtonStyle">
                <svg v-if="!showSocialFab" class="w-6 h-6 transition-opacity duration-200" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2c-3.18-.35-6.2-1.63-8.82-3.68a19.86 19.86 0 0 1-6.24-6.24C2.7 9.38 1.42 6.36 1.07 3.18A2 2 0 0 1 3.06 1h3a2 2 0 0 1 2 1.72c.09.74.25 1.46.46 2.16a2 2 0 0 1-.45 2.06L7.5 8.5a16 16 0 0 0 8 8l1.56-1.57a2 2 0 0 1 2.06-.45c.7.21 1.42.37 2.16.46A2 2 0 0 1 22 16.92z"/>
                </svg>
                <svg v-else class="w-6 h-6 transition-opacity duration-200" viewBox="0 0 24 24" fill="currentColor"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>
    </div>

    <!-- FAB Carrito (móvil y desktop) - Oculto en header Banner & Logo -->
    <div v-if="!(headerStyle === 'banner_logo' && !catalogUseDefault)" class="fixed bottom-6 right-6 z-50">
        <Link :href="route('cart.index', { store: store.slug })" class="relative w-12 h-12 rounded-full backdrop-blur ring-1 text-white flex items-center justify-center shadow-2xl active:scale-95" :class="catalogUseDefault ? 'bg-blue-600/70 ring-blue-500/50' : ''" :style="cartBubbleStyle">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h2l.4 2M7 13h10l3.6-7H6.4M7 13L5.4 6M7 13l-2 9m12-9l2 9M9 22a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
            <span v-if="$page.props.cart.count > 0" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                {{ $page.props.cart.count }}
            </span>
    </Link>
    </div>
    <CookieConsent />
</template>

<style scoped>
/* Cinta con gradiente y shimmer */
.promo-ribbon {
	background: linear-gradient(90deg, #ef4444, #dc2626);
	position: relative;
	overflow: hidden;
}
.promo-ribbon::before {
	content: '';
	position: absolute;
	top: 0;
	left: -50%;
	width: 50%;
	height: 100%;
	background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
	transform: skewX(-20deg);
	animation: shimmer 2.2s infinite;
}
@keyframes shimmer {
	0% { left: -60%; }
	60% { left: 120%; }
	100% { left: 120%; }
}

/* Marquee horizontal */
.marquee {
	position: relative;
	overflow: hidden;
	width: 100%;
}
.marquee__inner {
	display: inline-flex;
	gap: 2rem;
	white-space: nowrap;
	animation: marquee 12s linear infinite;
}
@keyframes marquee {
	0%   { transform: translateX(0); }
	100% { transform: translateX(-50%); }
}

/* Parpadeo suave para el ícono */
.blink { animation: blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }

/* Transiciones para el drawer (cortina translúcida) */
.drawer-enter-active {
    transition: opacity 0.25s ease;
}

.drawer-leave-active {
    transition: opacity 0.25s ease;
}

.drawer-enter-from {
    opacity: 0;
}

.drawer-leave-to {
    opacity: 0;
}

.drawer-enter-active > div:last-child {
    animation: slideInLeft 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.drawer-leave-active > div:last-child {
    animation: slideOutLeft 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideInLeft {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes slideOutLeft {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-100%);
    }
}

/* Transiciones para búsqueda expandible */
.search-expand-enter-active,
.search-expand-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.search-expand-enter-from {
    opacity: 0;
    transform: scale(0.9) translateX(10px);
}

.search-expand-leave-to {
    opacity: 0;
    transform: scale(0.9) translateX(10px);
}

.search-expand-enter-to,
.search-expand-leave-from {
    opacity: 1;
    transform: scale(1) translateX(0);
}

/* Transiciones para la galería de productos - desplazamiento automático hacia la izquierda */
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.5s ease-in-out;
}

.slide-fade-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.slide-fade-leave-to {
    opacity: 0;
    transform: translateX(-100%);
}

.slide-fade-enter-to,
.slide-fade-leave-from {
    opacity: 1;
    transform: translateX(0);
}

/* Estilos para la galería con arrastre manual */
.gallery-slide {
    will-change: transform;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

/* Efecto de menú hamburguesa estilo chalochalo.co - entrada progresiva desde la izquierda */
.menu-item-wrapper {
    margin-bottom: 0;
}

.menu-item-enter-active {
    animation: slideReveal 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
    animation-delay: calc(var(--i) * 80ms);
}

.menu-item-enter-from {
    opacity: 0;
    transform: translateX(-50px);
}

.menu-item-enter-to {
    opacity: 1;
    transform: translateX(0);
}

.menu-item-leave-active {
    transition: all 0.25s ease-in-out;
}

.menu-item-leave-from {
    opacity: 1;
    transform: translateX(0);
}

.menu-item-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}

.menu-item-move {
    transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes slideReveal {
    from {
        opacity: 0;
        transform: translateX(-50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Transición para subcategorías */
.submenu-enter-active {
    transition: all 0.3s ease-out;
    overflow: hidden;
}

.submenu-leave-active {
    transition: all 0.25s ease-in;
    overflow: hidden;
}

.submenu-enter-from {
    opacity: 0;
    max-height: 0;
    transform: translateY(-10px);
}

.submenu-enter-to {
    opacity: 1;
    max-height: 500px;
    transform: translateY(0);
}

.submenu-leave-from {
    opacity: 1;
    max-height: 500px;
    transform: translateY(0);
}

.submenu-leave-to {
    opacity: 0;
    max-height: 0;
    transform: translateY(-10px);
}

/* Efecto de barrido para subcategorías */
.submenu-item-wrapper {
    margin-bottom: 0;
}

.submenu-item-enter-active {
    animation: slideReveal 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
    animation-delay: calc(var(--i) * 60ms);
}

.submenu-item-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}

.submenu-item-enter-to {
    opacity: 1;
    transform: translateX(0);
}

.submenu-item-leave-active {
    transition: all 0.2s ease-in-out;
}

.submenu-item-leave-from {
    opacity: 1;
    transform: translateX(0);
}

.submenu-item-leave-to {
    opacity: 0;
    transform: translateX(-15px);
}

/* Estilos para menú desplegable */
.dropdown-menu {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.dropdown-menu::-webkit-scrollbar {
    width: 6px;
}

.dropdown-menu::-webkit-scrollbar-track {
    background: transparent;
}

.dropdown-menu::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 3px;
}

.dropdown-menu::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}

/* Estilos para menú completo en móvil */
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Menú completo responsive */
.menu-full-container {
    position: relative;
}

@media (max-width: 640px) {
    .menu-full-container {
        gap: 0.25rem;
        padding: 0.25rem 0;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
        max-width: calc(100vw - 8rem); /* Dejar espacio para logo y búsqueda */
    }
    
    .menu-full-container button {
        font-size: 0.75rem;
        padding: 0.375rem 0.625rem;
        min-width: fit-content;
        border-radius: 0.375rem;
        white-space: nowrap;
    }
}

@media (min-width: 641px) {
    .menu-full-container {
        gap: 0.5rem;
    }
}

.submenu-item-move {
    transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Multi-level Sliding Menu - Contenedor de slides */
.menu-slides-container {
    display: flex;
    height: 100%;
    transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    will-change: transform;
}

/* Cada nivel del menú es un slide */
.menu-slide {
    flex-shrink: 0;
    background: white;
    height: 100%;
    position: relative;
}

/* Animación de salto vertical y pulso para el botón "COMPRAR" en la galería */
.buy-now-gallery {
    animation: bounce-pulse-gallery 2s ease-in-out infinite;
}

@keyframes bounce-pulse-gallery {
    0%, 100% {
        transform: translateY(0) scale(1);
        box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.6);
    }
    25% {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 0 0 8px rgba(255, 255, 255, 0);
    }
    50% {
        transform: translateY(0) scale(1.02);
        box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
    }
    75% {
        transform: translateY(-2px) scale(1.01);
        box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
    }
}

.buy-now-gallery:hover {
    animation: none;
    transform: translateY(0) scale(1.05);
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.4);
}
</style>

<!-- MODALS -->
<RegisterModal 
    :show="showRegisterModal" 
    :store="store" 
    @close="showRegisterModal = false" 
/>
<LoginModal
    :show="showLoginModal"
    :store="store"
    @close="showLoginModal = false"
    @switch-to-register="showLoginModal = false; showRegisterModal = true"
/>
<PopupModal 
    v-if="store.popup_active"
    :store="store" 
    @open-register="showRegisterModal = true"
/>