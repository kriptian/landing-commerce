<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AlertModal from '@/Components/AlertModal.vue';
import Modal from '@/Components/Modal.vue';
import { downloadPDF } from '@/Utils/pdfUtils';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    store: {
        type: Object,
        required: true,
    },
});

const catalogRef = ref(null);
const search = ref('');
const activeTab = ref('edit');
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200);
const isGenerating = ref(false);
const showNotice = ref(false);
const noticeMessage = ref('');
const coverPreview = ref('');
const catalogItems = ref([]);
const draggedIndex = ref(null);
const dragOverIndex = ref(null);
const previewItem = ref(null);

const settings = ref({
    fileName: 'catalogo-productos',
    title: props.store?.name ? `Catálogo ${props.store.name}` : 'Catálogo de productos',
    sectionTitle: '',
    subtitle: 'Productos seleccionados para ti',
    style: 'elegant',
    textColor: '#111827',
    accentColor: '#2563EB',
    priceColor: '#FFFFFF',
    priceBgColor: '#111827',
    textPosition: 'bottom-left',
    pricePosition: 'top-right',
    itemsPerPage: 4,
    showBusinessInfoOnCover: false,
    coverBusinessStyle: 'contact-list',
    showCoverLogo: true,
    showBusinessPhone: true,
    showBusinessEmail: true,
    showBusinessAddress: true,
    showBusinessWebsite: true,
    showBusinessInstagram: true,
    showBusinessFacebook: false,
    showBusinessTiktok: false,
    logoApplyTo: 'none',
    logoPosition: 'top-right',
    logoSize: 'medium',
    logoOpacity: 18,
});

const stylePresets = {
    elegant: {
        label: 'Elegante',
        description: 'Portada sobria, tarjetas limpias y precio destacado.',
        pageClass: 'bg-[#f7f3ee] text-slate-900',
        cardClass: 'bg-white border border-stone-200 shadow-sm',
        titleClass: 'font-serif tracking-tight',
    },
    modern: {
        label: 'Moderno',
        description: 'Contrastes fuertes, bloques amplios y estilo comercial.',
        pageClass: 'bg-slate-950 text-white',
        cardClass: 'bg-white/10 border border-white/15 shadow-xl backdrop-blur',
        titleClass: 'font-black uppercase tracking-wide',
    },
    minimal: {
        label: 'Minimalista',
        description: 'Diseño claro, mucho aire y texto discreto.',
        pageClass: 'bg-white text-gray-950',
        cardClass: 'bg-white border border-gray-100 shadow-none',
        titleClass: 'font-light tracking-tight',
    },
};

const currentStyle = computed(() => stylePresets[settings.value.style]);

const internalSectionTitle = computed(() => settings.value.sectionTitle?.trim() || 'Productos');
const canSelectExistingProducts = computed(() => props.store?.plan !== 'creador_pdf' && props.products.length > 0);

const itemsPerPageOptions = [1, 2, 4, 6];

const coverBusinessStyles = [
    { value: 'contact-list', label: 'Lista de contacto' },
    { value: 'center-card', label: 'Tarjeta central' },
    { value: 'banner', label: 'Banner profesional' },
];

const logoApplyOptions = [
    { value: 'none', label: 'No mostrar' },
    { value: 'page', label: 'Cada página' },
    { value: 'image', label: 'Cada imagen' },
    { value: 'both', label: 'Ambas' },
];

const logoPositionOptions = [
    { value: 'none', label: 'No mostrar' },
    { value: 'top-left', label: 'Arriba izq.' },
    { value: 'top-center', label: 'Arriba centro' },
    { value: 'top-right', label: 'Arriba der.' },
    { value: 'bottom-left', label: 'Abajo izq.' },
    { value: 'bottom-right', label: 'Abajo der.' },
    { value: 'center', label: 'Centro' },
];

const logoSizes = [
    { value: 'small', label: 'Pequeño' },
    { value: 'medium', label: 'Mediano' },
    { value: 'large', label: 'Grande' },
];

const positionOptions = [
    { value: 'bottom-left', label: 'Abajo izq.' },
    { value: 'bottom-right', label: 'Abajo der.' },
    { value: 'top-left', label: 'Arriba izq.' },
    { value: 'top-right', label: 'Arriba der.' },
];

const businessInfoOptions = [
    { key: 'showBusinessPhone', label: 'WhatsApp', field: 'phone' },
    { key: 'showBusinessEmail', label: 'Email', field: 'email' },
    { key: 'showBusinessAddress', label: 'Dirección', field: 'address' },
    { key: 'showBusinessWebsite', label: 'Web', field: 'custom_domain' },
    { key: 'showBusinessInstagram', label: 'Instagram', field: 'instagram_url' },
    { key: 'showBusinessFacebook', label: 'Facebook', field: 'facebook_url' },
    { key: 'showBusinessTiktok', label: 'TikTok', field: 'tiktok_url' },
];

const updateViewportWidth = () => {
    viewportWidth.value = window.innerWidth;
};

