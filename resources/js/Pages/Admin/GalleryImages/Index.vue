<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    galleryImages: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const editingImage = ref(null);
const deletingImage = ref(null);
const productSearchQuery = ref('');

const imageForm = useForm({
    _method: 'PUT', // Incluir _method para PUT requests
    media_type: 'image',
    image: null,
    video: null,
    title: '',
    description: '',
    product_id: null,
    show_buy_button: true,
    order: 0,
    is_active: true,
});

const openAddModal = () => {
    imageForm.reset();
    imageForm.order = props.galleryImages?.length || 0;
    showAddModal.value = true;
};

const openEditModal = (image) => {
    editingImage.value = image;
    // Asegurar que media_type sea un string válido
    imageForm.media_type = String(image.media_type || 'image');
    imageForm.title = image.title || '';
    imageForm.description = image.description || '';
    imageForm.product_id = image.product_id || null;
    imageForm.show_buy_button = image.show_buy_button !== undefined ? Boolean(image.show_buy_button) : true;
    imageForm.order = image.order || 0;
    imageForm.is_active = image.is_active !== undefined ? Boolean(image.is_active) : true;
    imageForm.image = null; // No pre-cargar la imagen
    imageForm.video = null; // No pre-cargar el video
    const selectedProduct = props.products.find(p => p.id === image.product_id);
    productSearchQuery.value = selectedProduct ? selectedProduct.name : '';
    showEditModal.value = true;
};

const openDeleteModal = (image) => {
    deletingImage.value = image;
    showDeleteModal.value = true;
};

const submitAdd = () => {
    // Validar que si es video, tenga archivo
    if (imageForm.media_type === 'video' && !imageForm.video) {
        alert('Debes seleccionar un archivo de video para continuar.');
        return;
    }
    
    // Validar que si es imagen, tenga archivo
    if (imageForm.media_type === 'image' && !imageForm.image) {
        alert('Debes seleccionar una imagen para continuar.');
        return;
    }

    // Preparar datos para enviar - convertir booleanos a strings para FormData
    const dataToSend = {
        media_type: String(imageForm.media_type || 'image'),
        title: imageForm.title || '',
        description: imageForm.description || '',
        show_buy_button: imageForm.show_buy_button ? '1' : '0',
        is_active: imageForm.is_active ? '1' : '0',
        order: String(imageForm.order || 0),
    };
    
    // Solo incluir product_id si tiene valor
    if (imageForm.product_id) {
        dataToSend.product_id = String(imageForm.product_id);
    }
    
    // Incluir archivos (requeridos)
    if (imageForm.image) {
        dataToSend.image = imageForm.image;
    }
    if (imageForm.video) {
        dataToSend.video = imageForm.video;
    }
    
    // Usar router.post directamente con los datos
    router.post(route('admin.gallery-images.store'), dataToSend, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            imageForm.reset();
            productSearchQuery.value = '';
        },
        onError: (errors) => {
            if (errors.video) {
                alert('Error con el video: ' + (Array.isArray(errors.video) ? errors.video[0] : errors.video));
            } else if (errors.image) {
                alert('Error con la imagen: ' + (Array.isArray(errors.image) ? errors.image[0] : errors.image));
            } else if (errors.error) {
                alert('Error: ' + (Array.isArray(errors.error) ? errors.error[0] : errors.error));
            } else {
                alert('Error al guardar el elemento. Por favor, verifica los datos e intenta nuevamente.');
            }
        },
    });
};

