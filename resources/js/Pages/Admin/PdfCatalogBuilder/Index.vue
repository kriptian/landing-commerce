<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AlertModal from '@/Components/AlertModal.vue';
import { downloadPDF } from '@/Utils/pdfUtils';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
const isGenerating = ref(false);
const showNotice = ref(false);
const noticeMessage = ref('');
const coverPreview = ref('');
const catalogItems = ref([]);

const settings = ref({
    fileName: 'catalogo-productos',
    title: props.store?.name ? `Catálogo ${props.store.name}` : 'Catálogo de productos',
    subtitle: 'Productos seleccionados para ti',
    style: 'elegant',
    textColor: '#111827',
    accentColor: '#2563EB',
    priceColor: '#FFFFFF',
    priceBgColor: '#111827',
    textPosition: 'bottom-left',
    pricePosition: 'top-right',
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
    for (let i = 0; i < catalogItems.value.length; i += 4) {
        pages.push(catalogItems.value.slice(i, i + 4));
    }
    return pages;
});

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

const handleTemporaryPhotos = (event) => {
    const files = Array.from(event.target.files || []);

    files.forEach((file) => {
        catalogItems.value.push({
            id: `temporary-${Date.now()}-${Math.random().toString(16).slice(2)}`,
            source: 'temporary',
            productId: null,
            name: file.name.replace(/\.[^.]+$/, ''),
            price: 0,
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
        await downloadPDF(catalogRef.value, normalizeFileName(settings.value.fileName));
    } finally {
        isGenerating.value = false;
    }
};
</script>

<template>
    <Head title="Generador PDF" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Generador de catálogo PDF</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 xl:grid-cols-[420px,1fr]">
                    <section class="space-y-6">
                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
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

                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">Estilo</h3>
                            <div class="mt-4 grid gap-3">
                                <button v-for="(preset, key) in stylePresets" :key="key" type="button" class="rounded-xl border p-4 text-left transition hover:border-blue-300" :class="settings.style === key ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white'" @click="applyPreset(key)">
                                    <span class="block font-semibold text-gray-900">{{ preset.label }}</span>
                                    <span class="mt-1 block text-sm text-gray-500">{{ preset.description }}</span>
                                </button>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3">
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

                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <label class="block text-sm font-medium text-gray-700">
                                    Posición texto
                                    <select v-model="settings.textPosition" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                        <option value="bottom-left">Abajo izquierda</option>
                                        <option value="bottom-right">Abajo derecha</option>
                                        <option value="top-left">Arriba izquierda</option>
                                        <option value="top-right">Arriba derecha</option>
                                    </select>
                                </label>
                                <label class="block text-sm font-medium text-gray-700">
                                    Posición precio
                                    <select v-model="settings.pricePosition" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                        <option value="top-right">Arriba derecha</option>
                                        <option value="top-left">Arriba izquierda</option>
                                        <option value="bottom-right">Abajo derecha</option>
                                        <option value="bottom-left">Abajo izquierda</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">Productos existentes</h3>
                            <input v-model="search" type="search" class="mt-4 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Buscar por nombre o categoría" />

                            <div class="mt-4 max-h-72 space-y-2 overflow-y-auto pr-1">
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

                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">Fotos nuevas temporales</h3>
                            <p class="mt-1 text-sm text-gray-500">Estas fotos solo se usan para este PDF. No se guardan como productos.</p>
                            <input type="file" accept="image/*" multiple class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700" @change="handleTemporaryPhotos" />
                        </div>
                    </section>

                    <section class="space-y-6">
                        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Productos del PDF</h3>
                                    <p class="text-sm text-gray-500">Edita textos, precios y orden antes de descargar.</p>
                                </div>
                                <button type="button" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="isGenerating" @click="generateCatalog">
                                    {{ isGenerating ? 'Generando...' : 'Descargar PDF' }}
                                </button>
                            </div>

                            <div v-if="!catalogItems.length" class="mt-5 rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                                Agrega productos existentes o sube fotos nuevas para iniciar el catálogo.
                            </div>

                            <div v-else class="mt-5 space-y-3">
                                <article v-for="(item, index) in catalogItems" :key="item.id" class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 md:grid-cols-[88px,1fr,auto]">
                                    <img :src="item.imageUrl" :alt="item.name" class="h-24 w-24 rounded-lg object-cover bg-white" />
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
                                    <div class="flex items-center justify-end gap-2 md:flex-col">
                                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 disabled:opacity-40" :disabled="index === 0" @click="moveItem(index, -1)">Subir</button>
                                        <button type="button" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 disabled:opacity-40" :disabled="index === catalogItems.length - 1" @click="moveItem(index, 1)">Bajar</button>
                                        <button type="button" class="rounded-lg bg-red-50 px-3 py-1 text-xs font-semibold text-red-700" @click="removeItem(index)">Quitar</button>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-slate-100 p-4 shadow-inner">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Vista previa</h3>
                                <span class="text-xs text-gray-500">{{ catalogItems.length }} productos</span>
                            </div>

                            <div ref="catalogRef" class="mx-auto w-[794px] max-w-full origin-top overflow-hidden bg-white text-gray-900 shadow-xl">
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
                                    <div class="relative z-10 h-2 w-40 rounded-full" :style="{ backgroundColor: settings.accentColor }"></div>
                                </section>

                                <section v-for="(page, pageIndex) in productPages" :key="pageIndex" class="min-h-[1123px] p-10" :class="currentStyle.pageClass">
                                    <div class="mb-8 flex items-end justify-between border-b pb-4" :style="{ borderColor: settings.accentColor }">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.25em]" :style="{ color: settings.accentColor }">{{ store.name }}</p>
                                            <h2 class="mt-1 text-3xl font-semibold" :style="{ color: settings.textColor }">Productos</h2>
                                        </div>
                                        <span class="text-sm opacity-70">Página {{ pageIndex + 2 }}</span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-6">
                                        <article v-for="item in page" :key="item.id" class="overflow-hidden rounded-3xl" :class="currentStyle.cardClass">
                                            <div class="relative h-[390px] overflow-hidden bg-gray-100">
                                                <img :src="item.imageUrl" :alt="item.name" class="h-full w-full object-cover" />
                                                <div class="absolute rounded-2xl px-4 py-2 text-xl font-black shadow-lg" :class="overlayPositionClass(settings.pricePosition)" :style="{ backgroundColor: settings.priceBgColor, color: settings.priceColor }">
                                                    {{ formatPrice(item.price) }}
                                                </div>
                                                <div class="absolute max-w-[78%] rounded-2xl bg-black/50 p-4 text-white backdrop-blur-sm" :class="overlayPositionClass(settings.textPosition)">
                                                    <h3 class="text-2xl font-bold leading-tight">{{ item.name }}</h3>
                                                    <p v-if="item.description" class="mt-2 line-clamp-3 text-sm leading-relaxed opacity-90">{{ item.description }}</p>
                                                </div>
                                            </div>
                                        </article>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <AlertModal :show="showNotice" type="warning" title="Catálogo incompleto" :message="noticeMessage" @close="showNotice = false" @primary="showNotice = false" />
    </AuthenticatedLayout>
</template>
