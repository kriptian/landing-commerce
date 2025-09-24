<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, nextTick } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    products: Object,
    store: Object,
    categories: Array,
    filters: Object,
});

const search = ref(props.filters.search);

// --- LÓGICA PARA LA BÚSQUEDA ANIMADA ---
const isSearchActive = ref(false);
const searchInput = ref(null); // Referencia al input de búsqueda

const toggleSearch = () => {
    isSearchActive.value = !isSearchActive.value;
    if (isSearchActive.value) {
        // nextTick espera a que Vue actualice el DOM (muestre el input)
        // antes de intentar ponerle el foco.
        nextTick(() => {
            searchInput.value.focus();
        });
    } else {
        // Si cerramos la búsqueda, limpiamos el filtro
        search.value = '';
    }
};
// --- FIN LÓGICA ---


watch(search, (value) => {
    setTimeout(() => {
        router.get(route('catalogo.index', { store: props.store.slug }), { search: value }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    }, 500);
});
</script>

<template>
    <Head :title="`Catálogo de ${store.name}`" />

    <header class="bg-white shadow-sm sticky top-0 z-10">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <img v-if="store.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-full object-cover">
                <h1 class="text-2xl font-bold text-gray-900">{{ store.name }}</h1>
            </div>
            <div class="flex items-center space-x-4">
                 <a v-if="store.facebook_url" :href="store.facebook_url" target="_blank" class="text-gray-500 hover:text-blue-800">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
                </a>
                <a v-if="store.instagram_url" :href="store.instagram_url" target="_blank" class="text-gray-500 hover:text-pink-600">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.012-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.08 2.525c.636-.247 1.363-.416 2.427-.465C9.53 2.013 9.884 2 12.315 2zm-1.161 1.545a1.12 1.12 0 10-1.584 1.584 1.12 1.12 0 001.584-1.584zm-3.097 3.569a3.468 3.468 0 106.937 0 3.468 3.468 0 00-6.937 0z" clip-rule="evenodd" /><path d="M12 6.166a5.834 5.834 0 100 11.668 5.834 5.834 0 000-11.668zm0 1.545a4.289 4.289 0 110 8.578 4.289 4.289 0 010-8.578z" /></svg>
                </a>
                <a v-if="store.tiktok_url" :href="store.tiktok_url" target="_blank" class="text-gray-500 hover:text-black">
                     <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.525.02c1.31-.02 2.61-.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.01-1.58-.31-3.15-.82-4.7-.52-1.56-1.23-3.04-2.1-4.42a.1.1 0 00-.2-.04c-.02.13-.03.26-.05.39v7.24a.26.26 0 00.27.27c.82.04 1.63.16 2.42.37.04.83.16 1.66.36 2.47.19.82.49 1.6.86 2.33.36.73.81 1.41 1.32 2.02-.17.1-.34.19-.51.28a4.26 4.26 0 01-1.93.52c-1.37.04-2.73-.06-4.1-.23a9.8 9.8 0 01-3.49-1.26c-.96-.54-1.8-1.23-2.52-2.03-.72-.8-1.3-1.7-1.77-2.69-.47-.99-.8-2.06-1.02-3.13a.15.15 0 01.04-.15.24.24 0 01.2-.09c.64-.02 1.28-.04 1.92-.05.1 0 .19-.01.28-.01.07.01.13.02.2.04.19.04.38.09.57.14a5.2 5.2 0 005.02-5.22v-.02a.23.23 0 00-.23-.23.2.2 0 00-.2-.02c-.83-.06-1.66-.13-2.49-.22-.05-.01-.1-.01-.15-.02-1.12-.13-2.25-.26-3.37-.44a.2.2 0 01-.16-.24.22.22 0 01.23-.18c.41-.06.82-.12 1.23-.18C9.9.01 11.21 0 12.525.02z"/></svg>
                </a>
                <div class="border-l h-6 border-gray-300"></div>
                <Link :href="route('cart.index', { store: store.slug })" class="relative flex items-center px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                    <span>🛒</span>
                    <span class="ml-2 font-semibold">Carrito</span>
                    <span v-if="$page.props.cart.count > 0" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                        {{ $page.props.cart.count }}
                    </span>
                </Link>
            </div>
        </nav>
    </header>
    <main class="container mx-auto px-6 py-12">
        <h2 class="text-3xl font-bold mb-4">Nuestro Catálogo</h2>

        <div class="mb-8 p-4 bg-gray-50 rounded-lg">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                
                <div class="flex-grow">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filtrar por categoría</label>
                    <Dropdown align="left" width="64">
                        <template #trigger>
                            <button type="button" class="inline-flex justify-between items-center w-full md:w-auto rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">
                                <span>{{ filters.category ? categories.find(c => c.id == filters.category).name : 'Todas las categorías' }}</span>
                                <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('catalogo.index', { store: store.slug })"> Todas </DropdownLink>
                            <DropdownLink v-for="category in categories" :key="category.id" :href="route('catalogo.index', { store: store.slug, category: category.id })">
                                {{ category.name }}
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
                
                <div class="flex items-center space-x-2">
                    <input 
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        placeholder="Buscar..."
                        class="block h-10 rounded-md shadow-sm transition-all duration-300 ease-in-out border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                        :class="{ 'w-48 sm:w-64 px-2 opacity-100': isSearchActive, 'w-0 p-0 border-transparent opacity-0': !isSearchActive }"
                    />
                    <button @click="toggleSearch" type="button" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                        <svg v-if="isSearchActive" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        <svg v-else class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </button>
                </div>

            </div>
        </div>

        <div v-if="products.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div v-for="product in products.data" :key="product.id" class="product-card border rounded-lg shadow-md overflow-hidden">
                <img v-if="product.main_image_url" :src="product.main_image_url" alt="Imagen del producto" class="w-full h-64 object-cover">
                <div class="p-4">
                    <h3 class="text-xl font-semibold mb-2">{{ product.name }}</h3>
                    <p class="text-lg text-blue-600 font-bold mb-4">$ {{ Number(product.price).toLocaleString('es-CO') }}</p>
                    <Link :href="route('catalogo.show', { store: store.slug, product: product.id })" class="w-full bg-gray-800 text-white font-bold py-2 px-4 rounded text-center block hover:bg-gray-700">
                        Ver Detalles
                    </Link>
                </div>
            </div>
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
        
        <Pagination v-if="products.data.length > 0" class="mt-8" :links="products.links" />

    </main>

    <footer class="bg-white mt-16 border-t">
        <div class="container mx-auto px-6 py-4 text-center text-gray-500">
            <p>&copy; 2025 {{ store.name }}</p>
        </div>
    </footer>
</template>