const submitEdit = () => {
    // Asegurar que media_type siempre tenga un valor válido
    const mediaType = String(imageForm.media_type || editingImage.value?.media_type || 'image');
    
    // Preparar datos para enviar - convertir booleanos a strings para FormData
    const dataToSend = {
        _method: 'PUT',
        media_type: mediaType,
        title: imageForm.title || '',
        description: imageForm.description || '',
        show_buy_button: imageForm.show_buy_button ? '1' : '0',
        is_active: imageForm.is_active ? '1' : '0',
        order: String(imageForm.order || 0),
    };
    
    // Solo incluir product_id si tiene valor
    if (imageForm.product_id) {
        dataToSend.product_id = String(imageForm.product_id);
    }
    
    // Solo incluir archivos si están presentes
    if (imageForm.image) {
        dataToSend.image = imageForm.image;
    }
    if (imageForm.video) {
        dataToSend.video = imageForm.video;
    }
    
    // Usar router.post directamente con los datos
    router.post(route('admin.gallery-images.update', editingImage.value.id), dataToSend, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingImage.value = null;
            imageForm.reset();
            productSearchQuery.value = '';
        },
        onError: (errors) => {
            if (errors.media_type) {
                alert('Error: El campo tipo de media es requerido. Por favor, selecciona Imagen o Video.');
            } else if (errors.video) {
                alert('Error con el video: ' + (Array.isArray(errors.video) ? errors.video[0] : errors.video));
            } else if (errors.error) {
                alert('Error: ' + (Array.isArray(errors.error) ? errors.error[0] : errors.error));
            } else {
                alert('Error al actualizar el elemento. Por favor, verifica los datos e intenta nuevamente.');
            }
        },
    });
};

const deleteImage = () => {
    router.delete(route('admin.gallery-images.destroy', deletingImage.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingImage.value = null;
        },
    });
};

const handleImageChange = (event) => {
    imageForm.image = event.target.files[0];
};

const handleVideoChange = (event) => {
    imageForm.video = event.target.files[0];
};

// Buscador de productos
const filteredProducts = computed(() => {
    if (!productSearchQuery.value) {
        return props.products.slice(0, 10); // Mostrar primeros 10 si no hay búsqueda
    }
    const query = productSearchQuery.value.toLowerCase();
    return props.products.filter(product => 
        product.name.toLowerCase().includes(query)
    ).slice(0, 10);
});

const selectedProductName = computed(() => {
    if (!imageForm.product_id) return '';
    const product = props.products.find(p => p.id === imageForm.product_id);
    return product ? product.name : '';
});
</script>

