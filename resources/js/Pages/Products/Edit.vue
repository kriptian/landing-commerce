<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import VariantInventoryModal from './VariantInventoryModal.vue';

const props = defineProps({
    product: Object,
    categories: Array, 
    selectedCategoryPath: Array,
});

const page = usePage();
const showSaved = ref(page?.props?.flash?.success ? true : false);
const successMessage = ref(page?.props?.flash?.success || '');
const confirmingSave = ref(false);
const showErrors = ref(false);
const errorMessages = ref([]);

// Escáner de código de barras
const showBarcodeScanner = ref(false);
const html5QrCode = ref(null);
const showBarcodeSuccessModal = ref(false);

const scannedBarcode = ref('');
const scanningVariant = ref(null); // { pIndex, cIndex } or null

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

// Validación previa en el navegador
const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2 MB
const onNewGalleryInput = (event) => {
    const files = Array.from(event?.target?.files || []);
    const msgs = [];
    const validFiles = [];
    for (const file of files) {
        const isImage = (file?.type || '').startsWith('image/');
        if (!isImage) {
            msgs.push(`${file.name}: no es una imagen válida.`);
            continue;
        }
        if (file.size > MAX_IMAGE_BYTES) {
            msgs.push(`${file.name}: supera el límite de 2 MB.`);
            continue;
        }
        validFiles.push(file);
    }
    if (msgs.length) {
        errorMessages.value = ['Problemas con las imágenes seleccionadas:', ...msgs];
        showErrors.value = true;
        if (event?.target) event.target.value = '';
        form.new_gallery_files = [];
        return;
    }
    form.new_gallery_files = validFiles;
};

// --- Selects en cascada multi-nivel (con preselección) ---
const levels = ref([ { options: props.categories, selected: null } ]);
const hydratePath = async () => {
    // Preseleccionamos desde la raíz a la hoja
    if (!Array.isArray(props.selectedCategoryPath) || props.selectedCategoryPath.length === 0) return;
    // Nivel 0 ya tiene opciones: categorías raíz
    for (let i = 0; i < props.selectedCategoryPath.length; i++) {
        const node = props.selectedCategoryPath[i];
        // Seleccionar en el nivel i
        if (!levels.value[i]) {
            levels.value[i] = { options: [], selected: null };
        }
        // Si el nivel i no tiene opciones (i>0), cargamos hijos del seleccionado anterior
        if (i > 0 && levels.value[i].options.length === 0) {
            const prevSelectedId = levels.value[i-1].selected;
            if (prevSelectedId) {
                try {
                    const response = await window.axios.get(route('admin.categories.children', prevSelectedId));
                    levels.value[i].options = Array.isArray(response.data?.data) ? response.data.data : [];
                } catch (error) {
                    levels.value[i].options = [];
                }
            }
        }
        // Establecer selección del nivel i
        levels.value[i].selected = node.id;
        form.category_id = node.id;
        // Asegurar que exista un nivel i+1 si hay más por seleccionar
        if (i < props.selectedCategoryPath.length - 1) {
            if (!levels.value[i+1]) levels.value[i+1] = { options: [], selected: null };
        }
    }
    // Finalmente, ofrecer hijos del último seleccionado
    const lastId = form.category_id;
    if (lastId) {
        try {
            const response = await window.axios.get(route('admin.categories.children', lastId));
            const children = Array.isArray(response.data?.data) ? response.data.data : [];
            if (children.length > 0) {
                levels.value.push({ options: children, selected: null });
            }
        } catch (error) {
            // Error silenciado
        }
    }
};

const removeDeeperLevels = (levelIndex) => {
    levels.value.splice(levelIndex + 1);
};
const onSelectAtLevel = async (levelIndex) => {
    const selectedId = levels.value[levelIndex].selected;
    removeDeeperLevels(levelIndex);
    form.category_id = selectedId || null;
    if (!selectedId) return;
    try {
        const response = await window.axios.get(route('admin.categories.children', selectedId));
        const children = Array.isArray(response.data?.data) ? response.data.data : [];
        if (children.length > 0) {
            levels.value.push({ options: children, selected: null });
            // Limpiar category_id para forzar selección de subcategoría
            form.category_id = null;
        }
    } catch (error) {
        // Error silenciado
    }
};
// --- FIN Selects en cascada ---

// Validar que si hay niveles inferiores disponibles, se debe seleccionar una hoja
const requiresSubcategory = computed(() => {
    // Si hay más de un nivel y el último nivel tiene opciones pero no está seleccionado
    // Solo validar si form.category_id no está establecido (usuario cambiando categoría)
    if (levels.value.length > 1) {
        const lastLevel = levels.value[levels.value.length - 1];
        // Solo requiere subcategoría si hay opciones pero no hay selección Y form.category_id no está establecido o es null
        if (lastLevel.options.length > 0 && !lastLevel.selected) {
            // Si form.category_id está en uno de los niveles anteriores, no requerir
            const hasValidCategory = levels.value.some(lvl => lvl.selected === form.category_id);
            return !hasValidCategory;
        }
    }
    return false;
});

// --- Lógica de Variantes (ajustada) ---
const convertJsonToText = (optionsJson) => {
    return Object.entries(optionsJson).map(([key, value]) => `${key}:${value}`).join(', ');
};

const initialVariants = props.product.variants.map(variant => {
    return {
        id: variant.id, 
        options_text: convertJsonToText(variant.options),
        price: variant.price ?? '', 
        purchase_price: variant.purchase_price ?? null,
        retail_price: variant.retail_price ?? null,
        stock: variant.stock,
        alert: variant.alert ?? null,
    };
});
// --- FIN Lógica Variantes ---


const form = useForm({
    _method: 'PUT', // Incluir desde el principio, igual que UpdateProfileInformationForm.vue
    name: props.product.name,
    barcode: props.product.barcode ?? '',
    price: props.product.price,
    purchase_price: props.product.purchase_price ?? null,
    // retail_price eliminado
    track_inventory: props.product.track_inventory ?? true,
    quantity: props.product.quantity,
    alert: props.product.alert ?? null,
    category_id: props.product.category_id,
    short_description: props.product.short_description,
    long_description: props.product.long_description,
    specifications: props.product.specifications ? JSON.parse(props.product.specifications).join(', ') : '',
    meta_keywords: props.product.meta_keywords ?? '',
    new_gallery_files: [],
    images_to_delete: [],
    variants: initialVariants, 
    variants_to_delete: [], 
    variant_attributes: [],
    variant_options: [], // Nueva estructura jerárquica
});

// --- Lógica de Variantes Jerárquicas (Nuevo sistema) ---
const variantParents = ref([]); // Variantes principales (ej: "Color", "Talla")
const newVariantName = ref(''); // Nombre para el formulario de nueva variante
// Estados para expandir/colapsar variantes (solo visual)
const expandedVariants = ref({});
const toggleExpandVariant = (parentIndex) => {
    expandedVariants.value[parentIndex] = !expandedVariants.value[parentIndex];
};

