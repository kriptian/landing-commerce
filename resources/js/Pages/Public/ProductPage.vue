<script setup>
import ProductGallery from '@/Components/Product/ProductGallery.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

// Inertia nos pasa el producto completo (ahora CON sus variantes)
const props = defineProps({
    product: Object,
});

// --- ESTA ES LA MAGIA NUEVA ---

// 1. Guardamos el ID de la variante que el cliente escoja
const selectedVariantId = ref(null);

// 2. Una propiedad calculada para encontrar el OBJETO de la variante escogida
const selectedVariant = computed(() => {
    if (!selectedVariantId.value) return null;
    return props.product.variants.find(v => v.id === selectedVariantId.value);
});

// 3. Propiedades calculadas para mostrar los datos correctos
const displayPrice = computed(() => {
    if (selectedVariant.value && selectedVariant.value.price) {
        return selectedVariant.value.price; // Muestra el precio de la variante
    }
    return props.product.price; // Si no, muestra el precio principal
});

const displayStock = computed(() => {
    if (selectedVariant.value) {
        return selectedVariant.value.stock; // Muestra el stock de la variante
    }
    // Si no hay variantes (o no se ha escogido), muestra el stock general
    return props.product.variants.length > 0 ? 0 : props.product.quantity; 
});

// Esta variable guardará la cantidad que el cliente selecciona
const selectedQuantity = ref(1);

// Función para aumentar la cantidad (ahora revisa el stock de la variante)
const increaseQuantity = () => {
    if (displayStock.value > 0 && selectedQuantity.value < displayStock.value) {
        selectedQuantity.value++;
    }
};

// Función para disminuir la cantidad
const decreaseQuantity = () => {
    if (selectedQuantity.value > 1) {
        selectedQuantity.value--;
    }
};

// VIGILANTE: Si el usuario cambia de variante, reseteamos la cantidad a 1
watch(selectedVariantId, () => {
    selectedQuantity.value = 1;
});

// VIGILANTE: Si el usuario teclea una cantidad mayor al stock, la corregimos
watch(selectedQuantity, (newQty) => {
    if (newQty > displayStock.value) {
        selectedQuantity.value = displayStock.value;
    }
    if (newQty < 1) {
        selectedQuantity.value = 1;
    }
});

// 4. El "Agregar al Carrito" ahora manda el ID de la variante
const addToCart = () => {
    if (!selectedVariantId.value) {
        alert('Por favor, selecciona una opción.');
        return;
    }

    router.post(route('cart.store'), {
        product_id: props.product.id,
        product_variant_id: selectedVariantId.value, // <-- LA CLAVE
        quantity: selectedQuantity.value,
    }, {
        preserveScroll: true, 
        onSuccess: () => {
            alert('¡Producto añadido al carrito!'); 
        },
        onError: (errors) => {
            if (errors.quantity) {
                alert(errors.quantity);
            } else {
                alert('Hubo un error al añadir el producto.');
            }
        }
    });
};

// Lógica de especificaciones (sigue igual)
const specifications = computed(() => {
    if (!props.product.specifications) return [];
    try {
        return JSON.parse(props.product.specifications);
    } catch (e) {
        return [];
    }
});
</script>

<template>
    <Head :title="product.name" />

    <header class="bg-white shadow-sm sticky top-0 z-10">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-900">Landing-Commerce</h1>
            <Link :href="route('cart.index', { store: product.store.slug })" class="relative flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                <span>🛒</span>
                <span class="ml-2 font-semibold">Carrito</span>
                <span v-if="$page.props.cart.count > 0" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                    {{ $page.props.cart.count }}
                </span>
            </Link>
        </nav>
    </header>

    <main class="container mx-auto px-6 py-12">
        <section class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">

            <div class="gallery">
                <ProductGallery 
                    :main-image-url="product.main_image_url"
                    :images="product.images"
                />
            </div>

            <div class="info flex flex-col space-y-4">
                <h1 class="text-4xl font-extrabold text-gray-900">{{ product.name }}</h1>
                <p class="text-3xl font-semibold text-blue-600">$ {{ Number(displayPrice).toFixed(2) }}</p>
                <p class="text-lg text-gray-600">{{ product.short_description }}</p>


                <div v-if="product.variants.length > 0" class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Opciones Disponibles:</h3>
                    <div class="space-y-2">
                        <div v-for="variant in product.variants" :key="variant.id">
                            <label class="flex items-center p-4 border rounded-lg cursor-pointer" :class="{ 'border-blue-600 ring-2 ring-blue-300': selectedVariantId === variant.id }">
                                <input type="radio" :value="variant.id" v-model="selectedVariantId" class="form-radio text-blue-600">
                                <div class="ml-4 flex-grow">
                                    <span class="font-semibold text-gray-800">
                                        <span v-for="(value, key) in variant.options" :key="key" class="mr-2">
                                            <span class="font-normal">{{ key }}:</span> {{ value }}
                                        </span>
                                    </span>
                                    <span v-if="variant.price" class="ml-2 text-blue-600">($ {{ Number(variant.price).toFixed(2) }})</span>
                                </div>
                                <span class="text-sm font-medium" :class="{ 'text-red-600': variant.stock === 0, 'text-gray-600': variant.stock > 0 }">
                                    {{ variant.stock > 0 ? `${variant.stock} disponibles` : 'Agotado' }}
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="pt-4">
                    <div class="flex items-center space-x-4">
                        <label class="font-semibold">Cantidad:</label>
                        <div class="flex items-center border rounded-md">
                            <button @click="decreaseQuantity" :disabled="!selectedVariant" class="px-3 py-1 text-lg font-bold hover:bg-gray-200 rounded-l-md disabled:opacity-50">-</button>
                            <input type="number" v-model.number="selectedQuantity" class="w-16 text-center border-y-0 border-x" />
                            <button @click="increaseQuantity" :disabled="!selectedVariant" class="px-3 py-1 text-lg font-bold hover:bg-gray-200 rounded-r-md disabled:opacity-50">+</button>
                        </div>
                        <p v-if="selectedVariant" class="text-sm text-gray-600">({{ displayStock }} en stock)</p>
                    </div>

                    <button 
                        @click="addToCart"
                        :disabled="!selectedVariant || displayStock === 0 || selectedQuantity > displayStock"
                        class="w-full mt-6 bg-blue-600 text-white font-bold py-3 px-6 rounded-lg text-center transition duration-300 disabled:bg-gray-400 enabled:hover:bg-blue-700">
                        {{ !selectedVariant ? 'Selecciona una opción' : (displayStock === 0 ? 'Agotado' : 'Agregar al Carrito') }}
                    </button>
                </div>
                <div class="border-t pt-4">
                    <h3 class="text-xl font-semibold mb-3">Especificaciones Técnicas</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li v-for="spec in specifications" :key="spec">{{ spec }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="long-description mt-16 border-t pt-8">
            <h2 class="text-2xl font-bold mb-4">Descripción Detallada</h2>
            <div class="prose max-w-none text-gray-600">
                <p>{{ product.long_description }}</p> 
            </div>
        </section>

        </main>

    <footer class="bg-white mt-16 border-t">
        <div class="container mx-auto px-6 py-4 text-center text-gray-500">
            <p>&copy; 2025 Landing-Commerce</p>
        </div>
    </footer>
</template>