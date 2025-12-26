<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { nextTick, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    categories: Array, 
});

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const showSaved = ref(page?.props?.flash?.success ? true : false);
const showErrors = ref(false);
const errorMessages = ref([]);

// Escáner de código de barras
const showBarcodeScanner = ref(false);
const html5QrCode = ref(null);
const showBarcodeSuccessModal = ref(false);
const scannedBarcode = ref('');

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

// Iniciar escáner de código de barras
const startBarcodeScanner = async () => {
    try {
        showBarcodeScanner.value = true;
        await nextTick();
        
        const { Html5Qrcode } = await import('html5-qrcode');
        const scannerElement = document.getElementById('barcode-scanner-create');
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
                (decodedText) => {
                    playBeep();
                    form.barcode = decodedText;
                    scannedBarcode.value = decodedText;
                    stopBarcodeScanner();
                    showBarcodeSuccessModal.value = true;
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
                    (decodedText) => {
                        playBeep();
                        form.barcode = decodedText;
                        scannedBarcode.value = decodedText;
                        stopBarcodeScanner();
                        showBarcodeSuccessModal.value = true;
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

// Limpiar al desmontar
onBeforeUnmount(() => {
    stopBarcodeScanner();
});


// Validación previa en el navegador
const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2 MB
const onGalleryInput = (event) => {
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
        // Limpiar selección para evitar enviar archivos inválidos
        if (event?.target) event.target.value = '';
        form.gallery_files = [];
        return;
    }
    form.gallery_files = validFiles;
};

// --- Selects en cascada multi-nivel ---
const levels = ref([ { options: props.categories, selected: null } ]);

const addLevel = () => {
    levels.value.push({ options: [], selected: null });
};
const removeDeeperLevels = (levelIndex) => {
    levels.value.splice(levelIndex + 1);
};

const onSelectAtLevel = async (levelIndex) => {
    const selectedId = levels.value[levelIndex].selected;
    // Reset niveles inferiores
    removeDeeperLevels(levelIndex);
    form.category_id = selectedId; // por defecto, si no hay hijos, esto será la categoría elegida
    if (!selectedId) return;
    // Cargar hijos
    try {
        const response = await window.axios.get(route('admin.categories.children', selectedId));
        const children = Array.isArray(response.data?.data) ? response.data.data : [];
        if (children.length > 0) {
            // Añadir nuevo nivel con los hijos
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
    if (levels.value.length > 1) {
        const lastLevel = levels.value[levels.value.length - 1];
        return lastLevel.options.length > 0 && !lastLevel.selected;
    }
    return false;
});

const form = useForm({
    name: '',
    barcode: '',
    price: '',
    purchase_price: null,
    // wholesale_price eliminado
    retail_price: null,
    track_inventory: true,
    quantity: 0,
    alert: null,
    category_id: null, 
    short_description: '',
    long_description: '',
    specifications: '',
    meta_keywords: '',
    gallery_files: [],
    variant_options: [], // Nueva estructura jerárquica
    variants: [], // Mantener para retrocompatibilidad
    variant_attributes: [],
});

// --- Lógica de Variantes Jerárquicas (Nuevo sistema) ---
const variantParents = ref([]); // Variantes principales (ej: "Color", "Talla")
const newVariantName = ref(''); // Nombre para el formulario de nueva variante
// Estados para expandir/colapsar variantes (solo visual)
const expandedVariants = ref({});

const toggleExpandVariant = (parentIndex) => {
    expandedVariants.value[parentIndex] = !(expandedVariants.value[parentIndex] ?? false);
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
    
    // Validación de imagen (igual que onGalleryInput)
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
        return;
    }
    
    variantParents.value[parentIndex].children[childIndex].image = file;
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
};

// Preparar datos de variant_options para enviar al backend
const prepareVariantOptions = () => {
    const options = [];
    variantParents.value.forEach((parent, parentIndex) => {
        if (!parent.name.trim()) return;
        
        // Crear el padre
        const parentOption = {
            name: parent.name.trim(),
            parent_id: null,
            price: null,
            image_path: null,
            order: parentIndex,
            children: [],
        };
        
        // Agregar hijos
        if (parent.children && parent.children.length > 0) {
            parent.children.forEach((child, childIndex) => {
                if (!child.name.trim()) return;
                
                parentOption.children.push({
                    name: child.name.trim(),
                    parent_id: null, // Se establecerá en el backend
                    price: child.price ? parseFloat(child.price) : null,
                    image_file_key: child.image ? `variant_option_${parentIndex}_${childIndex}` : null,
                    order: childIndex,
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

// Obtener la primera variante principal que tiene precios
const firstPricedVariant = computed(() => {
    return variantParents.value.find(parent => {
        if (!parent.children || parent.children.length === 0) return false;
        return parent.children.some(child => child.price && child.price !== '' && child.price != null);
    });
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

// Sincronizar precio detal con precio principal cuando se controla inventario
watch(() => form.track_inventory, (active) => {
    if (active) {
        form.retail_price = form.price;
    }
});
watch(() => form.price, (newPrice) => {
    if (form.track_inventory) {
        form.retail_price = newPrice;
    }
});

// Builder de Atributos (nombre + valores CSV) -> genera variantes
const attributes = ref([{ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }]);
const addAttribute = () => { attributes.value.push({ name: '', valuesText: '', dependsOn: '', rules: {}, rulesSelected: {} }); };
const removeAttribute = (idx) => { attributes.value.splice(idx, 1); };
const parseCsv = (text) => String(text || '').split(',').map(s => s.trim()).filter(Boolean);
const cartesian = (arrays) => arrays.reduce((a, b) => a.flatMap(x => b.map(y => x.concat([y]))), [[]]);
// Helpers para selección múltiple segura en reglas (funciona para attributes y variantParents)
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

// Alternar UI de dependencia y limpiar estado si se oculta (funciona para attributes y variantParents)
function toggleDependencyUi(attr) {
    attr.__showDependency = !attr.__showDependency;
    if (!attr.__showDependency) {
        attr.dependsOn = '';
        attr.rulesSelected = {};
        attr.rules = {};
    }
}
const generateVariantsFromAttributes = () => {
    const attrs = attributes.value.map(a => ({
        name: String(a.name || '').trim(),
        values: parseCsv(a.valuesText),
        dependsOn: String(a.dependsOn || '').trim(),
        rules: a.rules || {}, // { parentValue: 'a,c,d' }
        rulesSelected: a.rulesSelected || {},
    })).filter(a => a.name && a.values.length > 0);
    if (attrs.length === 0) return;

    // Mapa rápido por nombre
    const nameToAttr = Object.fromEntries(attrs.map(a => [a.name, a]));

    // Ordenar para que todo hijo quede después de su padre
    const ordered = [];
    const visited = new Set();
    function visit(attr) {
        if (visited.has(attr.name)) return;
        if (attr.dependsOn && nameToAttr[attr.dependsOn]) visit(nameToAttr[attr.dependsOn]);
        visited.add(attr.name);
        ordered.push(attr);
    }
    attrs.forEach(visit);

    // Generar recursivo respetando reglas
    const results = [];
    function backtrack(index, chosen) {
        if (index === ordered.length) {
            results.push({ ...chosen });
            return;
        }
        const attr = ordered[index];
        let allowed = attr.values;
        if (attr.dependsOn) {
            const parentVal = chosen[attr.dependsOn];
            if (parentVal != null) {
                const ruleRaw = attr.rulesSelected?.[parentVal] ?? attr.rules?.[parentVal] ?? [];
                const ruleVals = Array.isArray(ruleRaw) ? ruleRaw : parseCsv(ruleRaw);
                if (ruleVals.length > 0) {
                    allowed = attr.values.filter(v => ruleVals.includes(v));
                }
            }
        }
        for (const val of allowed) {
            chosen[attr.name] = val;
            backtrack(index + 1, chosen);
        }
        delete chosen[attr.name];
    }
    backtrack(0, {});

    // persistir definición para backend
    form.variant_attributes = attrs;

    form.variants = results.map(sel => {
        const parts = ordered.map(a => `${a.name}:${sel[a.name]}`);
        return {
            options_text: parts.join(', '),
            price: '',
            purchase_price: null,
            retail_price: null,
            stock: 0,
            alert: null,
        };
    });
};


// --- Lógica de Stock Total (Sigue igual) ---
const totalQuantity = computed(() => {
    if (form.variants.length === 0) return Number(form.quantity) || 0;
    return form.variants.reduce((t, v) => t + (Number(v.stock) || 0), 0);
});
watch(totalQuantity, (newTotal) => {
    if (form.variants.length > 0) form.quantity = newTotal;
});
// --- FIN Lógica Stock ---


// ===== LÓGICA NUEVA PARA SUMAR EL STOCK MÍNIMO =====
// Eliminado cálculo de mínimo: ahora no se usa

// Quitamos la regla que limitaba stock actual <= stock mínimo


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
            // Regenerar variantes con dependencias para persistencia coherente
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
                form.variants = results.map(sel => ({ options_text: ordered.map(a => `${a.name}:${sel[a.name]}`).join(', '), price: '', purchase_price: null, stock: 0, alert: null }));
            }
        } catch (_) {
            // Si falla, asegurar que variant_attributes esté vacío
            form.variant_attributes = [];
        }
    }

    // Usar form.transform() para agregar archivos dinámicos ANTES de enviar
    // Esto hace que Inertia los detecte automáticamente
    form.transform((data) => {
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
        // Asegurar que los datos principales se mantengan
        return data;
    });


    form.post(route('admin.products.store'), {
        preserveScroll: true,
        onError: async () => {
            // Llevar el foco/scroll al primer error y dejar claro qué falta
            await nextTick();
            // Construir mensajes legibles
            const msgs = [];
            for (const [key, val] of Object.entries(form.errors)) {
                if (key.startsWith('gallery_files.') && String(val).includes('The gallery files.')) {
                    msgs.push('Una o más imágenes superan el tamaño máximo permitido (2 MB) o no son válidas.');
                } else if (key.startsWith('new_gallery_files.')) {
                    msgs.push('Una o más imágenes nuevas superan el tamaño máximo permitido (2 MB) o no son válidas.');
                } else {
                    msgs.push(String(val));
                }
            }
            errorMessages.value = msgs;
            showErrors.value = msgs.length > 0;
            const firstErrorField = Object.keys(form.errors)[0];
            const el = document.getElementById(firstErrorField);
            if (el && typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.focus?.();
            }
        },
        onSuccess: () => {
            showSaved.value = true;
            form.reset();
        }
    });
};

</script>

<template>
    <Head title="Crear Producto" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear producto</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <div>
                                <div class="mb-4">
                                    <label for="name" class="block font-medium text-sm text-gray-700">Nombre</label>
                                    <input id="name" v-model="form.name" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" required>
                                </div>
                                <div class="mb-4">
                                    <label for="barcode" class="block font-medium text-sm text-gray-700 mb-1">Código de Barras (Opcional)</label>
                                    <div class="flex gap-2">
                                        <input id="barcode" v-model="form.barcode" type="text" class="flex-1 block mt-1 rounded-md shadow-sm border-gray-300" placeholder="Ej: 1234567890123">
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

                                <div class="p-4 border rounded-md bg-gray-50">
                                    <label class="inline-flex items-center gap-2 mb-3 select-none">
                                        <input type="checkbox" v-model="form.track_inventory" class="rounded">
                                        <span class="font-semibold">Controlar inventario</span>
                                    </label>
                                    <h4 v-if="form.track_inventory" class="font-semibold text-gray-800 mb-2">Inventario y Precios</h4>
                                    <p v-if="form.track_inventory" class="text-xs text-gray-500 mb-4">
                                        Si creas variantes, estos totales se calcularán solos.
                                    </p>
                                    <div v-if="form.track_inventory" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label for="quantity" class="block font-medium text-sm text-gray-700 flex items-center">Inventario (Total)
                                                <InfoTip class="ml-1 md:hidden" text="Stock si no hay variantes. Con variantes, se suma automáticamente." />
                                            </label>
                                            <input 
                                                id="quantity" 
                                                v-model="form.quantity" 
                                                type="number" 
                                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300" 
                                                :class="{ 'bg-gray-100': form.variants.length > 0 }"
                                                :disabled="form.track_inventory === false || form.variants.length > 0"  
                                                title="Stock si no hay variantes. Con variantes, se suma automáticamente."
                                            />
                                            <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">{{ form.errors.quantity }}</p>
                                        </div>
                                        <div>
                                            <label for="alert" class="block font-medium text-sm text-gray-700 flex items-center">Alerta (Opcional)
                                                <InfoTip class="ml-1 md:hidden" text="Se considera bajo stock cuando cantidad ≤ alerta." />
                                            </label>
                                            <input 
                                                id="alert" 
                                                v-model="form.alert" 
                                                type="number" 
                                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300"
                                                :class="{ 'bg-gray-100': form.variants.length > 0 && form.track_inventory }"
                                                :disabled="form.variants.length > 0 && form.track_inventory"
                                                title="Se considera bajo stock cuando cantidad ≤ alerta."
                                            />
                                        </div>
                                        <div>
                                            <label for="purchase_price" class="block font-medium text-sm text-gray-700 flex items-center">Precio de compra
                                                <InfoTip class="ml-1 md:hidden" text="Costo de adquisición del producto." />
                                            </label>
                                            <input id="purchase_price" v-model="form.purchase_price" type="number" step="0.01" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" />
                                        </div>
                                        
                                    </div>
                                </div>

                                <div class="mt-4 mb-2">
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
                                <p v-if="form.errors.category_id" class="mt-1 text-sm text-red-600">{{ form.errors.category_id }}</p>
                                <p v-if="requiresSubcategory && !form.errors.category_id" class="mt-1 text-sm text-red-600">
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
                                    <input id="specifications" v-model="form.specifications" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" placeholder="Alto: 2.30m,Ancho: 1.20m,...">
                                </div>
                                <div class="mb-4">
                                    <label for="meta_keywords" class="block font-medium text-sm text-gray-700">Palabras Clave SEO (separadas por comas)</label>
                                    <input id="meta_keywords" v-model="form.meta_keywords" type="text" class="block mt-1 w-full rounded-md shadow-sm border-gray-300" placeholder="palabra1, palabra2, palabra3">
                                    <p class="mt-1 text-xs text-gray-500">Estas palabras clave ayudan a mejorar el SEO del producto. No son visibles para los clientes.</p>
                                </div>
                            </div>
                            
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
                                                        <!-- Botón dependencias -->
                                                        <button 
                                                            type="button" 
                                                            class="w-8 h-8 inline-flex items-center justify-center rounded hover:bg-gray-100 text-indigo-600" 
                                                            :title="parent.__showDependency ? 'Ocultar dependencia' : 'Configurar dependencia'"
                                                            @click="toggleDependencyUi(parent)"
                                                        >
                                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                                            </svg>
                                                        </button>
                                                        
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
                                                        <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
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
                                        
                                        <!-- Formulario para agregar nueva variante principal -->
                                        <form @submit.prevent="addVariantParent" class="mt-6 border-t pt-4">
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
                                <div class="mb-4">
                                    <label for="gallery_files" class="block font-medium text-sm text-gray-700">Imágenes de la Galería</label>
                                    <input id="gallery_files" @input="onGalleryInput($event)" type="file" multiple class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <p class="mt-1 text-xs text-gray-500">Formatos de imagen y hasta 2 MB por archivo.</p>
                                </div>
                            </div>
                            
                            <div class="md:col-span-2 flex items-center justify-end mt-6 border-t pt-6">
                                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4a2 2 0 0 1 2-2h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4zm9-1.5V7h4.5L13 2.5z"/><path d="M8 13h8v2H8zM8 9h5v2H8z"/></svg>
                                    <span>Crear</span>
                                </button>
                                <span v-if="Object.keys(form.errors).length" class="ml-3 text-sm text-red-600">Por favor corrige los campos marcados.</span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <AlertModal
        :show="showSaved"
        type="success"
        title="¡Producto creado con éxito!"
        primary-text="Entendido"
        @primary="showSaved=false"
        @close="showSaved=false"
    />

    <AlertModal
        :show="showErrors"
        type="error"
        title="No pudimos guardar el producto"
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
                <div id="barcode-scanner-create" class="w-full rounded-lg overflow-hidden" style="min-height: 300px;"></div>
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
</template>