// Hidratar variantParents desde los datos del producto
const hydrateVariantParents = () => {
    if (!props.product.variant_options || !Array.isArray(props.product.variant_options)) {
        return;
    }
    
    // DEBUG: Log de lo que llega del backend
    
    // Mapa de dependencias desde variant_attributes
    const dependencyMap = {};
    if (props.product.variant_attributes && Array.isArray(props.product.variant_attributes)) {
        props.product.variant_attributes.forEach(attr => {
            if (attr.name && attr.dependsOn) {
                dependencyMap[attr.name] = {
                    dependsOn: attr.dependsOn,
                    rulesSelected: attr.rulesSelected || {},
                    rules: attr.rules || {},
                };
            }
        });
    }
    
    variantParents.value = props.product.variant_options.map((parentOption) => {
        const children = (parentOption.children || []).map((child) => {
            // Normalizar la ruta de la imagen para que sea accesible
            let imagePath = child.image_path || null;
            
            if (imagePath) {
                // Si no empieza con /storage/ y no es una URL completa, agregar /storage/
                if (!imagePath.startsWith('/storage/') && !imagePath.startsWith('http://') && !imagePath.startsWith('https://')) {
                    imagePath = '/storage/' + imagePath.replace(/^\/+/, '');
                }
            }
            
                // --- FIX DISCREPANCIA STOCK ---
                // Buscar si existe un ProductVariant (Source of Truth) que coincida con esta opción para usar su stock real.
                // Suponemos variantes simples (1 dimensión) o buscamos coincidencia exacta de opción.
                let realStock = Number(child.stock || 0);

                if (props.product.variants && props.product.variants.length > 0) {
                   // Buscamos un variant que tenga esta opción (ej: "Peso": "1.5")
                   // props.product.variants es un array de { id, options: {"Peso": "1.5"}, stock: 17 ... }
                   const matchingPv = props.product.variants.find(v => {
                       const vOpts = v.options || {};
                       // Verificamos si alguna key tiene como valor el nombre de este hijo (ej: "1.5")
                       // Y si la key coincide con el padre (parentOption.name)
                       return vOpts[parentOption.name] === child.name;
                   });

                   if (matchingPv) {
                       realStock = Number(matchingPv.stock); // Usar el stock real (17)
                   }
                }
                // -----------------------------

                return {
                    id: child.id,
                    name: child.name,
                    barcode: child.barcode || '',
                    price: child.price,
                    image: null, // No podemos cargar el archivo, solo la ruta
                    imagePreview: imagePath, // Usar la ruta normalizada como preview
                    imagePath: imagePath, // Guardar la ruta normalizada existente
                    order: child.order || 0,
                    stock: realStock, // Usar stock corregido
                    alert: child.alert,
                    purchase_price: child.purchase_price,
                };
        });
        
        const depInfo = dependencyMap[parentOption.name] || {};
        const shouldShow = !!(depInfo.dependsOn || Object.keys(depInfo.rulesSelected || {}).length || Object.keys(depInfo.rules || {}).length);
        
        return {
            id: parentOption.id,
            name: parentOption.name,
            children: children,
            order: parentOption.order || 0,
            dependsOn: depInfo.dependsOn || '',
            rules: depInfo.rules || {},
            rulesSelected: depInfo.rulesSelected || {},
            __showDependency: shouldShow,
        };
    });
    
    // Inicializar estados expandidos para todas las variantes (expandidas por defecto)
    variantParents.value.forEach((_, index) => {
        expandedVariants.value[index] = true;
    });
};

const hasSpecificInventory = computed(() => {
    return variantParents.value.some(parent => 
        parent.children && parent.children.some(child => 
            (Number(child.stock) || 0) > 0 || 
            (child.alert !== null && child.alert !== '' && child.alert !== undefined) || 
            (child.purchase_price !== null && child.purchase_price !== '' && child.purchase_price !== undefined)
        )
    );
});

const hasSpecificBarcode = computed(() => {
    return variantParents.value.some(parent => 
        parent.children && parent.children.some(child => 
           child.barcode && child.barcode !== ''
        )
    );
});

// Limpiar código de barras principal si se agregan códigos a variantes
watch(hasSpecificBarcode, (newValue) => {
    if (newValue) {
        form.barcode = '';
        form.clearErrors('barcode');
    }
});

// Calcular stock total basado en las variantes configuradas
const computedTotalStock = computed(() => {
    if (!hasSpecificInventory.value) return form.quantity;
    
    // Sumar el stock de todas las opciones hijas configuradas
    let total = 0;
    variantParents.value.forEach(parent => {
        if (parent.children) {
            parent.children.forEach(child => {
                total += Number(child.stock) || 0;
            });
        }
    });
    return total;
});

// Actualizar form.quantity visualmente
watch(computedTotalStock, (newVal) => {
    if (hasSpecificInventory.value) {
        form.quantity = newVal;
    }
});



const showInventoryModal = ref(false);
const currentInventoryVariant = ref(null);
const currentInventoryIndices = ref({ pIndex: -1, cIndex: -1 });

const openInventoryModal = (child, pIndex, cIndex) => {
    currentInventoryVariant.value = {
        stock: child.stock || 0,
        alert: child.alert,
        purchase_price: child.purchase_price,
        name: child.name
    };
    currentInventoryIndices.value = { pIndex, cIndex };
    showInventoryModal.value = true;
};

const saveInventoryData = (data) => {
    const { pIndex, cIndex } = currentInventoryIndices.value;
    if (pIndex !== -1 && cIndex !== -1) {
        variantParents.value[pIndex].children[cIndex].stock = data.stock;
        variantParents.value[pIndex].children[cIndex].alert = data.alert;
        variantParents.value[pIndex].children[cIndex].purchase_price = data.purchase_price;
    }
};

const addVariantParent = () => {
    const name = newVariantName.value.trim() || '';
    const newIndex = variantParents.value.length;
    variantParents.value.push({
        id: null,
        name: name,
        children: [],
        order: newIndex,
        dependsOn: '',
        rules: {},
        rulesSelected: {},
        __showDependency: false,
    });
    // Expandir la nueva variante por defecto
    expandedVariants.value[newIndex] = true;
    // Limpiar el input
    newVariantName.value = '';
};

const removeVariantParent = (index) => {
    variantParents.value.splice(index, 1);
};

const addVariantChild = (parentIndex) => {
    if (!variantParents.value[parentIndex].children) {
        variantParents.value[parentIndex].children = [];
    }
    variantParents.value[parentIndex].children.push({
        id: null,
        name: '',
        barcode: '',
        price: null,
        image: null,
        imagePreview: null,
        imagePath: null,
        order: variantParents.value[parentIndex].children.length,
    });
    // Asegurar que la variante esté expandida cuando se agrega un hijo
    expandedVariants.value[parentIndex] = true;
};

const removeVariantChild = (parentIndex, childIndex) => {
    variantParents.value[parentIndex].children.splice(childIndex, 1);
};

const onVariantChildImageChange = (parentIndex, childIndex, event) => {
    const file = event?.target?.files?.[0];
    if (!file) return;
    
    // Verificar si se puede agregar imagen a esta variante
    if (!canAddImageToVariant(parentIndex)) {
        // No permitir, limpiar el input
        if (event?.target) event.target.value = '';
        
        // Mostrar modal si no se ha mostrado
        if (!hasShownImageInfoModal.value) {
            showImageInfoModal.value = true;
            hasShownImageInfoModal.value = true;
        }
        return;
    }
    
    // Validación de imagen (igual que onNewGalleryInput)
    const msgs = [];
    const isImage = (file?.type || '').startsWith('image/');
    if (!isImage) {
        msgs.push(`${file.name}: no es una imagen válida.`);
    }
    if (file.size > MAX_IMAGE_BYTES) {
        msgs.push(`${file.name}: supera el límite de 2 MB.`);
    }
    
    if (msgs.length) {
        errorMessages.value = ['Problemas con la imagen de la variante:', ...msgs];
        showErrors.value = true;
        // Limpiar selección para evitar enviar archivos inválidos
        if (event?.target) event.target.value = '';
        variantParents.value[parentIndex].children[childIndex].image = null;
        variantParents.value[parentIndex].children[childIndex].imagePreview = null;
        variantParents.value[parentIndex].children[childIndex].imagePath = null; // También limpiar la ruta existente
        return;
    }
    
    variantParents.value[parentIndex].children[childIndex].image = file;
    // Limpiar imagePath existente cuando se sube una nueva
    variantParents.value[parentIndex].children[childIndex].imagePath = null;
    // Crear preview
    const reader = new FileReader();
    reader.onload = (e) => {
        variantParents.value[parentIndex].children[childIndex].imagePreview = e.target.result;
    };
    reader.readAsDataURL(file);
};

const removeVariantChildImage = (parentIndex, childIndex) => {
    variantParents.value[parentIndex].children[childIndex].image = null;
    variantParents.value[parentIndex].children[childIndex].imagePreview = null;
    variantParents.value[parentIndex].children[childIndex].imagePath = null; // También limpiar la ruta existente
};

