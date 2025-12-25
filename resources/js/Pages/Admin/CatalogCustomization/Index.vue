<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    store: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    gallery_type: props.store.gallery_type ?? 'products',
    gallery_show_buy_button: props.store.gallery_show_buy_button ?? true,
    catalog_use_default: props.store.catalog_use_default ?? true,
    catalog_product_template: props.store.catalog_product_template ?? 'default',
    catalog_show_buy_button: props.store.catalog_show_buy_button ?? false,
    catalog_header_style: props.store.catalog_header_style ?? 'default',
    // Colores granulares
    catalog_header_bg_color: props.store.catalog_header_bg_color ?? '#FFFFFF',
    catalog_header_text_color: props.store.catalog_header_text_color ?? '#1F2937',
    catalog_button_bg_color: props.store.catalog_button_bg_color ?? '#2563EB',
    catalog_button_text_color: props.store.catalog_button_text_color ?? '#FFFFFF',
    catalog_body_bg_color: props.store.catalog_body_bg_color ?? '#FFFFFF',
    catalog_body_text_color: props.store.catalog_body_text_color ?? '#1F2937',
    catalog_input_bg_color: props.store.catalog_input_bg_color ?? '#FFFFFF',
    catalog_input_text_color: props.store.catalog_input_text_color ?? '#1F2937',
    // Colores específicos (legacy, mantener compatibilidad)
    catalog_button_color: props.store.catalog_button_color ?? '#1F2937',
    catalog_promo_banner_color: props.store.catalog_promo_banner_color ?? '#DC2626',
    catalog_promo_banner_text_color: props.store.catalog_promo_banner_text_color ?? '#FFFFFF',
    catalog_variant_button_color: props.store.catalog_variant_button_color ?? '#2563EB',
    catalog_purchase_button_color: props.store.catalog_purchase_button_color ?? '#2563EB',
    catalog_cart_bubble_color: props.store.catalog_cart_bubble_color ?? '#2563EB',
    catalog_social_button_color: props.store.catalog_social_button_color ?? '#2563EB',
});

const showSuccessModal = ref(false);
const showPreviewModal = ref(false);
const previewMode = ref('mobile'); // 'mobile' | 'desktop'
const showResetConfirm = ref(false);

const submit = () => {
    form.put(route('admin.catalog-customization.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccessModal.value = true;
        },
        onError: (errors) => {
            // Si hay errores, se mostrarán en el formulario
        },
    });
};

const resetToDefaults = () => {
    // Resetear todos los valores a los por defecto
    form.catalog_use_default = true;
    form.catalog_product_template = 'default';
    form.catalog_show_buy_button = false;
    form.catalog_header_style = 'default';
    // Colores granulares - valores por defecto
    form.catalog_header_bg_color = '#FFFFFF';
    form.catalog_header_text_color = '#1F2937';
    form.catalog_button_bg_color = '#2563EB';
    form.catalog_button_text_color = '#FFFFFF';
    form.catalog_body_bg_color = '#FFFFFF';
    form.catalog_body_text_color = '#1F2937';
    form.catalog_input_bg_color = '#FFFFFF';
    form.catalog_input_text_color = '#1F2937';
    // Colores específicos (legacy) - valores por defecto
    form.catalog_button_color = '#1F2937';
    form.catalog_promo_banner_color = '#DC2626';
    form.catalog_promo_banner_text_color = '#FFFFFF';
    form.catalog_variant_button_color = '#2563EB';
    form.catalog_purchase_button_color = '#2563EB';
    form.catalog_cart_bubble_color = '#2563EB';
    form.catalog_social_button_color = '#2563EB';
    showResetConfirm.value = false;
};

// Resetear colores cuando se activa modo por defecto
watch(() => form.catalog_use_default, (newValue) => {
    if (newValue) {
        form.catalog_header_bg_color = '#FFFFFF';
        form.catalog_header_text_color = '#1F2937';
        form.catalog_button_bg_color = '#2563EB';
        form.catalog_button_text_color = '#FFFFFF';
        form.catalog_body_bg_color = '#FFFFFF';
        form.catalog_body_text_color = '#1F2937';
        form.catalog_input_bg_color = '#FFFFFF';
        form.catalog_input_text_color = '#1F2937';
        form.catalog_promo_banner_color = '#DC2626';
        form.catalog_promo_banner_text_color = '#FFFFFF';
    }
});

