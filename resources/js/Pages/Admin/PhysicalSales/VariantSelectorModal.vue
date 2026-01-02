<script setup>
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    show: Boolean,
    product: Object,
});

const emit = defineEmits(['close', 'add-to-cart']);

const selectedOptions = ref({});
const availableVariants = computed(() => props.product?.variants || []);
const variantOptions = computed(() => props.product?.variant_options || []);

// Reset selections when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        selectedOptions.value = {};
    }
});

// Helper to check if an option is available based on previous selections
// This can be complex depending on full dependencies, for now we allow all that match structure
// Ideally we filter based on availableVariants

const isOptionSelected = (parentName, optionName) => {
    return selectedOptions.value[parentName] === optionName;
};

const selectOption = (parentName, optionName) => {
    selectedOptions.value[parentName] = optionName;
};

// Check if all required options are selected
const canAddToCart = computed(() => {
    if (!variantOptions.value.length) return true;
    
    // Check if every parent option has a selection
    return variantOptions.value.every(parent => !!selectedOptions.value[parent.name]);
});

// Find the matching variant object
const findMatchingVariant = () => {
    if (!availableVariants.value.length) return null;

    return availableVariants.value.find(variant => {
        // variant.options is an object/array: { "Color": "Rojo", "Talla": "S" }
        const options = variant.options;
        if (!options) return false;
        
        // Check if all selected options match this variant's options
        return Object.entries(selectedOptions.value).every(([key, value]) => {
            const variantValue = options[key];
            // Robust comparison: handle nulls and trim strings
            if (variantValue == null || value == null) return variantValue == value;
            return String(variantValue).trim() === String(value).trim();
        });
    });
};

const selectedVariant = computed(() => findMatchingVariant());

const variantStock = computed(() => {
    if (!selectedVariant.value) return null;
    return selectedVariant.value.stock;
});

const priceData = computed(() => {
    // 1. Obtener precio base (variante o producto)
    let basePrice = props.product?.price;
    if (selectedVariant.value) {
        const vp = selectedVariant.value.price;
        if (vp !== null && vp !== '' && vp !== undefined) {
            basePrice = vp;
        }
    }
    basePrice = parseFloat(basePrice || 0);

    // 2. Calcular descuento si aplica
    // El descuento viene del producto principal (promo_active, promo_discount_percent)
    // Asumimos que el descuento porcentual del producto aplica a sus variantes también
    let finalPrice = basePrice;
    let hasDiscount = false;

    if (props.product?.promo_active && props.product?.promo_discount_percent > 0) {
        const pct = parseFloat(props.product.promo_discount_percent);
        finalPrice = Math.round(basePrice * (1 - pct / 100)); // Redondeo simple para pesos
        hasDiscount = true;
    }

    return {
        original: basePrice,
        final: finalPrice,
        hasDiscount
    };
});

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined) return '';
    return new Intl.NumberFormat('es-CO', { 
        style: 'currency', 
        currency: 'COP', 
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};

const handleAddToCart = () => {
    const variant = findMatchingVariant();
    if (variant) {
        emit('add-to-cart', props.product, variant);
        emit('close');
    } else {
        // Fallback or error if no exact variant found (shouldn't happen if logic is correct)
        // Maybe the combination doesn't exist
        alert('Esta combinación de variantes no está disponible.');
    }
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Seleccionar Variante: {{ product?.name }}
            </h2>

            <div v-if="selectedVariant" class="mb-4 p-3 bg-blue-50 rounded-lg flex justify-between items-center text-blue-800">
                <div class="flex flex-col">
                    <span class="font-medium text-sm">Precio:</span>
                    <div class="flex items-center gap-2">
                         <span v-if="priceData.hasDiscount" class="text-sm text-gray-500 line-through">
                            {{ formatCurrency(priceData.original) }}
                        </span>
                        <span class="text-xl font-bold" :class="priceData.hasDiscount ? 'text-blue-600' : 'text-gray-900'">
                            {{ formatCurrency(priceData.final) }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-col text-right">
                    <span class="font-medium text-sm">Stock Disponible:</span>
                    <span class="text-xl font-bold">
                        {{ (!product?.track_inventory || product?.track_inventory === '0' || product?.track_inventory === 0) ? 'Ilimitado' : (variantStock ?? 'N/A') }}
                    </span>
                </div>
            </div>

            <!-- Opciones -->
            <div v-if="variantOptions.length > 0" class="space-y-6">
                <div v-for="parent in variantOptions" :key="parent.id">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ parent.name }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="child in parent.children"
                            :key="child.id"
                            @click="selectOption(parent.name, child.name)"
                            class="px-4 py-2 text-sm rounded-md border transition-colors"
                            :class="[
                                isOptionSelected(parent.name, child.name)
                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            {{ child.name }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="py-4 text-center text-gray-500">
                No hay opciones disponibles para este producto.
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="$emit('close')">
                    Cancelar
                </SecondaryButton>
                
                <PrimaryButton 
                    @click="handleAddToCart" 
                    :disabled="!canAddToCart"
                    :class="{ 'opacity-50 cursor-not-allowed': !canAddToCart }"
                >
                    Agregar al Carrito
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