// Preparar datos de variant_options para enviar al backend
const prepareVariantOptions = () => {
    const options = [];
    variantParents.value.forEach((parent, parentIndex) => {
        if (!parent.name.trim()) return;
        
        // Crear el padre
        const parentOption = {
            id: parent.id, // Para actualizar si existe
            name: parent.name.trim(),
            parent_id: null,
            barcode: null,
            price: null,
            image_path: null,
            order: parent.order ?? parentIndex,
            children: [],
        };
        
        // Agregar hijos
        if (parent.children && parent.children.length > 0) {
            parent.children.forEach((child, childIndex) => {
                if (!child.name.trim()) return;
                
                // Asegurar que image_path solo se envíe si existe y no es null/undefined/vacío
                // Si hay una nueva imagen (child.image), no enviar image_path (será null)
                // Si NO hay nueva imagen pero hay imagePath existente, enviarlo
                let imagePathToSend = null;
                if (!child.image && child.imagePath) {
                    // Verificar que imagePath no sea el string "null" o vacío
                    const path = String(child.imagePath || '').trim();
                    if (path && path !== 'null' && path !== 'undefined') {
                        imagePathToSend = path;
                    }
                }
                
                parentOption.children.push({
                    id: child.id, // Para actualizar si existe
                    name: child.name.trim(),
                    parent_id: null, // Se establecerá en el backend
                    barcode: child.barcode || null,
                    stock: child.stock || 0,
                    alert: child.alert,
                    purchase_price: child.purchase_price,
                    price: child.price ? parseFloat(child.price) : null,
                    image_file_key: child.image ? `variant_option_${parentIndex}_${childIndex}` : null,
                    image_path: imagePathToSend, // Enviar la ruta existente si no hay nueva imagen
                    order: child.order ?? childIndex,
                });
            });
        }
        
        if (parentOption.children.length > 0 || parentOption.name) {
            options.push(parentOption);
        }
    });
    
    return options;
};

// Construir variant_attributes desde variantParents para conservar dependencias
const buildVariantAttributes = () => {
    const attrs = [];
    variantParents.value.forEach((parent) => {
        if (!parent.name.trim()) return;
        
        const values = (parent.children || []).map(child => child.name.trim()).filter(Boolean);
        if (values.length === 0) return;
        
        const attr = {
            name: parent.name.trim(),
            values: values,
            valuesText: values.join(', '),
            dependsOn: parent.dependsOn || '',
            rules: parent.rules || {},
            rulesSelected: parent.rulesSelected || {},
        };
        
        attrs.push(attr);
    });
    
    return attrs;
};

// Ya no necesitamos attachVariantImages() porque usamos form.transform()

// Detectar si hay múltiples variantes principales con precios diferentes
const hasMultiplePricedVariants = computed(() => {
    const parentsWithPrices = variantParents.value.filter(parent => {
        if (!parent.children || parent.children.length === 0) return false;
        // Verificar si alguna opción hijo tiene precio
        return parent.children.some(child => child.price && child.price !== '' && child.price != null);
    });
    return parentsWithPrices.length > 1;
});

// Modal informativo sobre precios múltiples
const showPriceInfoModal = ref(false);
const hasShownPriceInfoModal = ref(false); // Para mostrar solo una vez por sesión

// Mensajes del modal de precios
const priceModalMessages = [
    'Has intentado agregar precios a una variante cuando ya existe otra variante principal con precios configurados.',
    '',
    '📋 Regla del Sistema:',
    'Solo puedes asignar precios a las opciones hijas de UNA variante principal. Si ya asignaste precios a una variante (por ejemplo, Color), no podrás agregar precios a otra variante (por ejemplo, Equipo).',
    '',
    '💡 Cómo funciona:',
    'Cuando un cliente seleccione una opción de la variante que tiene precios, se mostrará el precio de esa opción específica. Si no selecciona ninguna opción o selecciona una opción sin precio, se usará el precio principal del producto.',
    '',
    '💡 Sugerencia:',
    'Si necesitas diferentes precios según múltiples características, considera crear productos separados o usar una sola variante que combine ambas características.'
];

// Verificar si se puede agregar precio a esta variante
const canAddPriceToVariant = (parentIndex) => {
    // Verificar si ya hay otra variante principal con precios
    const otherPricedParent = variantParents.value.find((parent, index) => {
        if (index === parentIndex) return false; // Excluir la variante actual
        if (!parent.children || parent.children.length === 0) return false;
        return parent.children.some(child => child.price && child.price !== '' && child.price != null);
    });
    
    // Si hay otra variante con precios, no permitir agregar precio a esta
    return !otherPricedParent;
};

// Verificar si se puede agregar imagen a esta variante
const canAddImageToVariant = (parentIndex) => {
    // Verificar si ya hay otra variante principal con imágenes
    const otherImageParent = variantParents.value.find((parent, index) => {
        if (index === parentIndex) return false; // Excluir la variante actual
        if (!parent.children || parent.children.length === 0) return false;
        return parent.children.some(child => (child.imagePreview || child.image) || (child.imagePath && child.imagePath !== ''));
    });
    
    // Si hay otra variante con imágenes, no permitir agregar imagen a esta
    return !otherImageParent;
};

// Verificar si se puede agregar código de barras a esta variante
const canAddBarcodeToVariant = (parentIndex) => {
    const otherBarcodeParent = variantParents.value.find((parent, index) => {
        if (index === parentIndex) return false;
        if (!parent.children || parent.children.length === 0) return false;
        return parent.children.some(child => child.barcode && child.barcode !== '');
    });
    return !otherBarcodeParent;
};

// Verificar si se puede agregar inventario a esta variante
const canAddInventoryToVariant = (parentIndex) => {
    const otherInventoryParent = variantParents.value.find((parent, index) => {
        if (index === parentIndex) return false;
        if (!parent.children || parent.children.length === 0) return false;
        // Check stock>0, alert!=null, purchase_price!=null
        return parent.children.some(child => 
            (Number(child.stock) || 0) > 0 || 
            (child.alert !== null && child.alert !== '' && child.alert !== undefined) ||
            (child.purchase_price !== null && child.purchase_price !== '' && child.purchase_price !== undefined)
        );
    });
    return !otherInventoryParent;
};

// Manejar el input de precio
const handlePriceInput = (parentIndex, childIndex, event) => {
    const newPrice = event?.target?.value;
    
    // Si el nuevo precio está vacío o es null, permitirlo (está eliminando el precio)
    if (!newPrice || newPrice === '' || newPrice == null) {
        return;
    }
    
    // Verificar si se puede agregar precio
    if (!canAddPriceToVariant(parentIndex)) {
        // No permitir, revertir el valor
        event.target.value = '';
        variantParents.value[parentIndex].children[childIndex].price = null;
        
        // Mostrar modal si no se ha mostrado
        if (!hasShownPriceInfoModal.value) {
            showPriceInfoModal.value = true;
            hasShownPriceInfoModal.value = true;
        }
    }
};

// Modal informativo sobre imágenes múltiples
const showImageInfoModal = ref(false);
const hasShownImageInfoModal = ref(false); // Para mostrar solo una vez por sesión

// Mensajes del modal de imágenes
const imageModalMessages = [
    'Has intentado agregar imágenes a una variante cuando ya existe otra variante principal con imágenes configuradas.',
    '',
    '📋 Regla del Sistema:',
    'Solo puedes asignar imágenes a las opciones hijas de UNA variante principal. Si ya asignaste imágenes a una variante (por ejemplo, Color), no podrás agregar imágenes a otra variante (por ejemplo, Equipo).',
    '',
    '💡 Cómo funciona:',
    'Cuando un cliente seleccione una opción de la variante que tiene imágenes, se mostrará la imagen de esa opción específica en la galería. Si no selecciona ninguna opción o selecciona una opción sin imagen, se mostrarán las imágenes regulares del producto.',
    '',
    '💡 Sugerencia:',
    'Si necesitas diferentes imágenes según múltiples características, considera crear productos separados o usar una sola variante que combine ambas características.'
];

// Watch para mostrar el modal cuando se detecte múltiples precios
watch(() => variantParents.value, () => {
    // Esperar un poco para que el usuario termine de agregar precios
    if (!hasShownPriceInfoModal.value) {
        setTimeout(() => {
            if (hasMultiplePricedVariants.value && !showPriceInfoModal.value) {
                showPriceInfoModal.value = true;
                hasShownPriceInfoModal.value = true;
            }
        }, 1000); // Aumentar el delay para que no sea intrusivo
    }
}, { deep: true });

// --- FIN Lógica Variantes Jerárquicas ---