onMounted(() => {
    updateViewportWidth();
    window.addEventListener('resize', updateViewportWidth, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateViewportWidth);
});

const filteredProducts = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.products;

    return props.products.filter((product) => {
        return [product.name, product.category]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term));
    });
});

const productPages = computed(() => {
    const pages = [];
    const itemsPerPage = Number(settings.value.itemsPerPage) || 4;
    for (let i = 0; i < catalogItems.value.length; i += itemsPerPage) {
        pages.push(catalogItems.value.slice(i, i + itemsPerPage));
    }
    return pages;
});

const productGridClass = computed(() => {
    const layouts = {
        1: 'grid-cols-1',
        2: 'grid-cols-1 gap-8',
        4: 'grid-cols-2 gap-6',
        6: 'grid-cols-3 gap-5',
    };

    return layouts[Number(settings.value.itemsPerPage)] || layouts[4];
});

const productImageClass = computed(() => {
    const layouts = {
        1: 'h-[850px]',
        2: 'h-[415px]',
        4: 'h-[390px]',
        6: 'h-[270px]',
    };

    return layouts[Number(settings.value.itemsPerPage)] || layouts[4];
});

const previewScale = computed(() => {
    if (viewportWidth.value >= 1024) return 1;

    const availableWidth = Math.max(280, viewportWidth.value - 32);
    return Math.min(1, Math.max(0.36, availableWidth / 794));
});

const previewFrameStyle = computed(() => ({
    width: `${794 * previewScale.value}px`,
    height: `${1123 * (productPages.value.length + 1) * previewScale.value}px`,
}));

const previewCatalogStyle = computed(() => ({
    transform: `scale(${previewScale.value})`,
    transformOrigin: 'top left',
}));

