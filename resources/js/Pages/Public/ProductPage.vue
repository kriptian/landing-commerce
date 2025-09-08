<script setup>
import ProductGallery from '@/Components/Product/ProductGallery.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue'; // <-- Se añade este import

// Inertia nos pasa el producto completo como una "prop"
const props = defineProps({
    product: Object,
});

// ESTA ES LA PARTE QUE CAMBIA PARA SER MÁS SEGURA
const specifications = computed(() => {
    // Si no hay especificaciones, devuelve una lista vacía para no generar un error
    if (!props.product.specifications) {
        return [];
    }
    // Si hay especificaciones, intenta procesarlas
    try {
        return JSON.parse(props.product.specifications);
    } catch (e) {
        console.error("Error al procesar especificaciones:", e);
        // Si el JSON está malformado, también devuelve una lista vacía
        return [];
    }
});
</script>

<template>
    <Head :title="product.name" />

    <header class="bg-white shadow-sm sticky top-0">
        <nav class="container mx-auto px-6 py-4">
            <h1 class="text-2xl font-bold text-gray-900">Landing-Commerce</h1>
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
                <p class="text-3xl font-semibold text-blue-600">$ {{ Number(product.price).toFixed(2) }}</p>
                <p class="text-lg text-gray-600">{{ product.short_description }}</p>

                <a href="https://wa.me/573208204198" target="_blank" class="w-full bg-green-500 text-white font-bold py-3 px-6 rounded-lg text-center hover:bg-green-600 transition duration-300">
                    COTIZAR POR WHATSAPP
                </a>

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