// Atributos de variantes (mantener para retrocompatibilidad)
const attributes = ref([{ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }]);
const addAttribute = () => { attributes.value.push({ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }); };
const removeAttribute = (idx) => { attributes.value.splice(idx, 1); };
const parseCsv = (text) => String(text || '').split(',').map(s => s.trim()).filter(Boolean);
const cartesian = (arrays) => arrays.reduce((a, b) => a.flatMap(x => b.map(y => x.concat([y]))), [[]]);
function isRuleChecked(attr, pv, cv) {
    const arr = (attr.rulesSelected && Array.isArray(attr.rulesSelected[pv])) ? attr.rulesSelected[pv] : [];
    return arr.includes(cv);
}
function toggleRule(attr, pv, cv, event) {
    const checked = !!event?.target?.checked;
    if (!attr.rulesSelected) attr.rulesSelected = {};
    const base = Array.isArray(attr.rulesSelected[pv]) ? [...attr.rulesSelected[pv]] : [];
    const idx = base.indexOf(cv);
    if (checked && idx === -1) base.push(cv);
    if (!checked && idx !== -1) base.splice(idx, 1);
    attr.rulesSelected[pv] = base;
}
// Alternar UI de dependencia y limpiar estado si se oculta
function toggleDependencyUi(attr) {
    attr.__showDependency = !attr.__showDependency;
    if (!attr.__showDependency) {
        attr.dependsOn = '';
        attr.rulesSelected = {};
        attr.rules = {};
    }
}
// Hidratar atributos desde variantes existentes
const hydrateAttributesFromVariants = () => {
    try {
        const vs = Array.isArray(props.product.variants) ? props.product.variants : [];
        if (vs.length === 0) return;
        const keys = new Set();
        vs.forEach(v => { Object.keys(v?.options || {}).forEach(k => keys.add(k)); });
        const next = [];
        keys.forEach((k) => {
            const valuesSet = new Set();
            vs.forEach(v => { const val = (v?.options || {})[k]; if (val != null && String(val).trim() !== '') valuesSet.add(String(val)); });
            const valuesText = Array.from(valuesSet).join(', ');
            next.push({ name: k, valuesText, dependsOn: '', rules: {}, rulesSelected: {} });
        });
        if (next.length > 0) attributes.value = next;
    } catch (_) { /* noop */ }
};

// Preferir hidratar desde lo guardado en product.variant_attributes (fuente de verdad)
const hydrateAttributesFromSaved = () => {
    try {
        const saved = props.product?.variant_attributes;
        const arr = Array.isArray(saved) ? saved : [];
        if (arr.length === 0) return false;
        const next = arr.map(sa => {
            const values = Array.isArray(sa?.values) ? sa.values : parseCsv(sa?.valuesText || '');
            const dependsOn = String(sa?.dependsOn || '').trim();
            const rulesSelected = typeof sa?.rulesSelected === 'object' && sa.rulesSelected ? sa.rulesSelected : {};
            const rules = typeof sa?.rules === 'object' && sa.rules ? sa.rules : {};
            const shouldShow = !!(dependsOn || Object.keys(rulesSelected).length || Object.keys(rules).length);
            return {
                name: String(sa?.name || ''),
                valuesText: values.join(', '),
                dependsOn,
                rulesSelected,
                rules,
                __showDependency: shouldShow,
            };
        }).filter(a => a.name);
        if (next.length > 0) {
            attributes.value = next;
            return true;
        }
    } catch (e) {}
    return false;
};

// Hidratar dependencias guardadas
const hydrateDependenciesFromProduct = () => {
    try {
        const saved = props.product?.variant_attributes || [];
        if (!Array.isArray(saved) || saved.length === 0) return;
        const map = Object.fromEntries(attributes.value.map(a => [a.name, a]));
        for (const sa of saved) {
            const local = map[sa.name];
            if (!local) continue;
            if (sa.dependsOn) {
                local.dependsOn = sa.dependsOn;
                local.__showDependency = true;
            }
            if (sa.rulesSelected && typeof sa.rulesSelected === 'object') {
                local.rulesSelected = sa.rulesSelected;
                if (Object.keys(local.rulesSelected || {}).length > 0) local.__showDependency = true;
            } else if (sa.rules && typeof sa.rules === 'object') {
                local.rules = sa.rules;
                if (Object.keys(local.rules || {}).length > 0) local.__showDependency = true;
            }
        }
    } catch (_) {}
};
// Generador: reemplaza variantes actuales con combinaciones nuevas
const generateVariantsFromAttributes = () => {
    const attrs = attributes.value.map(a => ({
        name: String(a.name || '').trim(),
        values: parseCsv(a.valuesText),
        dependsOn: String(a.dependsOn || '').trim(),
        rules: a.rules || {},
        rulesSelected: a.rulesSelected || {},
    })).filter(a => a.name && a.values.length > 0);
    if (attrs.length === 0) return;
    const nameToAttr = Object.fromEntries(attrs.map(a => [a.name, a]));
    const ordered = [];
    const visited = new Set();
    function visit(attr) {
        if (visited.has(attr.name)) return;
        if (attr.dependsOn && nameToAttr[attr.dependsOn]) visit(nameToAttr[attr.dependsOn]);
        visited.add(attr.name);
        ordered.push(attr);
    }
    attrs.forEach(visit);
    const results = [];
    function backtrack(index, chosen) {
        if (index === ordered.length) { results.push({ ...chosen }); return; }
        const attr = ordered[index];
        let allowed = attr.values;
        if (attr.dependsOn) {
            const parentVal = chosen[attr.dependsOn];
            if (parentVal != null) {
                const ruleRaw = attr.rulesSelected?.[parentVal] ?? attr.rules?.[parentVal] ?? [];
                const ruleVals = Array.isArray(ruleRaw) ? ruleRaw : parseCsv(ruleRaw);
                if (ruleVals.length > 0) allowed = attr.values.filter(v => ruleVals.includes(v));
            }
        }
        for (const val of allowed) {
            chosen[attr.name] = val;
            backtrack(index + 1, chosen);
        }
        delete chosen[attr.name];
    }
    backtrack(0, {});
    form.variant_attributes = attrs;
    form.variants = results.map(sel => {
        const parts = ordered.map(a => `${a.name}:${sel[a.name]}`);
        return { id: null, options_text: parts.join(', '), price: '', purchase_price: null, stock: 0, alert: null };
    });
};
// --- Lógica de Imágenes (sigue igual) ---
const currentImages = ref([...props.product.images]);
const markImageForDeletion = (image) => {
    form.images_to_delete.push(image.id);
    currentImages.value = currentImages.value.filter(img => img.id !== image.id);
};

// ===== Galería en modal (para más de 4 imágenes) =====
const showGalleryModal = ref(false);
const currentImageIndex = ref(0);
const isMobile = ref(false);
function updateIsMobile() { isMobile.value = window.innerWidth < 768; }
onMounted(async () => { 
    updateIsMobile(); 
    window.addEventListener('resize', updateIsMobile);
    // Preseleccionar la cadena de categorías del producto
    hydratePath();
    // Hidratar variant_options jerárquicas
    hydrateVariantParents();
    // Hidratar atributos desde la definición guardada; si no hay, caer a variantes (retrocompatibilidad)
    const usedSaved = hydrateAttributesFromSaved();
    if (!usedSaved) {
        hydrateAttributesFromVariants();
        hydrateDependenciesFromProduct();
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateIsMobile);
    stopBarcodeScanner();
});

// Manejar código escaneado
const handleBarcodeScanned = async (decodedText) => {
    playBeep();
    // Limpiar el código de barras
    const cleanedBarcode = decodedText.replace(/\s+/g, '').trim();

    if (scanningVariant.value) {
        // Asignar a la variante específica
        const { pIndex, cIndex } = scanningVariant.value;
        if (variantParents.value[pIndex] && variantParents.value[pIndex].children[cIndex]) {
            variantParents.value[pIndex].children[cIndex].barcode = cleanedBarcode;
        }
        scanningVariant.value = null;
    } else {
        // Asignar al producto principal
        form.barcode = cleanedBarcode;
        await nextTick();
    }

    scannedBarcode.value = cleanedBarcode;
    stopBarcodeScanner();
    showBarcodeSuccessModal.value = true;
};

// Iniciar escáner para variante
const startVariantBarcodeScanner = (pIndex, cIndex) => {
    scanningVariant.value = { pIndex, cIndex };
    startBarcodeScanner();
};