const formatSocialHandle = (value) => {
    if (!value) return '';
    return String(value)
        .replace(/^https?:\/\//, '')
        .replace(/^www\./, '')
        .replace(/\/$/, '');
};

const storeAddresses = computed(() => [
    props.store?.address,
    props.store?.address_two,
    props.store?.address_three,
    props.store?.address_four,
].filter(Boolean));

const businessContactItems = computed(() => {
    const items = [];

    if (settings.value.showBusinessPhone && props.store?.phone) {
        items.push({ label: 'WhatsApp', type: 'whatsapp', text: props.store.phone });
    }

    if (settings.value.showBusinessEmail && props.store?.email) {
        items.push({ label: 'Email', type: 'email', text: props.store.email });
    }

    if (settings.value.showBusinessAddress && storeAddresses.value.length) {
        storeAddresses.value.forEach((address, index) => {
            items.push({ label: index === 0 ? 'Dirección' : `Sede ${index + 1}`, type: 'address', text: address });
        });
    }

    if (settings.value.showBusinessWebsite && props.store?.custom_domain) {
        items.push({ label: 'Web', type: 'web', text: props.store.custom_domain });
    }

    if (settings.value.showBusinessInstagram && props.store?.instagram_url) {
        items.push({ label: 'Instagram', type: 'instagram', text: formatSocialHandle(props.store.instagram_url) });
    }

    if (settings.value.showBusinessFacebook && props.store?.facebook_url) {
        items.push({ label: 'Facebook', type: 'facebook', text: formatSocialHandle(props.store.facebook_url) });
    }

    if (settings.value.showBusinessTiktok && props.store?.tiktok_url) {
        items.push({ label: 'TikTok', type: 'tiktok', text: formatSocialHandle(props.store.tiktok_url) });
    }

    return items;
});

const availableBusinessInfoOptions = computed(() => businessInfoOptions.filter((option) => {
    if (option.field === 'address') return storeAddresses.value.length > 0;
    return Boolean(props.store?.[option.field]);
}));

const pageLogoSizeClass = computed(() => {
    const sizes = {
        small: 'h-12 w-12',
        medium: 'h-20 w-20',
        large: 'h-28 w-28',
    };

    return sizes[settings.value.logoSize] || sizes.medium;
});

const imageLogoSizeClass = computed(() => {
    const sizes = {
        small: 'h-9 w-9',
        medium: 'h-14 w-14',
        large: 'h-20 w-20',
    };

    return sizes[settings.value.logoSize] || sizes.medium;
});

const pageLogoPositionClass = computed(() => {
    const positions = {
        'top-left': 'top-6 left-6',
        'top-center': 'top-6 left-1/2 -translate-x-1/2',
        'top-right': 'top-6 right-6',
        'bottom-left': 'bottom-6 left-6',
        'bottom-right': 'bottom-6 right-6',
        center: 'left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2',
    };

    return positions[settings.value.logoPosition] || positions['top-right'];
});

const imageLogoPositionClass = computed(() => {
    const positions = {
        'top-left': 'top-4 left-4',
        'top-center': 'top-4 left-1/2 -translate-x-1/2',
        'top-right': 'top-4 right-4',
        'bottom-left': 'bottom-4 left-4',
        'bottom-right': 'bottom-4 right-4',
        center: 'left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2',
    };

    return positions[settings.value.logoPosition] || positions['top-right'];
});

const logoOpacityStyle = computed(() => ({
    opacity: Math.max(5, Math.min(100, Number(settings.value.logoOpacity) || 18)) / 100,
}));

const showPageLogo = computed(() => Boolean(
    props.store?.logo_url && ['page', 'both'].includes(settings.value.logoApplyTo)
));

const showImageLogo = computed(() => Boolean(
    props.store?.logo_url && ['image', 'both'].includes(settings.value.logoApplyTo)
));

const contactIconSvg = (type) => {
    const icons = {
        whatsapp: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.33 4.96L2 22l5.25-1.38a9.88 9.88 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.51 2 12.04 2Zm0 18.16h-.01a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 1 1 6.98 3.85Zm4.49-6.13c-.25-.12-1.46-.72-1.68-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.78.96-.14.17-.29.19-.54.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.46-1.37-1.7-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.76-1.85-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.25 1.05.4 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.46-.6 1.67-1.17.21-.58.21-1.07.14-1.17-.06-.1-.23-.16-.48-.29Z"/></svg>',
        email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        address: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>',
        web: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20"/><path d="M12 2a15.3 15.3 0 0 0 0 20"/></svg>',
        instagram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.5"/><circle cx="17.5" cy="6.5" r=".7" fill="currentColor" stroke="none"/></svg>',
        facebook: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14.2 8.1V6.7c0-.68.45-.84.77-.84h1.96V2.86L14.23 2.85c-3 0-3.68 2.25-3.68 3.69v1.57H8.63v3.15h1.92V21h3.65v-9.74h2.46l.32-3.15H14.2Z"/></svg>',
        tiktok: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 5.82A5.2 5.2 0 0 1 13.8 2h-3.05v12.16a2.55 2.55 0 1 1-1.78-2.43V8.62a5.62 5.62 0 1 0 4.83 5.56V8.02a8.28 8.28 0 0 0 4.85 1.55V6.52c-.72 0-1.42-.25-2.05-.7Z"/></svg>',
    };

    return icons[type] || icons.web;
};

const hasTextOverlay = (item) => {
    return Boolean(String(item.name || '').trim() || String(item.description || '').trim());
};

const hasPriceOverlay = (item) => {
    return item.price !== null && item.price !== undefined && String(item.price).trim() !== '' && Number(item.price) > 0;
};

const formatPrice = (value) => {
    const number = Number(value || 0);
    return `$ ${number.toLocaleString('es-CO')}`;
};

const normalizeFileName = (name) => {
    const safeName = String(name || 'catalogo-productos')
        .trim()
        .replace(/\.pdf$/i, '')
        .replace(/[\\/:*?"<>|]/g, '-')
        .replace(/\s+/g, '-');

    return `${safeName || 'catalogo-productos'}.pdf`;
};

const isSelected = (product) => {
    return catalogItems.value.some((item) => item.source === 'existing' && item.productId === product.id);
};

const addExistingProduct = (product) => {
    if (isSelected(product)) return;

    catalogItems.value.push({
        id: `existing-${product.id}`,
        source: 'existing',
        productId: product.id,
        name: product.name,
        price: product.price ?? 0,
        description: product.description ?? '',
        imageUrl: product.image_url,
    });
};

const removeItem = (index) => {
    catalogItems.value.splice(index, 1);
};

const moveItem = (index, direction) => {
    const nextIndex = index + direction;
    if (nextIndex < 0 || nextIndex >= catalogItems.value.length) return;

    const [item] = catalogItems.value.splice(index, 1);
    catalogItems.value.splice(nextIndex, 0, item);
};

const moveItemTo = (fromIndex, toIndex) => {
    if (fromIndex === null || toIndex === null || fromIndex === toIndex) return;
    if (fromIndex < 0 || toIndex < 0) return;
    if (fromIndex >= catalogItems.value.length || toIndex >= catalogItems.value.length) return;

    const [item] = catalogItems.value.splice(fromIndex, 1);
    catalogItems.value.splice(toIndex, 0, item);
};

const moveItemToPosition = (fromIndex, event) => {
    const requestedPosition = Number(event.target.value);
    const toIndex = Math.max(0, Math.min(catalogItems.value.length - 1, requestedPosition - 1));
    moveItemTo(fromIndex, toIndex);
};

const startDrag = (index) => {
    draggedIndex.value = index;
};

const dropItem = (index) => {
    moveItemTo(draggedIndex.value, index);
    draggedIndex.value = null;
    dragOverIndex.value = null;
};

const endDrag = () => {
    draggedIndex.value = null;
    dragOverIndex.value = null;
};

const openImagePreview = (item) => {
    previewItem.value = item;
};

const closeImagePreview = () => {
    previewItem.value = null;
};

const handleTemporaryPhotos = (event) => {
    const files = Array.from(event.target.files || []);

    files.forEach((file) => {
        catalogItems.value.push({
            id: `temporary-${Date.now()}-${Math.random().toString(16).slice(2)}`,
            source: 'temporary',
            productId: null,
            name: '',
            price: '',
            description: '',
            imageUrl: URL.createObjectURL(file),
        });
    });

    event.target.value = '';
};

const handleCoverImage = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (coverPreview.value) URL.revokeObjectURL(coverPreview.value);
    coverPreview.value = URL.createObjectURL(file);
};

const applyPreset = (style) => {
    settings.value.style = style;

    if (style === 'elegant') {
        settings.value.textColor = '#1F2937';
        settings.value.accentColor = '#92400E';
        settings.value.priceBgColor = '#111827';
        settings.value.priceColor = '#FFFFFF';
    }

    if (style === 'modern') {
        settings.value.textColor = '#FFFFFF';
        settings.value.accentColor = '#38BDF8';
        settings.value.priceBgColor = '#F97316';
        settings.value.priceColor = '#FFFFFF';
    }

    if (style === 'minimal') {
        settings.value.textColor = '#111827';
        settings.value.accentColor = '#111827';
        settings.value.priceBgColor = '#F3F4F6';
        settings.value.priceColor = '#111827';
    }
};

const overlayPositionClass = (position) => {
    const positions = {
        'top-left': 'top-4 left-4 text-left',
        'top-right': 'top-4 right-4 text-right',
        'bottom-left': 'bottom-4 left-4 text-left',
        'bottom-right': 'bottom-4 right-4 text-right',
    };

    return positions[position] || positions['bottom-left'];
};

const generateCatalog = async () => {
    if (!catalogItems.value.length) {
        noticeMessage.value = 'Agrega al menos un producto o una foto para generar el catálogo.';
        showNotice.value = true;
        return;
    }

    if (!catalogRef.value) return;

    isGenerating.value = true;
    try {
        activeTab.value = 'preview';
        await nextTick();

        const originalTransform = catalogRef.value.style.transform;
        const originalTransformOrigin = catalogRef.value.style.transformOrigin;

        try {
            catalogRef.value.style.transform = 'none';
            catalogRef.value.style.transformOrigin = 'top left';
            await downloadPDF(catalogRef.value, normalizeFileName(settings.value.fileName));
        } finally {
            catalogRef.value.style.transform = originalTransform;
            catalogRef.value.style.transformOrigin = originalTransformOrigin;
        }
    } finally {
        isGenerating.value = false;
    }
};
</script>

<template>
    <Head title="Generador PDF" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">Generador de catálogo PDF</h2>
        </template>

        <div class="py-4 sm:py-8">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
                <div class="sticky top-2 z-30 mb-4 sm:mb-6 rounded-2xl border border-gray-200 bg-white/95 p-3 shadow-sm backdrop-blur">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="grid grid-cols-2 rounded-xl bg-gray-100 p-1">
                            <button type="button" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition" :class="activeTab === 'edit' ? 'bg-white text-gray-950 shadow-sm' : 'text-gray-600 hover:text-gray-900'" @click="activeTab = 'edit'">
                                Configurar
                            </button>
                            <button type="button" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition" :class="activeTab === 'preview' ? 'bg-white text-gray-950 shadow-sm' : 'text-gray-600 hover:text-gray-900'" @click="activeTab = 'preview'">
                                Vista previa
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                            <span class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 text-center text-xs font-medium text-gray-600 sm:col-span-1 sm:bg-transparent sm:p-0 sm:text-left sm:text-sm">{{ catalogItems.length }} fotos seleccionadas</span>
                            <button type="button" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 sm:px-4" @click="activeTab = 'preview'">
                                Ver vista previa
                            </button>
                            <button type="button" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:px-5" :disabled="isGenerating" @click="generateCatalog">
                                {{ isGenerating ? 'Generando...' : 'Descargar PDF' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-show="activeTab === 'edit'" class="grid gap-6 xl:grid-cols-[420px,1fr]">
                    <section class="max-h-[calc(100vh-150px)] space-y-4 overflow-y-auto pr-1 sm:space-y-6 lg:pr-2">
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:p-5">
                            <h3 class="text-lg font-semibold text-gray-900">Configuración</h3>
                            <p class="mt-1 text-sm text-gray-500">Define portada, archivo y estilo visual.</p>

                            <div class="mt-5 space-y-4">
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Nombre del PDF</span>
                                    <input v-model="settings.fileName" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="catalogo-junio" />
                                </label>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Título de portada</span>
                                    <input v-model="settings.title" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                                </label>

                                <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-3">
                                    <label class="block">
                                        <span class="text-sm font-semibold text-blue-950">Encabezado interno del catálogo</span>
                                        <input v-model="settings.sectionTitle" type="text" class="mt-2 w-full rounded-lg border-blue-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Productos" />
                                        <span class="mt-1 block text-xs text-blue-800">Este texto reemplaza “Productos” debajo del nombre de la tienda. Si lo dejas vacío, seguirá diciendo “Productos”.</span>
                                    </label>
                                </div>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Subtítulo</span>
                                    <textarea v-model="settings.subtitle" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700">Imagen de portada</span>
                                    <input type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100" @change="handleCoverImage" />
                                </label>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:p-5">
                            <h3 class="text-lg font-semibold text-gray-900">Estilo</h3>
                            <div class="mt-4 grid gap-3">
                                <button v-for="(preset, key) in stylePresets" :key="key" type="button" class="rounded-xl border p-4 text-left transition hover:border-blue-300" :class="settings.style === key ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white'" @click="applyPreset(key)">
                                    <span class="block font-semibold text-gray-900">{{ preset.label }}</span>
                                    <span class="mt-1 block text-sm text-gray-500">{{ preset.description }}</span>
                                </button>
                            </div>

                            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Texto
                                    <input v-model="settings.textColor" type="color" class="mt-1 h-10 w-full rounded border border-gray-200" />
                                </label>
                                <label class="block text-sm font-medium text-gray-700">
                                    Acento
                                    <input v-model="settings.accentColor" type="color" class="mt-1 h-10 w-full rounded border border-gray-200" />
                                </label>
                                <label class="block text-sm font-medium text-gray-700">
                                    Fondo precio
                                    <input v-model="settings.priceBgColor" type="color" class="mt-1 h-10 w-full rounded border border-gray-200" />
                                </label>
                                <label class="block text-sm font-medium text-gray-700">
                                    Texto precio
                                    <input v-model="settings.priceColor" type="color" class="mt-1 h-10 w-full rounded border border-gray-200" />
                                </label>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <span class="block text-sm font-medium text-gray-700">Posición texto</span>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button v-for="option in positionOptions" :key="`text-${option.value}`" type="button" class="rounded-xl border px-3 py-2.5 text-xs font-semibold transition" :class="settings.textPosition === option.value ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300'" @click="settings.textPosition = option.value">
                                            {{ option.label }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <span class="block text-sm font-medium text-gray-700">Posición precio</span>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button v-for="option in positionOptions" :key="`price-${option.value}`" type="button" class="rounded-xl border px-3 py-2.5 text-xs font-semibold transition" :class="settings.pricePosition === option.value ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300'" @click="settings.pricePosition = option.value">
                                            {{ option.label }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <span class="block text-sm font-medium text-gray-700">Fotos por página</span>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <button v-for="option in itemsPerPageOptions" :key="option" type="button" class="rounded-xl border px-3 py-3 text-sm font-semibold transition" :class="settings.itemsPerPage === option ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300'" @click="settings.itemsPerPage = option">
                                        {{ option }} {{ option === 1 ? 'foto' : 'fotos' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:p-5">
                            <h3 class="text-lg font-semibold text-gray-900">Información del negocio en portada</h3>
                            <p class="mt-1 text-sm text-gray-500">Usa los datos configurados en Perfil para mostrar contacto en la portada.</p>

                            <label class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">Mostrar datos del negocio</span>
                                    <span class="text-xs text-gray-500">Si está apagado, la portada queda limpia como ahora.</span>
                                </span>
                                <input v-model="settings.showBusinessInfoOnCover" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            </label>

                            <div v-if="settings.showBusinessInfoOnCover" class="mt-4 space-y-4">
                                <div>
                                    <span class="block text-sm font-medium text-gray-700">Diseño de información</span>
                                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                        <button v-for="style in coverBusinessStyles" :key="style.value" type="button" class="rounded-xl border px-3 py-3 text-sm font-semibold transition" :class="settings.coverBusinessStyle === style.value ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300'" @click="settings.coverBusinessStyle = style.value">
                                            {{ style.label }}
                                        </button>
                                    </div>
                                </div>

                                <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-3">
                                    <span class="text-sm font-medium text-gray-700">Mostrar logo en portada</span>
                                    <input v-model="settings.showCoverLogo" type="checkbox" :disabled="!store.logo_url" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-40" />
                                </label>

                                <div>
                                    <span class="block text-sm font-medium text-gray-700">Datos visibles</span>
                                    <div v-if="availableBusinessInfoOptions.length" class="mt-2 grid grid-cols-2 gap-2">
                                        <label v-for="option in availableBusinessInfoOptions" :key="option.key" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700">
                                            <input v-model="settings[option.key]" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            {{ option.label }}
                                        </label>
                                    </div>
                                    <p v-else class="mt-2 rounded-xl bg-yellow-50 p-3 text-sm text-yellow-800">No hay datos de contacto cargados. Puedes agregarlos desde Perfil.</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:p-5">
                            <h3 class="text-lg font-semibold text-gray-900">Logo del catálogo</h3>
                            <p class="mt-1 text-sm text-gray-500">Muestra el logo en cada página, en cada imagen o en ambas. Usa “Centro” con baja transparencia como marca de agua.</p>

                            <div v-if="store.logo_url" class="mt-4 space-y-4">
                                <div>
                                    <span class="block text-sm font-medium text-gray-700">Aplicar logo en</span>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button v-for="option in logoApplyOptions" :key="option.value" type="button" class="rounded-xl border px-3 py-2.5 text-xs font-semibold transition" :class="settings.logoApplyTo === option.value ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300'" @click="settings.logoApplyTo = option.value">
                                            {{ option.label }}
                                        </button>
                                    </div>
                                </div>

                                <div v-if="settings.logoApplyTo !== 'none'" class="space-y-4">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-700">Posición</span>
                                        <div class="mt-2 grid grid-cols-2 gap-2">
                                            <button v-for="position in logoPositionOptions.filter((option) => option.value !== 'none')" :key="position.value" type="button" class="rounded-xl border px-3 py-2.5 text-xs font-semibold transition" :class="settings.logoPosition === position.value ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300'" @click="settings.logoPosition = position.value">
                                                {{ position.label }}
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="block text-sm font-medium text-gray-700">Tamaño</span>
                                        <div class="mt-2 grid grid-cols-3 gap-2">
                                            <button v-for="size in logoSizes" :key="size.value" type="button" class="rounded-xl border px-3 py-2.5 text-xs font-semibold transition" :class="settings.logoSize === size.value ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300'" @click="settings.logoSize = size.value">
                                                {{ size.label }}
                                            </button>
                                        </div>
                                    </div>

                                    <label class="block text-sm font-medium text-gray-700">
                                        Transparencia: {{ settings.logoOpacity }}%
                                        <input v-model.number="settings.logoOpacity" type="range" min="5" max="100" step="1" class="mt-2 w-full accent-blue-600" />
                                    </label>
                                </div>
                            </div>

                            <p v-else class="mt-4 rounded-xl bg-yellow-50 p-3 text-sm text-yellow-800">Carga un logo desde Perfil para usar esta opción.</p>
                        </div>

                        <div v-if="canSelectExistingProducts" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:p-5">
                            <h3 class="text-lg font-semibold text-gray-900">Productos existentes</h3>
                            <input v-model="search" type="search" class="mt-4 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Buscar por nombre o categoría" />

                            <div class="mt-4 max-h-80 space-y-2 overflow-y-auto pr-1 sm:max-h-72">
                                <button v-for="product in filteredProducts" :key="product.id" type="button" class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition hover:border-blue-300" :class="isSelected(product) ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'" @click="addExistingProduct(product)">
                                    <img :src="product.image_url" :alt="product.name" class="h-12 w-12 rounded-lg object-cover bg-gray-100" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-gray-900">{{ product.name }}</span>
                                        <span class="block text-xs text-gray-500">{{ formatPrice(product.price) }}</span>
                                    </span>
                                    <span class="text-xs font-medium" :class="isSelected(product) ? 'text-green-700' : 'text-blue-700'">{{ isSelected(product) ? 'Agregado' : 'Agregar' }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:p-5">
                            <h3 class="text-lg font-semibold text-gray-900">Fotos nuevas temporales</h3>
                            <p class="mt-1 text-sm text-gray-500">Estas fotos solo se usan para este PDF. No se guardan como productos.</p>
                            <input type="file" accept="image/*" multiple class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700" @change="handleTemporaryPhotos" />
                        </div>
                    </section>

                    <section class="space-y-4 sm:space-y-6">
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Productos del PDF</h3>
                                    <p class="text-sm text-gray-500">Edita textos, precios y orden antes de descargar.</p>
                                </div>
                            </div>

                            <div v-if="!catalogItems.length" class="mt-5 rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                                Agrega productos existentes o sube fotos nuevas para iniciar el catálogo.
                            </div>

                            <div v-else class="mt-5 space-y-3 sm:max-h-[calc(100vh-220px)] sm:overflow-y-auto sm:pr-2">
                                <article
                                    v-for="(item, index) in catalogItems"
                                    :key="item.id"
                                    class="grid grid-cols-[72px,1fr] gap-3 rounded-xl border bg-gray-50 p-3 transition md:grid-cols-[88px,1fr,auto]"
                                    :class="dragOverIndex === index ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200'"
                                    @dragenter.prevent="dragOverIndex = index"
                                    @dragover.prevent="dragOverIndex = index"
                                    @drop.prevent="dropItem(index)"
                                    @dragend="endDrag"
                                >
                                    <div class="space-y-2">
                                        <button type="button" class="block rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @click="openImagePreview(item)">
                                            <img :src="item.imageUrl" :alt="item.name || 'Imagen del producto'" class="h-[72px] w-[72px] rounded-lg object-cover bg-white sm:h-24 sm:w-24" />
                                        </button>
                                        <div draggable="true" class="flex items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 bg-white px-2 py-1 text-[11px] font-semibold text-gray-500 cursor-grab active:cursor-grabbing" title="Arrastra para reordenar" @dragstart.stop="startDrag(index)" @dragend="endDrag">
                                            <span aria-hidden="true">↕</span>
                                            <span>Mover</span>
                                        </div>
                                        <label class="block text-center text-[11px] font-semibold text-gray-500">
                                            Pos.
                                            <input type="number" min="1" :max="catalogItems.length" :value="index + 1" class="mt-1 w-full rounded-lg border-gray-300 px-1 py-1 text-center text-xs" @change="moveItemToPosition(index, $event)" />
                                        </label>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <label class="block text-xs font-medium text-gray-600">
                                            Nombre
                                            <input v-model="item.name" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                                        </label>
                                        <label class="block text-xs font-medium text-gray-600">
                                            Precio
                                            <input v-model="item.price" type="number" min="0" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                                        </label>
                                        <label class="block text-xs font-medium text-gray-600 md:col-span-2">
                                            Descripción
                                            <textarea v-model="item.description" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                                        </label>
                                    </div>
                                    <div class="col-span-2 grid grid-cols-3 gap-2 md:col-span-1 md:flex md:flex-col md:items-stretch md:justify-center">
                                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 disabled:opacity-40 md:py-1" :disabled="index === 0" @click="moveItem(index, -1)">Subir</button>
                                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 disabled:opacity-40 md:py-1" :disabled="index === catalogItems.length - 1" @click="moveItem(index, 1)">Bajar</button>
                                        <button type="button" class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 md:py-1" @click="removeItem(index)">Quitar</button>
                                    </div>
                                </article>
                            </div>
                        </div>

                    </section>
                </div>

                <div v-show="activeTab === 'preview'" class="rounded-2xl bg-slate-100 p-3 shadow-inner sm:p-4">
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Vista previa</h3>
                            <p class="text-xs text-gray-500">{{ catalogItems.length }} fotos, {{ settings.itemsPerPage }} por página</p>
                            <p class="mt-1 text-xs text-gray-500 sm:hidden">La vista previa se ajusta al ancho de tu pantalla.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="activeTab = 'edit'">
                            Volver a configurar
                        </button>
                    </div>

                    <div class="mx-auto overflow-hidden rounded-xl" :style="previewFrameStyle">
                    <div ref="catalogRef" class="w-[794px] origin-top-left overflow-hidden bg-white text-gray-900 shadow-xl" :style="previewCatalogStyle">
                        <section class="relative flex h-[1123px] flex-col justify-between overflow-hidden p-14" :class="currentStyle.pageClass">
                            <img v-if="coverPreview" :src="coverPreview" alt="Portada" class="absolute inset-0 h-full w-full object-cover opacity-35" />
                            <div class="relative z-10 flex items-center gap-3">
                                <img v-if="store.logo_url" :src="store.logo_url" :alt="store.name" class="h-14 w-14 rounded-full bg-white object-cover p-1" />
                                <span class="text-sm font-semibold uppercase tracking-[0.35em]" :style="{ color: settings.accentColor }">{{ store.name }}</span>
                            </div>
                            <div class="relative z-10 max-w-2xl">
                                <h1 class="text-6xl leading-tight" :class="currentStyle.titleClass" :style="{ color: settings.textColor }">{{ settings.title }}</h1>
                                <p class="mt-6 max-w-xl text-2xl leading-relaxed" :style="{ color: settings.textColor }">{{ settings.subtitle }}</p>
                            </div>

                            <div v-if="settings.showBusinessInfoOnCover && businessContactItems.length" class="relative z-10">
                                <div v-if="settings.coverBusinessStyle === 'contact-list'" class="mx-auto max-w-xl space-y-3 rounded-[2rem] bg-white/90 p-7 shadow-xl">
                                    <img v-if="settings.showCoverLogo && store.logo_url" :src="store.logo_url" :alt="store.name" class="mx-auto mb-4 h-28 w-28 rounded-3xl object-contain" />
                                    <h3 class="text-center text-2xl font-black" :style="{ color: settings.textColor }">{{ store.name }}</h3>
                                    <div class="space-y-2">
                                        <div v-for="item in businessContactItems" :key="`${item.label}-${item.text}`" class="flex items-center gap-3 rounded-xl bg-gray-100 px-4 py-2 text-sm font-bold text-gray-800">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full p-1.5 text-white [&>svg]:h-full [&>svg]:w-full" :style="{ backgroundColor: settings.accentColor }" v-html="contactIconSvg(item.type)"></span>
                                            <span class="truncate">{{ item.text }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="settings.coverBusinessStyle === 'center-card'" class="mx-auto max-w-2xl rounded-[2rem] bg-white/85 p-8 text-center shadow-xl backdrop-blur">
                                    <img v-if="settings.showCoverLogo && store.logo_url" :src="store.logo_url" :alt="store.name" class="mx-auto mb-5 h-32 w-32 rounded-full object-contain bg-white p-2" />
                                    <h3 class="text-3xl font-black" :style="{ color: settings.textColor }">{{ store.name }}</h3>
                                    <div class="mt-6 grid grid-cols-2 gap-3 text-left">
                                        <div v-for="item in businessContactItems" :key="`${item.label}-${item.text}`" class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                                            <p class="text-[10px] font-black uppercase tracking-widest" :style="{ color: settings.accentColor }">{{ item.label }}</p>
                                            <div class="mt-1 flex items-center gap-2">
                                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full p-1 text-white [&>svg]:h-full [&>svg]:w-full" :style="{ backgroundColor: settings.accentColor }" v-html="contactIconSvg(item.type)"></span>
                                                <p class="truncate text-sm font-semibold text-gray-800">{{ item.text }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="flex items-center gap-8 rounded-[2rem] bg-white/90 p-7 shadow-xl">
                                    <img v-if="settings.showCoverLogo && store.logo_url" :src="store.logo_url" :alt="store.name" class="h-32 w-32 shrink-0 rounded-3xl object-contain bg-white p-2" />
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-3xl font-black" :style="{ color: settings.textColor }">{{ store.name }}</h3>
                                        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-2">
                                            <div v-for="item in businessContactItems" :key="`${item.label}-${item.text}`" class="min-w-0 text-sm">
                                                <span class="inline-flex h-6 w-6 align-middle items-center justify-center rounded-full p-1 text-white [&>svg]:h-full [&>svg]:w-full" :style="{ backgroundColor: settings.accentColor }" v-html="contactIconSvg(item.type)"></span>
                                                <span class="ml-1 font-semibold text-gray-800">{{ item.text }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="relative z-10 h-2 w-40 rounded-full" :style="{ backgroundColor: settings.accentColor }"></div>
                        </section>

                        <section v-for="(page, pageIndex) in productPages" :key="pageIndex" class="relative min-h-[1123px] overflow-hidden p-10" :class="currentStyle.pageClass">
                            <img v-if="showPageLogo" :src="store.logo_url" :alt="store.name" class="pointer-events-none absolute z-10 object-contain" :class="[pageLogoPositionClass, settings.logoPosition === 'center' ? 'h-96 w-96' : pageLogoSizeClass]" :style="logoOpacityStyle" />
                            <div class="mb-8 flex items-end justify-between border-b pb-4" :style="{ borderColor: settings.accentColor }">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.25em]" :style="{ color: settings.accentColor }">{{ store.name }}</p>
                                    <h2 class="mt-1 text-3xl font-semibold" :style="{ color: settings.textColor }">{{ internalSectionTitle }}</h2>
                                </div>
                                <span class="text-sm opacity-70">Página {{ pageIndex + 2 }}</span>
                            </div>

                            <div class="grid" :class="productGridClass">
                                <article v-for="item in page" :key="item.id" class="overflow-hidden rounded-3xl" :class="currentStyle.cardClass">
                                    <div class="relative overflow-hidden bg-white" :class="productImageClass">
                                        <img :src="item.imageUrl" :alt="item.name || 'Producto del catálogo'" class="h-full w-full object-contain" />
                                        <img v-if="showImageLogo" :src="store.logo_url" :alt="store.name" class="pointer-events-none absolute z-10 object-contain" :class="[imageLogoPositionClass, settings.logoPosition === 'center' ? 'h-32 w-32' : imageLogoSizeClass]" :style="logoOpacityStyle" />
                                        <div v-if="hasPriceOverlay(item)" class="absolute rounded-2xl px-4 py-2 text-xl font-black shadow-lg" :class="overlayPositionClass(settings.pricePosition)" :style="{ backgroundColor: settings.priceBgColor, color: settings.priceColor }">
                                            {{ formatPrice(item.price) }}
                                        </div>
                                        <div v-if="hasTextOverlay(item)" class="absolute max-w-[78%] rounded-2xl bg-black/50 p-4 text-white backdrop-blur-sm" :class="overlayPositionClass(settings.textPosition)">
                                            <h3 v-if="item.name" class="text-2xl font-bold leading-tight">{{ item.name }}</h3>
                                            <p v-if="item.description" class="mt-2 line-clamp-3 text-sm leading-relaxed opacity-90">{{ item.description }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="Boolean(previewItem)" max-width="4xl" @close="closeImagePreview">
            <div class="bg-white p-4 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Vista de imagen</h3>
                        <p class="mt-1 text-sm text-gray-500">Revisa que esta sea la foto correcta antes de generar el PDF.</p>
                    </div>
                    <button type="button" class="rounded-full border border-gray-200 px-3 py-1 text-sm font-semibold text-gray-600 hover:bg-gray-50" @click="closeImagePreview">
                        Cerrar
                    </button>
                </div>

                <div class="mt-5 rounded-2xl bg-gray-100 p-3">
                    <img v-if="previewItem" :src="previewItem.imageUrl" :alt="previewItem.name || 'Imagen ampliada'" class="mx-auto max-h-[70vh] w-auto max-w-full rounded-xl object-contain" />
                </div>

                <p class="mt-4 text-xs text-gray-500">
                    Nota: por ahora esta vista permite revisar la imagen. El recorte manual se puede agregar como una mejora posterior.
                </p>
            </div>
        </Modal>

        <AlertModal :show="showNotice" type="warning" title="Catálogo incompleto" :message="noticeMessage" @close="showNotice = false" @primary="showNotice = false" />
    </AuthenticatedLayout>
</template>