<template>
    <Head title="Galería de Imágenes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Galería de Imágenes
                </h2>
                <Link 
                    :href="route('admin.catalog-customization.index')"
                    class="text-sm text-gray-600 hover:text-gray-800"
                >
                    ← Volver a Personalización
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="mb-6 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Imágenes de la Galería</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Gestiona las imágenes que se mostrarán en la galería principal del catálogo.
                                </p>
                            </div>
                            <PrimaryButton @click="openAddModal">
                                + Agregar Elemento
                            </PrimaryButton>
                        </div>

                        <div v-if="!galleryImages || galleryImages.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-4 text-sm text-gray-500">No hay elementos en la galería</p>
                            <PrimaryButton @click="openAddModal" class="mt-4">
                                Agregar Primer Elemento
                            </PrimaryButton>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="image in galleryImages" 
                                :key="image.id"
                                class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow"
                                :class="{ 'opacity-50': !image.is_active }"
                            >
                                <div class="relative h-48 bg-gray-100">
                                    <!-- Imagen -->
                                    <img 
                                        v-if="image.media_type === 'image' || !image.media_type"
                                        :src="image.image_url" 
                                        :alt="image.title || 'Imagen de galería'"
                                        class="w-full h-full object-cover"
                                    >
                                    <!-- Video local -->
                                    <video 
                                        v-else-if="image.media_type === 'video' && image.video_url && !image.video_url.startsWith('http')"
                                        :src="image.video_url"
                                        class="w-full h-full object-cover"
                                        muted
                                    ></video>
                                    <!-- Video embebido (YouTube, Vimeo, etc.) -->
                                    <div 
                                        v-else-if="image.media_type === 'video' && image.video_url && image.video_url.startsWith('http')"
                                        class="w-full h-full flex items-center justify-center bg-gray-800"
                                    >
                                        <div class="text-white text-sm text-center px-4">
                                            <svg class="w-12 h-12 mx-auto mb-2" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                            <p>Video Externo</p>
                                            <p class="text-xs mt-1 opacity-75 truncate">{{ image.video_url }}</p>
                                        </div>
                                    </div>
                                    <div v-if="!image.is_active" class="absolute top-2 right-2 bg-gray-600 text-white text-xs px-2 py-1 rounded">
                                        Inactiva
                                    </div>
                                    <div v-if="image.media_type === 'video'" class="absolute top-2 left-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                        Video
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h4 class="font-semibold text-gray-900 mb-1">
                                        {{ image.title || 'Sin título' }}
                                    </h4>
                                    <p v-if="image.description" class="text-sm text-gray-600 mb-2 line-clamp-2">
                                        {{ image.description }}
                                    </p>
                                    <div class="text-xs text-gray-500 space-y-1">
                                        <p v-if="image.product">
                                            <strong>Producto:</strong> {{ image.product.name }}
                                        </p>
                                        <p>
                                            <strong>Botón comprar:</strong> {{ image.show_buy_button ? 'Sí' : 'No' }}
                                        </p>
                                        <p>
                                            <strong>Orden:</strong> {{ image.order }}
                                        </p>
                                    </div>
                                    <div class="mt-4 flex gap-2">
                                        <SecondaryButton @click="openEditModal(image)" class="flex-1 text-sm">
                                            Editar
                                        </SecondaryButton>
                                        <DangerButton @click="openDeleteModal(image)" class="flex-1 text-sm">
                                            Eliminar
                                        </DangerButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Agregar -->
        <Modal :show="showAddModal" @close="showAddModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Agregar Elemento a la Galería</h3>
                <form @submit.prevent="submitAdd">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Media *
                            </label>
                            <div class="flex gap-4">
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        v-model="imageForm.media_type"
                                        value="image"
                                        class="mr-2"
                                    />
                                    <span class="text-sm text-gray-700">Imagen</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        v-model="imageForm.media_type"
                                        value="video"
                                        class="mr-2"
                                    />
                                    <span class="text-sm text-gray-700">Video</span>
                                </label>
                            </div>
                        </div>

                        <!-- Campo de imagen -->
                        <div v-if="imageForm.media_type === 'image'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Imagen *
                            </label>
                            <input 
                                type="file" 
                                @change="handleImageChange"
                                accept="image/*"
                                :required="imageForm.media_type === 'image'"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                        </div>

                        <!-- Campo de video -->
                        <div v-if="imageForm.media_type === 'video'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Subir Video *
                            </label>
                            <input 
                                type="file" 
                                @change="handleVideoChange"
                                accept="video/*"
                                :required="imageForm.media_type === 'video'"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Formatos: MP4, WebM, OGG (máx. 50MB)
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Título
                            </label>
                            <input 
                                v-model="imageForm.title"
                                type="text"
                                class="block w-full rounded-md border-gray-300 shadow-sm"
                                placeholder="Título de la imagen"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Descripción
                            </label>
                            <textarea 
                                v-model="imageForm.description"
                                rows="3"
                                class="block w-full rounded-md border-gray-300 shadow-sm"
                                placeholder="Descripción de la imagen"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Producto (opcional)
                            </label>
                            <div class="relative">
                                <div class="relative">
                                    <input
                                        v-model="productSearchQuery"
                                        type="text"
                                        placeholder="Buscar producto..."
                                        class="block w-full rounded-md border-gray-300 shadow-sm pl-10 pr-4"
                                        @focus="productSearchQuery = productSearchQuery || ''"
                                    >
                                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <div v-if="productSearchQuery && !imageForm.product_id" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    <div
                                        v-for="product in filteredProducts"
                                        :key="product.id"
                                        @click="imageForm.product_id = product.id; productSearchQuery = product.name"
                                        class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                    >
                                        <p class="text-sm font-medium text-gray-900">{{ product.name }}</p>
                                    </div>
                                    <div v-if="filteredProducts.length === 0" class="px-4 py-2 text-sm text-gray-500">
                                        No se encontraron productos
                                    </div>
                                </div>
                            </div>
                            <div v-if="imageForm.product_id" class="mt-2 flex items-center justify-between bg-blue-50 border border-blue-200 rounded-md px-3 py-2">
                                <span class="text-sm text-blue-900">
                                    {{ selectedProductName }}
                                </span>
                                <button
                                    type="button"
                                    @click="imageForm.product_id = null; productSearchQuery = ''"
                                    class="text-blue-600 hover:text-blue-800"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Si seleccionas un producto, el botón "Comprar ahora" redirigirá a ese producto.
                            </p>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input 
                                    type="checkbox"
                                    v-model="imageForm.show_buy_button"
                                    class="rounded border-gray-300 text-blue-600"
                                >
                                <span class="ml-2 text-sm text-gray-700">Mostrar botón "Comprar ahora"</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Orden
                            </label>
                            <input 
                                v-model.number="imageForm.order"
                                type="number"
                                min="0"
                                class="block w-full rounded-md border-gray-300 shadow-sm"
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Las imágenes se mostrarán en este orden (menor número primero).
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showAddModal = false">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton type="submit" :disabled="imageForm.processing">
                            Agregar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal Editar -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Elemento</h3>
                <form @submit.prevent="submitEdit">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de Media
                            </label>
                            <div class="flex gap-4">
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        v-model="imageForm.media_type"
                                        value="image"
                                        class="mr-2"
                                    />
                                    <span class="text-sm text-gray-700">Imagen</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        v-model="imageForm.media_type"
                                        value="video"
                                        class="mr-2"
                                    />
                                    <span class="text-sm text-gray-700">Video</span>
                                </label>
                            </div>
                        </div>

                        <!-- Campo de imagen -->
                        <div v-if="imageForm.media_type === 'image'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nueva Imagen (opcional)
                            </label>
                            <input 
                                type="file" 
                                @change="handleImageChange"
                                accept="image/*"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Deja vacío para mantener la imagen actual.
                            </p>
                        </div>

                        <!-- Campo de video -->
                        <div v-if="imageForm.media_type === 'video'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nuevo Video (opcional)
                            </label>
                            <input 
                                type="file" 
                                @change="handleVideoChange"
                                accept="video/*"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                            <p class="mt-1 text-xs text-gray-500">
                                Deja vacío para mantener el video actual. Formatos: MP4, WebM, OGG (máx. 50MB)
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Título
                            </label>
                            <input 
                                v-model="imageForm.title"
                                type="text"
                                class="block w-full rounded-md border-gray-300 shadow-sm"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Descripción
                            </label>
                            <textarea 
                                v-model="imageForm.description"
                                rows="3"
                                class="block w-full rounded-md border-gray-300 shadow-sm"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Producto (opcional)
                            </label>
                            <select 
                                v-model="imageForm.product_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option :value="null">Sin producto</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input 
                                    type="checkbox"
                                    v-model="imageForm.show_buy_button"
                                    class="rounded border-gray-300 text-blue-600"
                                >
                                <span class="ml-2 text-sm text-gray-700">Mostrar botón "Comprar ahora"</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Orden
                            </label>
                            <input 
                                v-model.number="imageForm.order"
                                type="number"
                                min="0"
                                class="block w-full rounded-md border-gray-300 shadow-sm"
                            >
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input 
                                    type="checkbox"
                                    v-model="imageForm.is_active"
                                    class="rounded border-gray-300 text-blue-600"
                                >
                                <span class="ml-2 text-sm text-gray-700">Activa</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showEditModal = false">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton type="submit" :disabled="imageForm.processing">
                            Guardar
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal Eliminar -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Eliminar Imagen</h3>
                <p class="text-gray-600 mb-4">
                    ¿Estás seguro de que deseas eliminar esta imagen? Esta acción no se puede deshacer.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteModal = false">
                        Cancelar
                    </SecondaryButton>
                    <DangerButton @click="deleteImage" :disabled="imageForm.processing">
                        Eliminar
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