// Iniciar escáner de código de barras
const startBarcodeScanner = async () => {
    try {
        showBarcodeScanner.value = true;
        await nextTick();
        
        const { Html5Qrcode } = await import('html5-qrcode');
        const scannerElement = document.getElementById('barcode-scanner-edit');
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
                    await handleBarcodeScanned(decodedText);
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
                        await handleBarcodeScanned(decodedText);
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

// Detener escáner
const stopBarcodeScanner = async () => {
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

const visibleCount = computed(() => isMobile.value ? 1 : 4);
const visibleEditImages = computed(() => currentImages.value.slice(0, visibleCount.value));
const hiddenEditImages = computed(() => currentImages.value.slice(visibleCount.value));

function openGallery(startIndex = 0) {
    currentImageIndex.value = startIndex;
    showGalleryModal.value = true;
}
function closeGallery() { showGalleryModal.value = false; }
function prevGallery() {
    const n = currentImages.value.length; if (n === 0) return;
    currentImageIndex.value = (currentImageIndex.value - 1 + n) % n;
}
function nextGallery() {
    const n = currentImages.value.length; if (n === 0) return;
    currentImageIndex.value = (currentImageIndex.value + 1) % n;
}
function setGalleryIndex(i) {
    if (i < 0 || i >= currentImages.value.length) return;
    currentImageIndex.value = i;
}

// --- Lógica de Añadir/Quitar Variantes (ajustada) ---
// (Se elimina la edición manual de variantes en esta vista)
// --- FIN Lógica Variantes ---

// --- Lógica de Stock Total (sigue igual) ---
const totalQuantity = computed(() => {
    if (form.variants.length > 0) {
        let total = 0;
        form.variants.forEach(variant => {
            total += Number(variant.stock) || 0;
        });
        return total;
    }
    return Number(form.quantity) || 0; 
});

watch(totalQuantity, (newTotal) => {
    if (form.variants.length > 0) {
        form.quantity = newTotal;
    }
});
// --- FIN Lógica Stock ---

// Eliminado cálculo de stock mínimo

// Sincronizar precio detal con precio principal cuando se controla inventario
// (retail_price eliminado)

// (retail por variante eliminado)
const submit = () => {
    // Validar que se haya seleccionado una categoría hoja si hay subcategorías disponibles
    if (requiresSubcategory.value) {
        errorMessages.value = ['Debés seleccionar una subcategoría. La categoría principal tiene subcategorías disponibles y es obligatorio elegir una de ellas.'];
        showErrors.value = true;
        // Hacer scroll al campo de categorías
        nextTick(() => {
            const firstCategorySelect = document.querySelector('.space-y-3 select');
            if (firstCategorySelect) {
                firstCategorySelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstCategorySelect.focus();
            }
        });
        return;
    }
    
    // Preparar variant_options para el nuevo sistema jerárquico
    form.variant_options = prepareVariantOptions();
    
    // Construir variant_attributes desde variantParents para conservar dependencias
    // IMPORTANTE: Priorizar el nuevo sistema (variantParents) sobre el antiguo (attributes)
    const variantAttrs = buildVariantAttributes();
    if (variantAttrs.length > 0) {
        // Usar el nuevo sistema de variantParents - IMPORTANTE: NO sobrescribir después
        form.variant_attributes = variantAttrs.map(attr => ({
            name: attr.name,
            values: attr.values,
            dependsOn: attr.dependsOn || '',
            rulesSelected: attr.rulesSelected || {},
            rules: attr.rules || {},
        }));
        // NO continuar con el sistema antiguo si ya hay variantAttrs
    } else {
        // Solo usar el sistema antiguo si no hay variantParents
        try {
            const attrs = attributes.value.map(a => ({
                name: String(a.name || '').trim(),
                values: parseCsv(a.valuesText),
                dependsOn: String(a.dependsOn || '').trim(),
                rules: a.rules || {},
                rulesSelected: a.rulesSelected || {},
            })).filter(a => a.name && a.values.length > 0);
            form.variant_attributes = attrs;
            // Regenerar variantes para mantener coherencia con la definición actual
            if (attrs.length > 0) {
                const nameToAttr = Object.fromEntries(attrs.map(a => [a.name, a]));
                const ordered = [];
                const visited = new Set();
                function visit(attr) { if (visited.has(attr.name)) return; if (attr.dependsOn && nameToAttr[attr.dependsOn]) visit(nameToAttr[attr.dependsOn]); visited.add(attr.name); ordered.push(attr); }
                attrs.forEach(visit);
                const results = [];
                function backtrack(index, chosen) {
                    if (index === ordered.length) { results.push({ ...chosen }); return; }
                    const attr = ordered[index];
                    let allowed = attr.values;
                    if (attr.dependsOn) {
                        const parentVal = chosen[attr.dependsOn];
                        if (parentVal != null) {
                            const ruleRaw = attr.rulesSelected?.[parentVal] ?? attr.rules?.[parentVal] ?? [];
                            const ruleVals = Array.isArray(ruleRaw) ? ruleRaw : parseCsv(ruleRaw);
                            if (ruleVals.length > 0) allowed = attr.values.filter(v => ruleVals.includes(v));
                        }
                    }
                    for (const val of allowed) { chosen[attr.name] = val; backtrack(index + 1, chosen); }
                    delete chosen[attr.name];
                }
                backtrack(0, {});
                form.variants = results.map(sel => ({
                    id: null,
                    options_text: ordered.map(a => `${a.name}:${sel[a.name]}`).join(', '),
                    price: '', purchase_price: null, stock: 0, alert: null,
                }));
            } else {
                // Si no hay atributos válidos, aseguramos enviar sin variantes
                form.variants = [];
            }
        } catch (_) {
            // Si falla, asegurar que variant_attributes esté vacío
            form.variant_attributes = [];
        }
    }
    confirmingSave.value = true;
};

const closeSaveModal = () => {
    confirmingSave.value = false;
};

const confirmSave = () => {
    // Asegurar que el barcode siempre se envíe, incluso si solo cambió ese campo
    // Esto soluciona el problema donde el formulario no detecta cambios cuando solo se modifica el barcode
    // Normalizar el barcode y forzar actualización en el formulario
    const originalBarcode = props.product.barcode ?? '';
    const normalizedBarcode = form.barcode !== undefined && form.barcode !== null 
        ? String(form.barcode).trim() 
        : '';
    
    // Actualizar directamente en el formulario para que Inertia detecte el cambio
    form.barcode = normalizedBarcode;
    
    // Verificar si hay un cambio real en el barcode
    const barcodeChanged = normalizedBarcode !== originalBarcode;
    
    // Si cambió el barcode, limpiar errores para asegurar que se detecte el cambio
    if (barcodeChanged) {
        // Forzar que el formulario sepa que hay un cambio
        form.clearErrors('barcode');
    }
    
    // Usar form.transform() para agregar archivos dinámicos ANTES de enviar
    // Esto hace que Inertia los detecte automáticamente
    form.transform((data) => {
        // Asegurar que barcode esté presente en los datos transformados
        data.barcode = normalizedBarcode;
        
        // Agregar archivos de variantes al objeto de datos
        variantParents.value.forEach((parent, parentIndex) => {
            if (parent.children && parent.children.length > 0) {
                parent.children.forEach((child, childIndex) => {
                    if (child.image instanceof File) {
                        const key = `variant_option_${parentIndex}_${childIndex}`;
                        data[key] = child.image;
                    }
                });
            }
        });
        return data;
    });

    // Verificar si hay archivos para enviar
    const variantImageKeys = variantParents.value.flatMap((parent, parentIndex) =>
        (parent.children || []).map((child, childIndex) => 
            child.image instanceof File ? `variant_option_${parentIndex}_${childIndex}` : null
        ).filter(Boolean)
    );
    const hasGalleryFiles = form.new_gallery_files && form.new_gallery_files.length > 0;
    const hasFiles = variantImageKeys.length > 0 || hasGalleryFiles;

    // Opciones comunes para el envío
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            // Mostrar mensaje de éxito
            showSaved.value = true;
            successMessage.value = 'Producto actualizado exitosamente';
            confirmingSave.value = false;
        },
        onError: async () => {
            // Construir mensajes legibles
            const msgs = [];
            for (const [key, val] of Object.entries(form.errors)) {
                if (key.startsWith('new_gallery_files.') && String(val).toLowerCase().includes('image')) {
                    msgs.push('Una o más imágenes nuevas superan el tamaño máximo permitido (2 MB) o no son válidas.');
                } else {
                    msgs.push(String(val));
                }
            }
            errorMessages.value = msgs;
            showErrors.value = msgs.length > 0;
            confirmingSave.value = false;
        },
        onFinish: () => { 
            confirmingSave.value = false;
        }
    };
    
    // SIEMPRE usar form.post() cuando hay archivos (Inertia detectará _method: 'PUT' y los archivos)
    // _method: 'PUT' ya está en el form desde el inicio
    if (hasFiles) {
        form.post(route('admin.products.update', props.product.id), options);
    } else {
        // Sin archivos, usar PUT directamente
        form.put(route('admin.products.update', props.product.id), options);
    }
};
</script>

<template>
    <Head title="Editar Producto" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Producto: {{ form.name }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <div>
                                    <div class="mb-4">
                                        <label for="name" class="block font-medium text-sm text-gray-700">Nombre</label>
                                        <input id="name" v-model="form.name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                    </div>
                                    <div class="mb-4">
                                        <label for="barcode" class="block font-medium text-sm text-gray-700 mb-1">Código de Barras (Opcional)</label>
                                        <div class="flex gap-2">
                                            <input 
                                                id="barcode" 
                                                v-model="form.barcode" 
                                                type="text" 
                                                class="flex-1 block mt-1 rounded-md shadow-sm border-gray-300" 
                                                placeholder="Ej: 1234567890123"
                                                :disabled="hasSpecificBarcode"
                                                :class="{ 'bg-gray-100 cursor-not-allowed': hasSpecificBarcode }"
                                                :title="hasSpecificBarcode ? 'Deshabilitado porque hay variantes con código de barras propio' : ''"
                                            >
                                            <button
                                                type="button"
                                                @click="startBarcodeScanner"
                                                class="px-4 py-2 mt-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center gap-2 whitespace-nowrap"
                                                title="Escanear código de barras"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                                </svg>
                                                Escanear
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label for="price" class="block font-medium text-sm text-gray-700">Precio (Principal)</label>
                                        <input id="price" v-model="form.price" type="number" step="0.01" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                    </div>

                                    <label class="inline-flex items-center gap-2 mb-3 select-none">
                                        <input type="checkbox" v-model="form.track_inventory" class="rounded">
                                        <span class="font-semibold">Controlar inventario</span>
                                    </label>
                                    <div v-if="form.track_inventory" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                         <div>
                                             <label for="quantity" class="block font-medium text-sm text-gray-700">Inventario (Total)</label>
                                             <input 
                                                 id="quantity" 
                                                 v-model="form.quantity" 
                                                 type="number" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                                 :class="{ 'bg-gray-100': !form.track_inventory || hasSpecificInventory }"
                                                 :disabled="!form.track_inventory || hasSpecificInventory" 
                                                 :required="form.track_inventory && !hasSpecificInventory"
                                                 title="Stock si no hay variantes. Con variantes, se suma automáticamente.">
                                         </div>
                                          <div>
                                             <label for="alert_general" class="block font-medium text-sm text-gray-700">Alerta (Opcional)</label>
                                             <input 
                                                 id="alert_general" 
                                                 v-model="form.alert" 
                                                 type="number" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                 :class="{ 'bg-gray-100': !form.track_inventory || hasSpecificInventory }"
                                                 :disabled="!form.track_inventory || hasSpecificInventory"
                                                 title="Se considera bajo stock cuando cantidad ≤ alerta."
                                             />
                                         </div>
                                         <div>
                                             <label for="purchase_price" class="block font-medium text-sm text-gray-700">Precio de compra</label>
                                             <input 
                                                 id="purchase_price" 
                                                 v-model="form.purchase_price" 
                                                 type="number" 
                                                 step="0.01" 
                                                 class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                                 :class="{ 'bg-gray-100': !form.track_inventory || hasSpecificInventory }"
                                                 :disabled="!form.track_inventory || hasSpecificInventory"
                                             />
                                         </div>
                                         
                                         
                                     </div>
                                    
                                    <div class="mb-2">
                                        <label class="block font-medium text-sm text-gray-700">Categorías</label>
                                        <p v-if="requiresSubcategory" class="mt-1 text-sm text-amber-600 font-medium">
                                            ⚠️ Es obligatorio seleccionar una subcategoría
                                        </p>
                                    </div>
                                    <div class="space-y-3">
                                        <div v-for="(lvl, idx) in levels" :key="idx" class="mb-1">
                                            <select 
                                                class="block w-full rounded-md shadow-sm border-gray-300"
                                                :class="{ 'border-red-500': requiresSubcategory && idx === levels.length - 1 && !lvl.selected }"
                                                v-model="lvl.selected"
                                                @change="onSelectAtLevel(idx)"
                                                :required="idx === levels.length - 1 && lvl.options.length > 0"
                                            >
                                                <option :value="null">Seleccione una categoría</option>
                                                <option v-for="opt in lvl.options" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <p v-if="requiresSubcategory" class="mt-1 text-sm text-red-600">
                                        Debés seleccionar una subcategoría. La categoría principal tiene subcategorías disponibles.
                                    </p>
                                </div>
                                
                                <div>
                                    <div class="mb-4">
                                        <label for="short_description" class="block font-medium text-sm text-gray-700">Descripción Corta</label>
                                        <textarea id="short_description" v-model="form.short_description" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" rows="3"></textarea>
                                    </div>
                                    <div class="mb-4">
                                        <label for="long_description" class="block font-medium text-sm text-gray-700">Descripción Larga</label>
                                        <textarea id="long_description" v-model="form.long_description" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" rows="5"></textarea>
                                    </div>
                                    <div class="mb-4">
                                        <label for="specifications" class="block font-medium text-sm text-gray-700">Especificaciones (separadas por comas)</label>
                                        <input id="specifications" v-model="form.specifications" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300">
                                    </div>
                                    <div class="mb-4">
                                        <label for="meta_keywords" class="block font-medium text-sm text-gray-700">Palabras Clave SEO (separadas por comas)</label>
                                        <input id="meta_keywords" v-model="form.meta_keywords" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" placeholder="palabra1, palabra2, palabra3">
                                        <p class="mt-1 text-xs text-gray-500">Estas palabras clave ayudan a mejorar el SEO del producto. No son visibles para los clientes.</p>
                                    </div>
                                </div>
                            </div>
                            
                        <!-- Variantes Jerárquicas (Nuevo sistema) -->
                            <div class="md:col-span-2 mt-6 border-t pt-6">
                            <!-- Contenedor estilo categorías -->
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6 text-gray-900">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-medium">Variantes del Producto</h3>
                                        <div class="flex items-center gap-2">
                                            <button 
                                                type="button" 
                                                class="w-7 h-7 inline-flex items-center justify-center rounded border border-gray-300 hover:bg-gray-100 text-gray-700" 
                                                title="Expandir todo" 
                                                @click="() => { variantParents.forEach((_, idx) => expandedVariants[idx] = true); }"
                                            >
                                                +
                                            </button>
                                            <button 
                                                type="button" 
                                                class="w-7 h-7 inline-flex items-center justify-center rounded border border-gray-300 hover:bg-gray-100 text-gray-700" 
                                                title="Colapsar todo" 
                                                @click="() => { variantParents.forEach((_, idx) => expandedVariants[idx] = false); }"
                                            >
                                                −
                                            </button>
                                        </div>
                                        </div>
                                    
                                    <div class="mt-4 border rounded-lg divide-y">
                                        <p v-if="variantParents.length === 0" class="text-sm text-gray-500 p-3">
                                            Aún no hay variantes principales. Agregá una usando el botón de abajo.
                                        </p>
                                        
                                            <div v-for="(parent, parentIndex) in variantParents" :key="`parent-${parentIndex}`" class="p-3 hover:bg-gray-50">
                                                <!-- Fila principal de la variante -->
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <!-- Botón expandir/colapsar -->
                                                            <button 
                                                                type="button" 
                                                                class="w-6 h-6 flex-shrink-0 inline-flex items-center justify-center rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100" 
                                                                :title="(expandedVariants[parentIndex] ?? false) ? 'Colapsar opciones' : 'Expandir opciones'" 
                                                                @click="toggleExpandVariant(parentIndex)"
                                                            >
                                                                <span v-if="!(expandedVariants[parentIndex] ?? false)">&gt;</span>
                                                                <span v-else>v</span>
                                                            </button>
                                                            
                                                            <!-- Input del nombre de la variante principal -->
                                                            <input 
                                                                v-model="parent.name" 
                                                                type="text" 
                                                                placeholder="Ej: Color, Talla, Material" 
                                                                class="flex-1 min-w-0 font-medium border-0 bg-transparent focus:bg-white focus:border focus:border-gray-300 rounded px-2 py-1 focus:outline-none"
                                                            />
                                                            
                                                            <!-- Badge con contador de hijos -->
                                                            <span v-if="parent.children && parent.children.length > 0" class="flex-shrink-0 text-xs bg-gray-100 text-gray-700 rounded px-2 py-0.5 align-middle">
                                                                ({{ parent.children.length }})
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Botones de acción -->
                                                    <div class="flex items-center gap-2 flex-shrink-0">

                                                        
                                                        <!-- Botón agregar opción -->
                                                        <button 
                                                            type="button" 
                                                            class="w-8 h-8 inline-flex items-center justify-center rounded border border-green-500 text-green-600 hover:bg-green-50" 
                                                            title="Añadir opción" 
                                                            @click="addVariantChild(parentIndex)"
                                                        >
                                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                                <rect x="4" y="3" width="11" height="15" rx="2" ry="2" stroke-width="1.5" />
                                                                <circle cx="18" cy="18" r="3" stroke-width="1.5" />
                                                                <path stroke-linecap="round" stroke-width="1.5" d="M18 16.5v3M16.5 18h3" />
                                                            </svg>
                                                        </button>
                                                        
                                                        <!-- Botón eliminar variante -->
                                                        <button 
                                                            type="button" 
                                                            @click="removeVariantParent(parentIndex)" 
                                                            class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-red-600" 
                                                            title="Eliminar"
                                                        >
                                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Dependencias (si está expandido) -->
                                                <div v-if="parent.__showDependency" class="mt-2 pl-2 bg-gray-50 rounded-md p-2">
                                                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                                                        <div class="md:col-span-2">
                                                            <label class="block text-xs font-medium text-gray-700 mb-1">Depende de</label>
                                                            <select v-model="parent.dependsOn" class="block w-full text-sm border-gray-300 rounded-md shadow-sm">
                                                                <option value="">(sin dependencia)</option>
                                                                <option v-for="(other, oi) in variantParents" :key="`dep-${parentIndex}-${oi}`" :value="other.name" :disabled="other === parent || !other.name">{{ other.name || '(sin nombre)' }}</option>
                                                            </select>
                                                        </div>
                                                        <div v-if="parent.dependsOn" class="md:col-span-3">
                                                            <label class="block text-xs font-medium text-gray-700 mb-1">Permitir valores según el valor del padre</label>
                                                            <div class="mt-2 space-y-2">
                                                                <div v-for="pv in (variantParents.find(p => p.name === parent.dependsOn)?.children || []).map(c => c.name)" :key="`pv-${parentIndex}-${pv}`" class="border rounded p-2 bg-white">
                                                                    <div class="text-xs font-semibold text-gray-700 mb-1">{{ parent.dependsOn }} = <strong>{{ pv }}</strong></div>
                                                                    <div class="flex flex-wrap gap-2">
                                                                        <label v-for="cv in (parent.children || []).map(c => c.name)" :key="`cv-${parentIndex}-${pv}-${cv}`" class="inline-flex items-center gap-1 text-xs">
                                                                            <input type="checkbox" :checked="isRuleChecked(parent, pv, cv)" @change="toggleRule(parent, pv, cv, $event)" class="rounded">
                                                                            <span>{{ cv }}</span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Opciones hijas (expandidas) -->
                                                <div v-if="expandedVariants[parentIndex] ?? false" class="mt-2 pl-4 border-l">
                                                    <div v-for="(child, childIndex) in parent.children" :key="`child-${parentIndex}-${childIndex}`" class="py-2 flex justify-between items-start">
                                                    <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-3">
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Nombre</label>
                                                                <input 
                                                                    v-model="child.name" 
                                                                    type="text" 
                                                                    placeholder="Ej: Rojo, Verde" 
                                                                    class="block w-full text-sm border-gray-300 rounded-md shadow-sm"
                                                                />
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Precio (opcional)</label>
                                                                <input 
                                                                    v-model="child.price" 
                                                                    type="number" 
                                                                    step="0.01"
                                                                    placeholder="Usa precio principal" 
                                                                    class="block w-full text-sm border-gray-300 rounded-md shadow-sm"
                                                                    :disabled="!canAddPriceToVariant(parentIndex)"
                                                                    :class="{ 'bg-gray-100 cursor-not-allowed': !canAddPriceToVariant(parentIndex) }"
                                                                    @input="handlePriceInput(parentIndex, childIndex, $event)"
                                                                />
                                                                <p v-if="!canAddPriceToVariant(parentIndex)" class="mt-1 text-xs text-amber-600">
                                                                    Solo puedes agregar precios a una variante principal
                                                                </p>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <input 
                                                                    type="file" 
                                                                    accept="image/*"
                                                                    @change="onVariantChildImageChange(parentIndex, childIndex, $event)"
                                                                    class="hidden"
                                                                    :id="`variant-image-input-${parentIndex}-${childIndex}`"
                                                                    :disabled="!canAddImageToVariant(parentIndex)"
                                                                />
                                                                <div v-if="child.imagePreview" class="relative">
                                                                    <img :src="child.imagePreview" alt="Preview" class="w-10 h-10 object-cover rounded border">
                                                                    <button 
                                                                        type="button"
                                                                        @click.stop="removeVariantChildImage(parentIndex, childIndex)"
                                                                        class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-xs hover:bg-red-600"
                                                                    >
                                                                        ×
                                                                    </button>
                                                                </div>
                                                                <label 
                                                                    v-else
                                                                    :for="`variant-image-input-${parentIndex}-${childIndex}`"
                                                                    class="inline-flex items-center justify-center w-10 h-10 border-2 border-dashed border-gray-300 rounded hover:border-blue-400 hover:bg-blue-50 transition"
                                                                    :class="{ 'cursor-pointer': canAddImageToVariant(parentIndex), 'cursor-not-allowed opacity-50': !canAddImageToVariant(parentIndex) }"
                                                                    :title="canAddImageToVariant(parentIndex) ? 'Agregar foto' : 'Solo puedes agregar imágenes a una variante principal'"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-gray-400" fill="currentColor">
                                                                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                                                    </svg>
                                                                </label>
                                                                <p v-if="!canAddImageToVariant(parentIndex)" class="mt-1 text-xs text-amber-600">
                                                                    Solo puedes agregar imágenes a una variante principal
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-600 mb-1">Código de Barras</label>
                                                                <div class="flex gap-1">
                                                                    <input 
                                                                        v-model="child.barcode" 
                                                                        type="text" 
                                                                        placeholder="Ej: 770..." 
                                                                        class="block w-full text-sm border-gray-300 rounded-md shadow-sm"
                                                                        :disabled="!canAddBarcodeToVariant(parentIndex)"
                                                                        :class="{ 'bg-gray-100 cursor-not-allowed': !canAddBarcodeToVariant(parentIndex) }"
                                                                    />
                                                                    <button
                                                                        type="button"
                                                                        @click="startVariantBarcodeScanner(parentIndex, childIndex)"
                                                                        class="p-2 bg-blue-100 text-blue-600 rounded hover:bg-blue-200"
                                                                        title="Escanear"
                                                                        :disabled="!canAddBarcodeToVariant(parentIndex)"
                                                                        :class="{ 'opacity-50 cursor-not-allowed': !canAddBarcodeToVariant(parentIndex) }"
                                                                    >
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                                                        </svg>
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        @click="openInventoryModal(child, parentIndex, childIndex)"
                                                                        class="p-2 bg-purple-100 text-purple-600 rounded hover:bg-purple-200"
                                                                        title="Inventario"
                                                                        :disabled="!form.track_inventory || !canAddInventoryToVariant(parentIndex)"
                                                                        :class="{ 'opacity-50 cursor-not-allowed': !form.track_inventory || !canAddInventoryToVariant(parentIndex) }"
                                                                    >
                                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                                <p v-if="!canAddBarcodeToVariant(parentIndex)" class="mt-1 text-xs text-amber-600">
                                                                    Solo una variante principal puede tener códigos
                                                                </p>
                                                                <p v-if="form.track_inventory && !canAddInventoryToVariant(parentIndex)" class="mt-1 text-xs text-amber-600">
                                                                    Solo una variante principal puede tener inventario
                                                                </p>
                                                            </div>

                                                        </div>
                                                        <div class="flex items-center gap-2 ml-2">
                                                            <button 
                                                                type="button"
                                                                @click="removeVariantChild(parentIndex, childIndex)"
                                                                class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-red-600" 
                                                                title="Eliminar"
                                                            >
                                                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M16.5 4.5V6h3.75a.75.75 0 010 1.5H3.75A.75.75 0 013 6h3.75V4.5A2.25 2.25 0 019 2.25h6A2.25 2.25 0 0117.25 4.5zM5.625 7.5h12.75l-.701 10.518A2.25 2.25 0 0115.43 20.25H8.57a2.25 2.25 0 01-2.244-2.232L5.625 7.5z" clip-rule="evenodd"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Formulario para agregar nueva variante principal (Restringido a 1 sola categoría principal para simplificar códigos de barras) -->
                                        <form v-if="variantParents.length < 1" @submit.prevent="addVariantParent" class="mt-6 border-t pt-4">
                                            <label class="block font-medium text-sm text-gray-700">Añadir Nueva Variante Principal</label>
                                            <div class="mt-2 flex items-center gap-2">
                                                <input 
                                                    type="text" 
                                                    v-model="newVariantName" 
                                                    class="block w-full rounded-md shadow-sm border-gray-300" 
                                                    placeholder="Nombre de la nueva variante principal"
                                                    @keyup.enter="addVariantParent"
                                                />
                                                <button 
                                                    type="submit" 
                                                    class="w-8 h-8 inline-flex items-center justify-center rounded bg-green-500 text-white hover:bg-green-600" 
                                                    title="Añadir"
                                                >
                                                    ✔
                                                </button>
                                                <button 
                                                    type="button" 
                                                    class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-200 text-gray-800 hover:bg-gray-300" 
                                                    @click="newVariantName = ''" 
                                                    title="Cancelar"
                                                >
                                                    ✖
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sección de imágenes al final -->
                            <div class="md:col-span-2 mt-6 border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Imágenes Extra de la Galería</h3>
                                <p class="text-sm text-gray-600 mb-4">
                                    Estas imágenes se agregarán a la galería general del producto. Las imágenes de las variantes se muestran cuando se selecciona esa opción.
                                </p>
                                 <div class="p-4 border rounded-md bg-gray-50 col-span-2">
                                     <div class="flex items-center justify-between mb-2">
                                         <label class="block font-medium text-sm text-gray-700">Imágenes actuales</label>
                                        <span class="text-xs text-gray-500">{{ currentImages.length }} imagen(es)</span>
                                    </div>
                                    <div v-if="currentImages.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                        <div v-for="(image, idx) in visibleEditImages" :key="image.id" class="relative rounded-lg overflow-hidden border bg-white shadow-sm hover:shadow-md transition group cursor-pointer" @click="openGallery(idx)">
                                            <img :src="image.path" class="w-full h-full aspect-[4/3] object-cover" alt="Imagen del producto">
                                            <button @click.stop.prevent="markImageForDeletion(image)" type="button" class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-7 h-7 flex items-center justify-center shadow-md hover:bg-red-700">
                                                &times;
                                            </button>
                                        </div>
                                        <div v-if="hiddenEditImages.length > 0" class="relative rounded-lg overflow-hidden border bg-white shadow-sm hover:shadow-md transition group cursor-pointer flex items-center justify-center text-gray-800 font-bold text-base h-full min-h-[90px]" @click="openGallery(visibleCount)">
                                            +{{ hiddenEditImages.length }}
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-500 mt-2">Este producto no tiene imágenes.</p>
                                </div>
                                <div class="mt-4 col-span-2">
                                    <label for="new_gallery_files" class="block font-medium text-sm text-gray-700">Añadir más imágenes</label>
                                    <input 
                                        id="new_gallery_files" 
                                        @input="onNewGalleryInput($event)" 
                                        type="file" 
                                        multiple 
                                        class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                    >
                                    <p class="mt-1 text-xs text-gray-500">Formatos de imagen y hasta 2 MB por archivo.</p>
                                </div>
                            </div>

                            <!-- Modal Galería para ver/eliminar -->
                            <div v-if="showGalleryModal" class="fixed inset-0 bg-black/50 z-[1000] flex items-center justify-center p-4" style="pointer-events: auto;" @click.self="closeGallery">
                                <div class="bg-white rounded-lg p-4 w-full max-w-5xl max-h-[85vh] overflow-hidden relative" @click.stop>
                                    <button type="button" class="absolute top-3 right-3 bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center z-30" @click.stop.prevent="closeGallery" @mousedown.stop.prevent>×</button>
                                    <div class="relative flex items-center justify-center bg-gray-50 border rounded-lg h-[60vh] min-h-[320px] mb-3 select-none">
                                        <button class="absolute left-2 bg-white/90 border rounded-full w-9 h-9 flex items-center justify-center z-20" @click.stop.prevent="prevGallery" @mousedown.stop.prevent>‹</button>
                                        <img :src="currentImages[currentImageIndex]?.path" class="max-w-full max-h-full object-contain" alt="Imagen">
                                        <button class="absolute right-2 bg-white/90 border rounded-full w-9 h-9 flex items-center justify-center z-20" @click.stop.prevent="nextGallery" @mousedown.stop.prevent>›</button>
                                        <button v-if="currentImages[currentImageIndex]" class="absolute top-2 right-12 bg-red-600 text-white rounded px-2 py-1 text-xs z-20" type="button" @click.stop.prevent="markImageForDeletion(currentImages[currentImageIndex])" @mousedown.stop.prevent>Eliminar</button>
                                    </div>
                                    <div class="flex items-center gap-2 overflow-x-auto py-2">
                                        <div v-for="(img, i) in currentImages" :key="img.id" class="border rounded p-1 cursor-pointer flex-shrink-0" :class="{ 'ring-2 ring-blue-500': i === currentImageIndex }" @click="setGalleryIndex(i)">
                                            <img :src="img.path" class="w-20 h-20 object-cover rounded" alt="thumb">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-2 flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm9-1.5V7h4.5L13 2.5z"/><path d="M8 13h8v2H8zM8 9h5v2H8z"/></svg>
                                    <span>Guardar</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="confirmingSave" @close="closeSaveModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                ¿Deseás guardar los cambios del producto?
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Se actualizará la información y variantes del producto.
            </p>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeSaveModal">Cancelar</SecondaryButton>
                <DangerButton class="ms-3" @click="confirmSave" :disabled="form.processing">
                    Guardar cambios
                </DangerButton>
            </div>
        </div>
    </Modal>

    <AlertModal
        :show="showSaved"
        type="success"
        title="Producto actualizado"
        primary-text="Entendido"
        @primary="showSaved=false"
        @close="showSaved=false"
    />

    <AlertModal
        :show="showErrors"
        type="error"
        title="No pudimos actualizar el producto"
        message="Revisá los siguientes puntos:"
        :messages="errorMessages"
        primary-text="Entendido"
        @primary="showErrors=false"
        @close="showErrors=false"
    />

    <!-- Modal informativo sobre precios múltiples en variantes -->
    <AlertModal
        :show="showPriceInfoModal"
        type="warning"
        title="Solo una Variante Puede Tener Precios"
        :messages="priceModalMessages"
        primary-text="Entendido"
        @primary="showPriceInfoModal = false"
        @close="showPriceInfoModal = false"
    />
    
    <!-- Modal informativo sobre imágenes múltiples en variantes -->
    <AlertModal
        :show="showImageInfoModal"
        type="warning"
        title="Solo una Variante Puede Tener Imágenes"
        :messages="imageModalMessages"
        primary-text="Entendido"
        @primary="showImageInfoModal = false"
        @close="showImageInfoModal = false"
    />

    <!-- Modal de escáner de código de barras -->
    <Modal :show="showBarcodeScanner" @close="stopBarcodeScanner">
        <div class="p-6">
            <h2 class="text-lg font-semibold mb-4">Escanear Código de Barras</h2>
            
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-4">
                    Apunta la cámara hacia el código de barras. El código se detectará automáticamente.
                </p>
                <div id="barcode-scanner-edit" class="w-full rounded-lg overflow-hidden" style="min-height: 300px;"></div>
            </div>

            <div class="flex gap-3">
                <SecondaryButton @click="stopBarcodeScanner">Cerrar</SecondaryButton>
            </div>
        </div>
    </Modal>

    <!-- Modal de éxito al escanear código de barras -->
    <Modal :show="showBarcodeSuccessModal" @close="showBarcodeSuccessModal = false">
        <div class="p-6">
            <h2 class="text-lg font-semibold mb-4 text-green-600">¡Código de barras escaneado!</h2>
            
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">
                    Código escaneado:
                </p>
                <p class="text-lg font-semibold text-gray-900">
                    {{ scannedBarcode }}
                </p>
            </div>

            <div class="flex gap-3">
                <PrimaryButton @click="showBarcodeSuccessModal = false">Aceptar</PrimaryButton>
            </div>
        </div>
    </Modal>

    <VariantInventoryModal 
        :show="showInventoryModal" 
        :variant-name="currentInventoryVariant?.name"
        :initial-data="currentInventoryVariant"
        @close="showInventoryModal = false"
        @save="saveInventoryData"
    />
</template>