// Plantillas de productos
const productTemplates = [
    { value: 'big', label: 'Big', description: 'Productos grandes destacados' },
    { value: 'default', label: 'Default', description: 'Grid 3x3 compacto (recomendado)' },
    { value: 'full_text', label: 'Full Text', description: 'Lista horizontal con texto completo' },
];

// Estilos de header
const headerStyles = [
    { value: 'default', label: 'Default', description: 'Header simple con logo a la izquierda' },
    { value: 'fit', label: 'Fit', description: 'Header compacto solo con iconos' },
    { value: 'banner_logo', label: 'Banner & Logo', description: 'Header con banner y logo centrado' },
];

// Colores granulares
const granularColors = [
    { 
        group: 'Header', 
        bg: 'catalog_header_bg_color', 
        text: 'catalog_header_text_color',
        bgLabel: 'Fondo del header',
        textLabel: 'Texto del header'
    },
    { 
        group: 'Botones', 
        bg: 'catalog_button_bg_color', 
        text: 'catalog_button_text_color',
        bgLabel: 'Fondo de los botones',
        textLabel: 'Texto de los botones'
    },
    { 
        group: 'Body', 
        bg: 'catalog_body_bg_color', 
        text: 'catalog_body_text_color',
        bgLabel: 'Fondo del body',
        textLabel: 'Texto del body'
    },
    { 
        group: 'Input', 
        bg: 'catalog_input_bg_color', 
        text: 'catalog_input_text_color',
        bgLabel: 'Fondo del input',
        textLabel: 'Texto del input'
    },
    { 
        group: 'Cinta de Promoción', 
        bg: 'catalog_promo_banner_color', 
        text: 'catalog_promo_banner_text_color',
        bgLabel: 'Fondo de la cinta de promoción',
        textLabel: 'Texto de la cinta de promoción'
    },
];

const getColorPreviewStyle = (bgColor, textColor) => {
    if (form.catalog_use_default) {
        return {};
    }
    return {
        backgroundColor: bgColor,
        color: textColor,
    };
};

// Estilos para la vista previa
const previewStyles = computed(() => {
    if (form.catalog_use_default) {
        return {
            header: { backgroundColor: '#FFFFFF', color: '#1F2937' },
            button: { backgroundColor: '#2563EB', color: '#FFFFFF' },
            body: { backgroundColor: '#FFFFFF', color: '#1F2937' },
            promo: { backgroundColor: '#DC2626', color: '#FFFFFF' },
            headerStyle: 'default',
        };
    }
    return {
        header: {
            backgroundColor: form.catalog_header_bg_color || '#FFFFFF',
            color: form.catalog_header_text_color || '#1F2937',
        },
        button: {
            backgroundColor: form.catalog_button_bg_color || '#2563EB',
            color: form.catalog_button_text_color || '#FFFFFF',
        },
        body: {
            backgroundColor: form.catalog_body_bg_color || '#FFFFFF',
            color: form.catalog_body_text_color || '#1F2937',
        },
        promo: {
            backgroundColor: form.catalog_promo_banner_color || '#DC2626',
            color: form.catalog_promo_banner_text_color || '#FFFFFF',
        },
        headerStyle: form.catalog_header_style || 'default',
    };
});
</script>

<template>
    <Head title="Personalizar Catálogo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Personalizar Catálogo
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit">
                            <!-- Configuración de Galería -->
                            <div class="mb-8 p-4 border border-gray-200 rounded-lg bg-gray-50">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Galería Principal</h3>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Tipo de Galería
                                    </label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.gallery_type"
                                                value="products"
                                                class="mr-2"
                                            />
                                            <span class="text-sm text-gray-700">Productos Destacados</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.gallery_type"
                                                value="custom"
                                                class="mr-2"
                                            />
                                            <span class="text-sm text-gray-700">Imágenes Personalizadas</span>
                                        </label>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500">
                                        Selecciona si quieres mostrar productos destacados o imágenes personalizadas en la galería principal del catálogo.
                                    </p>
                                </div>

                                <div v-if="form.gallery_type === 'products'" class="mb-4">
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            v-model="form.gallery_show_buy_button"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                        />
                                        <span class="ml-3 text-sm font-medium text-gray-700">
                                            Mostrar botón "Comprar ahora" en la galería
                                        </span>
                                    </label>
                                </div>

                                <div v-if="form.gallery_type === 'custom'" class="mb-4">
                                    <Link 
                                        :href="route('admin.gallery-images.index')"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                    >
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        Gestionar Imágenes de la Galería
                                    </Link>
                                    <p class="mt-2 text-xs text-gray-500">
                                        Agrega, edita y organiza las imágenes personalizadas de tu galería. Puedes linkear cada imagen a un producto específico.
                                    </p>
                                </div>
                            </div>

                            <!-- Modo por defecto -->
                            <div class="mb-8 p-4 border border-gray-200 rounded-lg bg-gray-50">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.catalog_use_default"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                    />
                                    <span class="ml-3 text-sm font-medium text-gray-700">
                                        Usar modo por defecto
                                    </span>
                                </label>
                                <p class="mt-2 ml-8 text-xs text-gray-500">
                                    Cuando está activado, se usarán los colores predeterminados del sistema.
                                </p>
                            </div>

                            <!-- Plantillas de Productos -->
                            <div v-if="!form.catalog_use_default" class="mb-8 border-t pt-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Plantilla de Lista de Productos</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <button
                                        v-for="template in productTemplates"
                                        :key="template.value"
                                        type="button"
                                        @click="form.catalog_product_template = template.value"
                                        :class="[
                                            'relative p-4 border-2 rounded-lg transition-all text-left',
                                            form.catalog_product_template === template.value
                                                ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-200'
                                                : 'border-gray-300 bg-white hover:border-gray-400'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-semibold text-gray-800">{{ template.label }}</span>
                                            <div v-if="form.catalog_product_template === template.value" class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center">
                                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-600 mb-3">{{ template.description }}</p>
                                        <!-- Preview visual -->
                                        <div class="mt-3 bg-gray-100 rounded p-2 h-24 overflow-hidden flex items-center justify-center">
                                            <div v-if="template.value === 'big'" class="w-full space-y-1.5 px-1">
                                                <div class="h-14 bg-blue-300 rounded"></div>
                                                <div class="h-6 bg-blue-200 rounded"></div>
                                            </div>
                                            <div v-else-if="template.value === 'default'" class="grid grid-cols-3 gap-1 w-full px-1">
                                                <div v-for="i in 9" :key="i" class="aspect-square bg-blue-300 rounded"></div>
                                            </div>
                                            <div v-else class="w-full space-y-1.5 px-1">
                                                <div v-for="i in 3" :key="i" class="h-8 bg-blue-300 rounded flex items-center gap-1.5">
                                                    <div class="w-6 h-6 bg-blue-400 rounded ml-1.5 flex-shrink-0"></div>
                                                    <div class="flex-1 space-y-0.5 min-w-0">
                                                        <div class="h-1.5 bg-blue-200 rounded w-3/4"></div>
                                                        <div class="h-1.5 bg-blue-200 rounded w-1/2"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                                
                                <!-- Checkbox para mostrar botón "Comprar Ahora" -->
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            v-model="form.catalog_show_buy_button"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                        />
                                        <span class="ml-2 text-sm font-medium text-gray-700">
                                            Botón "Comprar Ahora"
                                        </span>
                                    </label>
                                    <p class="mt-1 ml-6 text-xs text-gray-500">
                                        Muestra un botón "Comprar Ahora" en cada producto de la lista
                                    </p>
                                </div>
                            </div>

                            <!-- Estilos de Header -->
                            <div v-if="!form.catalog_use_default" class="mb-8 border-t pt-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Estilo de Header</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <button
                                        v-for="style in headerStyles"
                                        :key="style.value"
                                        type="button"
                                        @click="form.catalog_header_style = style.value"
                                        :class="[
                                            'relative p-4 border-2 rounded-lg transition-all text-left',
                                            form.catalog_header_style === style.value
                                                ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-200'
                                                : 'border-gray-300 bg-white hover:border-gray-400'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-semibold text-gray-800">{{ style.label }}</span>
                                            <div v-if="form.catalog_header_style === style.value" class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center">
                                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-600">{{ style.description }}</p>
                                        <!-- Preview visual -->
                                        <div class="mt-3 bg-gray-100 rounded p-2">
                                            <div v-if="style.value === 'default'" class="bg-blue-200 rounded p-2 flex items-center justify-between">
                                                <div class="w-8 h-8 bg-blue-400 rounded"></div>
                                                <div class="flex gap-1">
                                                    <div class="w-4 h-4 bg-blue-400 rounded"></div>
                                                    <div class="w-4 h-4 bg-blue-400 rounded"></div>
                                                </div>
                                            </div>
                                            <div v-else-if="style.value === 'fit'" class="bg-blue-200 rounded p-2 flex items-center justify-between">
                                                <div class="w-8 h-8 bg-blue-400 rounded"></div>
                                                <div class="flex gap-1">
                                                    <div class="w-4 h-4 bg-blue-400 rounded"></div>
                                                    <div class="w-4 h-4 bg-blue-400 rounded"></div>
                                                    <div class="w-4 h-4 bg-blue-400 rounded"></div>
                                                </div>
                                            </div>
                                            <div v-else class="space-y-1">
                                                <div class="bg-gray-800 rounded p-1 flex justify-end">
                                                    <div class="w-4 h-4 bg-gray-400 rounded"></div>
                                                </div>
                                                <div class="bg-blue-200 rounded p-2 flex items-center justify-center">
                                                    <div class="w-12 h-12 bg-blue-400 rounded"></div>
                                                </div>
                                                <div class="bg-blue-200 rounded p-1 flex items-center gap-1">
                                                    <div class="w-4 h-4 bg-blue-400 rounded"></div>
                                                    <div class="w-4 h-4 bg-blue-400 rounded"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Colores Granulares -->
                            <div v-if="!form.catalog_use_default" class="mb-8 border-t pt-6">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Editar Colores</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <template v-for="colorGroup in (granularColors || [])" :key="colorGroup.group">
                                        <!-- Fondo -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ colorGroup.bgLabel }}
                                            </label>
                                            <div class="flex items-center gap-3">
                                                <input
                                                    type="color"
                                                    v-model="form[colorGroup.bg]"
                                                    class="h-12 w-16 rounded border-2 border-gray-300 cursor-pointer shadow-sm"
                                                />
                                                <input
                                                    type="text"
                                                    v-model="form[colorGroup.bg]"
                                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    placeholder="#FFFFFF"
                                                />
                                            </div>
                                            <!-- Preview -->
                                            <div class="p-3 rounded border border-gray-200" :style="{ backgroundColor: form[colorGroup.bg] }">
                                                <div class="text-xs text-gray-500">Vista previa</div>
                                            </div>
                                        </div>
                                        
                                        <!-- Texto -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ colorGroup.textLabel }}
                                            </label>
                                            <div class="flex items-center gap-3">
                                                <input
                                                    type="color"
                                                    v-model="form[colorGroup.text]"
                                                    class="h-12 w-16 rounded border-2 border-gray-300 cursor-pointer shadow-sm"
                                                />
                                                <input
                                                    type="text"
                                                    v-model="form[colorGroup.text]"
                                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                    placeholder="#000000"
                                                />
                                            </div>
                                            <!-- Preview con fondo y texto -->
                                            <div class="p-3 rounded border border-gray-200" :style="getColorPreviewStyle(form[colorGroup.bg], form[colorGroup.text])">
                                                <div class="text-xs font-medium">Texto</div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div class="mt-8 flex flex-wrap items-center justify-center sm:justify-end gap-3 sm:gap-4 border-t pt-6 pb-6 sm:pb-8">
                                <button
                                    type="button"
                                    @click="showResetConfirm = true"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 bg-white border border-red-300 rounded-lg shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                    </svg>
                                    Restablecer
                                </button>
                                <button
                                    type="button"
                                    @click="showPreviewModal = true"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Vista Previa
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 animate-spin">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                    </svg>
                                    <span v-if="form.processing">Guardando...</span>
                                    <span v-else>Guardar cambios</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Modal de éxito -->
    <AlertModal
        :show="showSuccessModal"
        type="success"
        title="¡Cambios guardados!"
        message="Tu personalización del catálogo se ha actualizado correctamente."
        primary-text="Entendido"
        @primary="showSuccessModal = false"
        @close="showSuccessModal = false"
    />

    <!-- Modal de confirmación para restablecer -->
    <Modal :show="showResetConfirm" @close="showResetConfirm = false" maxWidth="md">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                ¿Restablecer configuración?
            </h3>
            <p class="text-sm text-gray-600 text-center mb-6">
                Esta acción restablecerá todos los valores de personalización a los valores por defecto del sistema. Esta acción no se puede deshacer.
            </p>
            <div class="flex items-center justify-center gap-3">
                <button
                    @click="showResetConfirm = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                    Cancelar
                </button>
                <button
                    @click="resetToDefaults"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                >
                    Restablecer
                </button>
            </div>
        </div>
    </Modal>

    <!-- Modal de vista previa -->
    <Modal :show="showPreviewModal" @close="showPreviewModal = false" :maxWidth="previewMode === 'desktop' ? '5xl' : 'md'">
        <div class="p-3 sm:p-4 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between mb-3 sm:mb-4 flex-shrink-0 relative">
                <h3 class="flex-1 text-center text-lg sm:text-xl font-serif font-light tracking-wider text-gray-900">Vista Previa del Catálogo</h3>
                <button
                    @click="showPreviewModal = false"
                    class="absolute right-0 p-2 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
                    aria-label="Cerrar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 sm:w-6 sm:h-6 text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Selector de vista -->
            <div class="flex items-center justify-center gap-3 sm:gap-4 mb-3 sm:mb-4 flex-shrink-0">
                <button
                    @click="previewMode = 'mobile'"
                    :class="[
                        'px-4 sm:px-6 py-2 rounded-lg text-sm sm:text-base font-medium transition-colors',
                        previewMode === 'mobile'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    ]"
                >
                    📱 Móvil
                </button>
                <button
                    @click="previewMode = 'desktop'"
                    :class="[
                        'px-4 sm:px-6 py-2 rounded-lg text-sm sm:text-base font-medium transition-colors',
                        previewMode === 'desktop'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    ]"
                >
                    🖥️ Escritorio
                </button>
            </div>
            
            <!-- Contenedor scrollable para las previews -->
            <div class="flex-1 overflow-y-auto -mx-4 sm:-mx-6 px-4 sm:px-6">

            <!-- Vista previa móvil -->
            <div v-if="previewMode === 'mobile'" class="mx-auto bg-gray-100 rounded-2xl p-4" style="max-width: 375px;">
                <div class="bg-white rounded-xl shadow-xl overflow-hidden" style="height: 600px;">
                    <!-- Cinta de promoción (PRIMERO, antes del header) -->
                    <div v-if="!form.catalog_use_default || form.catalog_promo_banner_color" class="relative overflow-hidden" :style="previewStyles.promo">
                        <div class="flex whitespace-nowrap animate-scroll text-xs font-bold uppercase py-2.5 px-4">
                            <span class="flex items-center gap-1 mx-2">🔥 Ofertas hasta 50% • Toca para ver ↗</span>
                            <span class="flex items-center gap-1 mx-2">🔥 Ofertas hasta 50% • Toca para ver ↗</span>
                            <span class="flex items-center gap-1 mx-2">🔥 Ofertas hasta 50% • Toca para ver ↗</span>
                        </div>
                    </div>
                    
                    <!-- Header Default -->
                    <div v-if="(previewStyles.headerStyle === 'default' || form.catalog_use_default) && previewStyles.headerStyle !== 'banner_logo' && previewStyles.headerStyle !== 'fit'" class="px-4 py-3 border-b flex items-center justify-between" :style="previewStyles.header">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-gray-300"></div>
                            <span class="font-semibold text-sm">{{ props.store.name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-gray-200"></div>
                            <div class="w-6 h-6 rounded-full" :style="{ backgroundColor: previewStyles.button.backgroundColor }"></div>
                        </div>
                    </div>
                    
                    <!-- Header Fit -->
                    <div v-else-if="previewStyles.headerStyle === 'fit'" class="px-4 py-2 border-b flex items-center justify-between" :style="previewStyles.header">
                        <div class="w-6 h-6 rounded-full bg-gray-300"></div>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-gray-200"></div>
                            <div class="w-6 h-6 rounded-full" :style="{ backgroundColor: previewStyles.button.backgroundColor }"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-gray-300"></div>
                            <span class="font-serif font-light text-xs tracking-wider">{{ props.store.name }}</span>
                        </div>
                    </div>
                    
                    <!-- Header Banner & Logo -->
                    <template v-else-if="previewStyles.headerStyle === 'banner_logo'">
                        <!-- Banner superior oscuro -->
                        <div class="bg-gray-800 text-white py-2 px-4 flex justify-end items-center gap-2">
                            <div class="w-5 h-5 rounded-full bg-gray-600"></div>
                            <div class="w-5 h-5 rounded-full bg-gray-600"></div>
                        </div>
                        <!-- Área principal con logo centrado (con fondo personalizado) -->
                        <div class="px-4 py-6 flex flex-col items-center gap-3" :style="previewStyles.header">
                            <div class="w-16 h-16 rounded-full bg-gray-300 ring-2 ring-gray-200"></div>
                            <span class="font-serif font-light text-xs tracking-wider" :style="{ color: previewStyles.header.color }">{{ props.store.name }}</span>
                        </div>
                        <!-- Barra de navegación inferior -->
                        <div class="px-4 py-2 border-t flex items-center justify-between" :style="previewStyles.header">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full bg-gray-200"></div>
                                <div class="w-5 h-5 rounded-full bg-gray-200"></div>
                            </div>
                            <div class="flex items-center gap-1">
                                <div class="w-5 h-5 rounded-full bg-gray-200"></div>
                                <div class="w-5 h-5 rounded-full bg-gray-200"></div>
                                <div class="w-5 h-5 rounded-full bg-gray-200"></div>
                                <div class="w-5 h-5 rounded-full bg-gray-200"></div>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Body -->
                    <div class="p-4 overflow-y-auto" :style="{ ...previewStyles.body, height: previewStyles.headerStyle === 'banner_logo' ? 'calc(600px - 180px)' : (previewStyles.headerStyle === 'fit' ? 'calc(600px - 80px)' : (form.catalog_promo_banner_color && !form.catalog_use_default ? 'calc(600px - 100px)' : 'calc(600px - 60px)')) }">
                        <!-- Galería dinámica -->
                        <div class="mb-4 relative overflow-hidden rounded-xl shadow-lg">
                            <div class="relative h-48 bg-gradient-to-br from-gray-200 to-gray-300">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="w-32 h-32 bg-gray-400 rounded-lg"></div>
                                </div>
                                <!-- Overlay con información -->
                                <div class="absolute inset-0 flex flex-col justify-between p-3">
                                    <div class="bg-white/70 backdrop-blur-sm rounded-lg px-3 py-2">
                                        <div class="h-4 bg-gray-500 rounded mb-1.5 w-3/4"></div>
                                        <div class="h-3 bg-gray-400 rounded w-1/2"></div>
                                    </div>
                                    <div class="flex justify-center">
                                        <button class="px-4 py-2 rounded-full text-xs font-bold shadow-lg" :style="previewStyles.button">
                                            COMPRAR
                                        </button>
                                    </div>
                                </div>
                                <!-- Indicadores -->
                                <div class="absolute bottom-2 left-1/2 transform -translate-x-1/2 flex gap-1.5">
                                    <div class="w-6 h-1.5 bg-white rounded-full"></div>
                                    <div class="w-1.5 h-1.5 bg-white/50 rounded-full"></div>
                                    <div class="w-1.5 h-1.5 bg-white/50 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantilla Big -->
                        <div v-if="form.catalog_product_template === 'big'" class="space-y-4">
                            <div v-for="i in 2" :key="i" class="border rounded-lg overflow-hidden">
                                <div class="h-48 bg-gray-200"></div>
                                <div class="p-4">
                                    <div class="h-5 bg-gray-300 rounded mb-2"></div>
                                    <div class="h-4 bg-gray-300 rounded w-3/4 mb-3"></div>
                                    <div class="flex items-center justify-between">
                                        <div class="h-6 bg-gray-400 rounded w-24"></div>
                                        <button v-if="form.catalog_show_buy_button" class="px-5 py-2.5 rounded-lg text-sm font-bold" :style="previewStyles.button">
                                            Comprar Ahora
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantilla Default -->
                        <div v-else-if="form.catalog_product_template === 'default'" class="grid grid-cols-2 gap-3">
                            <div v-for="i in 6" :key="i" class="border rounded-lg overflow-hidden">
                                <div class="h-32 bg-gray-200"></div>
                                <div class="p-2">
                                    <div class="h-3 bg-gray-300 rounded mb-1.5"></div>
                                    <div class="h-3 bg-gray-300 rounded w-2/3 mb-2"></div>
                                    <div class="flex items-center justify-between">
                                        <div class="h-4 bg-gray-400 rounded w-16"></div>
                                        <button v-if="form.catalog_show_buy_button" class="px-3 py-1.5 rounded text-xs font-semibold" :style="previewStyles.button">
                                            Comprar Ahora
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantilla Full Text -->
                        <div v-else class="space-y-3">
                            <div v-for="i in 3" :key="i" class="flex gap-3 border rounded-lg p-3">
                                <div class="w-20 h-20 rounded-lg bg-gray-200 flex-shrink-0"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="h-4 bg-gray-300 rounded mb-2"></div>
                                    <div class="h-3 bg-gray-300 rounded w-full mb-1"></div>
                                    <div class="h-3 bg-gray-300 rounded w-3/4 mb-3"></div>
                                    <div class="flex items-center justify-between">
                                        <div class="h-4 bg-gray-400 rounded w-20"></div>
                                        <button v-if="form.catalog_show_buy_button" class="px-3 py-1.5 rounded text-xs font-semibold" :style="previewStyles.button">
                                            Comprar Ahora
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vista previa escritorio -->
            <div v-if="previewMode === 'desktop'" class="flex justify-center">
                <div class="w-full max-w-4xl bg-gray-100 rounded-lg p-2 sm:p-3 overflow-x-auto">
                    <div class="bg-white rounded-lg shadow-xl overflow-hidden" style="max-height: 60vh; display: flex; flex-direction: column;">
                    <!-- Cinta de promoción (PRIMERO, antes del header) -->
                    <div v-if="!form.catalog_use_default || form.catalog_promo_banner_color" class="relative overflow-hidden flex-shrink-0" :style="previewStyles.promo">
                        <div class="flex whitespace-nowrap animate-scroll text-sm font-bold uppercase py-3 px-6">
                            <span class="flex items-center gap-2 mx-4">🔥 Ofertas hasta 50% • Toca para ver ↗</span>
                            <span class="flex items-center gap-2 mx-4">🔥 Ofertas hasta 50% • Toca para ver ↗</span>
                            <span class="flex items-center gap-2 mx-4">🔥 Ofertas hasta 50% • Toca para ver ↗</span>
                        </div>
                    </div>
                    
                    <!-- Header Default -->
                    <div v-if="(previewStyles.headerStyle === 'default' || form.catalog_use_default) && previewStyles.headerStyle !== 'banner_logo' && previewStyles.headerStyle !== 'fit'" class="px-6 py-4 border-b flex items-center justify-between flex-shrink-0" :style="previewStyles.header">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gray-300"></div>
                            <span class="font-semibold">{{ props.store.name }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-200"></div>
                            <div class="w-8 h-8 rounded-full" :style="{ backgroundColor: previewStyles.button.backgroundColor }"></div>
                        </div>
                    </div>
                    
                    <!-- Header Fit -->
                    <div v-else-if="previewStyles.headerStyle === 'fit'" class="px-6 py-3 border-b flex items-center justify-between flex-shrink-0" :style="previewStyles.header">
                        <div class="w-8 h-8 rounded-full bg-gray-300"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-200"></div>
                            <div class="w-8 h-8 rounded-full" :style="{ backgroundColor: previewStyles.button.backgroundColor }"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-10 h-10 rounded-full bg-gray-300"></div>
                            <span class="font-serif font-light text-sm tracking-wider">{{ props.store.name }}</span>
                        </div>
                    </div>
                    
                    <!-- Header Banner & Logo -->
                    <template v-else-if="previewStyles.headerStyle === 'banner_logo'">
                        <!-- Banner superior oscuro -->
                        <div class="bg-gray-800 text-white py-2 px-6 flex justify-end items-center gap-3 flex-shrink-0">
                            <div class="w-6 h-6 rounded-full bg-gray-600"></div>
                            <div class="w-6 h-6 rounded-full bg-gray-600"></div>
                        </div>
                        <!-- Área principal con logo centrado (con fondo personalizado) -->
                        <div class="px-6 py-6 flex flex-col items-center gap-3 flex-shrink-0" :style="previewStyles.header">
                            <div class="w-20 h-20 rounded-full bg-gray-300 ring-2 ring-gray-200"></div>
                            <span class="font-serif font-light text-base tracking-wider" :style="{ color: previewStyles.header.color }">{{ props.store.name }}</span>
                        </div>
                        <!-- Barra de navegación inferior -->
                        <div class="px-6 py-3 border-t flex items-center justify-between flex-shrink-0" :style="previewStyles.header">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-200"></div>
                                <div class="w-8 h-8 rounded-full bg-gray-200"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gray-200"></div>
                                <div class="w-7 h-7 rounded-full bg-gray-200"></div>
                                <div class="w-7 h-7 rounded-full bg-gray-200"></div>
                                <div class="w-7 h-7 rounded-full bg-gray-200"></div>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Body con scroll -->
                    <div class="flex-1 overflow-y-auto p-4 sm:p-6" :style="previewStyles.body">
                        <!-- Galería dinámica -->
                        <div class="mb-6 relative overflow-hidden rounded-xl shadow-lg">
                            <div class="relative h-64 bg-gradient-to-br from-gray-200 to-gray-300">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="w-48 h-48 bg-gray-400 rounded-lg"></div>
                                </div>
                                <!-- Overlay con información -->
                                <div class="absolute inset-0 flex flex-col justify-between p-4 sm:p-6">
                                    <div class="bg-white/70 backdrop-blur-sm rounded-lg px-4 py-3">
                                        <div class="h-5 bg-gray-500 rounded mb-2 w-2/3"></div>
                                        <div class="h-4 bg-gray-400 rounded w-1/2"></div>
                                    </div>
                                    <div class="flex justify-center">
                                        <button class="px-6 py-3 rounded-full text-sm font-bold shadow-xl" :style="previewStyles.button">
                                            COMPRAR
                                        </button>
                                    </div>
                                </div>
                                <!-- Indicadores -->
                                <div class="absolute bottom-3 left-1/2 transform -translate-x-1/2 flex gap-2">
                                    <div class="w-8 h-2 bg-white rounded-full"></div>
                                    <div class="w-2 h-2 bg-white/50 rounded-full"></div>
                                    <div class="w-2 h-2 bg-white/50 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantilla Big -->
                        <div v-if="form.catalog_product_template === 'big'" class="grid grid-cols-2 gap-6">
                            <div v-for="i in 2" :key="i" class="border rounded-lg overflow-hidden">
                                <div class="h-56 bg-gray-200"></div>
                                <div class="p-5">
                                    <div class="h-5 bg-gray-300 rounded mb-2"></div>
                                    <div class="h-4 bg-gray-300 rounded w-3/4 mb-4"></div>
                                    <div class="flex items-center justify-between">
                                        <div class="h-6 bg-gray-400 rounded w-28"></div>
                                        <button v-if="form.catalog_show_buy_button" class="px-6 py-3 rounded-lg text-sm font-bold" :style="previewStyles.button">
                                            Comprar Ahora
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantilla Default -->
                        <div v-else-if="form.catalog_product_template === 'default'" class="grid grid-cols-3 gap-4">
                            <div v-for="i in 6" :key="i" class="border rounded-lg overflow-hidden">
                                <div class="h-40 bg-gray-200"></div>
                                <div class="p-4">
                                    <div class="h-4 bg-gray-300 rounded mb-2"></div>
                                    <div class="h-4 bg-gray-300 rounded w-2/3 mb-3"></div>
                                    <div class="flex items-center justify-between">
                                        <div class="h-5 bg-gray-400 rounded w-24"></div>
                                        <button v-if="form.catalog_show_buy_button" class="px-4 py-2 rounded-lg text-sm font-semibold" :style="previewStyles.button">
                                            Comprar Ahora
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantilla Full Text -->
                        <div v-else class="space-y-4">
                            <div v-for="i in 4" :key="i" class="flex gap-4 border rounded-lg p-4">
                                <div class="w-32 h-32 rounded-lg bg-gray-200 flex-shrink-0"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="h-5 bg-gray-300 rounded mb-2"></div>
                                    <div class="h-4 bg-gray-300 rounded w-full mb-1"></div>
                                    <div class="h-4 bg-gray-300 rounded w-3/4 mb-4"></div>
                                    <div class="flex items-center justify-between">
                                        <div class="h-5 bg-gray-400 rounded w-24"></div>
                                        <button v-if="form.catalog_show_buy_button" class="px-4 py-2 rounded-lg text-sm font-semibold" :style="previewStyles.button">
                                            Comprar Ahora
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </Modal>
</template>
