<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';

const props = defineProps({
    mainImageUrl: String,
    images: {
        type: Array,
        default: () => [],
    },
});

// Exponer método para cambiar la imagen activa desde el padre
const selectImageByPath = (path) => {
    if (!path) return;
    
    // Buscar la imagen por path (comparación flexible)
    const index = allImages.value.findIndex(img => {
        const imgPath = img.path || '';
        const searchPath = path || '';
        // Comparar rutas normalizadas (sin espacios, mismo formato)
        return imgPath === searchPath || 
               imgPath.replace(/^\/+/, '') === searchPath.replace(/^\/+/, '') ||
               imgPath.endsWith(searchPath) ||
               searchPath.endsWith(imgPath);
    });
    
    if (index >= 0) {
        setIndex(index);
        activeImage.value = allImages.value[index].path;
    } else {
        // Si no se encuentra, intentar buscar por nombre de archivo
        const fileName = path.split('/').pop();
        const indexByFile = allImages.value.findIndex(img => {
            const imgPath = img.path || '';
            return imgPath.includes(fileName) || fileName.includes(imgPath.split('/').pop());
        });
        
        if (indexByFile >= 0) {
            setIndex(indexByFile);
            activeImage.value = allImages.value[indexByFile].path;
        }
    }
};

defineExpose({
    selectImageByPath,
    setIndex,
});

const VISIBLE_COUNT = 3;

const allImages = computed(() => props.images || []);
const initialActive = props.mainImageUrl || (allImages.value[0]?.path || '');
const activeImage = ref(initialActive);

const showAll = ref(false);
const currentIndex = ref(0);

// Sincronizar currentIndex con activeImage inicial
watch(() => allImages.value, (newImages) => {
    if (newImages.length > 0) {
        const index = newImages.findIndex(img => img.path === activeImage.value);
        if (index >= 0) {
            currentIndex.value = index;
        } else {
            // Si la imagen activa no se encuentra, usar la primera
            activeImage.value = newImages[0].path;
            currentIndex.value = 0;
        }
    }
}, { immediate: true });
const thumbsRef = ref(null);
const hasThumbsOverflow = ref(false);

const visibleThumbnails = computed(() => props.images.slice(0, VISIBLE_COUNT));
const hiddenThumbnails = computed(() => props.images.slice(VISIBLE_COUNT));

function selectImage(path) {
    if (!path) return;
    activeImage.value = path;
    // Sincronizar el índice actual con la imagen seleccionada
    const index = indexOfPath(path);
    if (index >= 0) {
        setIndex(index);
    }
}

function indexOfPath(path) {
    return allImages.value.findIndex(img => img.path === path);
}

function setIndex(i) {
    if (i < 0 || i >= allImages.value.length) return;
    currentIndex.value = i;
}

function prev() {
    if (allImages.value.length === 0) return;
    currentIndex.value = (currentIndex.value - 1 + allImages.value.length) % allImages.value.length;
}

function next() {
    if (allImages.value.length === 0) return;
    currentIndex.value = (currentIndex.value + 1) % allImages.value.length;
}

function openAll() {
    const idx = indexOfPath(activeImage.value);
    currentIndex.value = idx >= 0 ? idx : 0;
    showAll.value = true;
}

function closeAll() {
    const img = allImages.value[currentIndex.value];
    if (img?.path) activeImage.value = img.path;
    showAll.value = false;
}

function onKeydown(e) {
    if (!showAll.value) return;
    if (e.key === 'Escape') { e.preventDefault(); closeAll(); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); prev(); }
    if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

function scrollThumbs(direction) {
    const el = thumbsRef.value;
    if (!el) return;
    const itemWidth = 90; // 80px + ~10px de gap
    const amount = itemWidth * 6 * direction; // desplazar ~6 miniaturas
    el.scrollBy({ left: amount, behavior: 'smooth' });
}

function updateThumbsOverflow() {
    const el = thumbsRef.value;
    if (!el) { hasThumbsOverflow.value = false; return; }
    hasThumbsOverflow.value = (el.scrollWidth - el.clientWidth) > 1;
}

onMounted(() => {
    window.addEventListener('resize', updateThumbsOverflow);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateThumbsOverflow);
});

watch(showAll, async (open) => {
    if (open) {
        await nextTick();
        updateThumbsOverflow();
        // atraso breve para asegurar layout final (imágenes cargadas)
        setTimeout(updateThumbsOverflow, 50);
        setTimeout(updateThumbsOverflow, 300);
    }
});
</script>

<template>
    <div class="gallery-container">
        <div class="main-image">
            <img :src="activeImage" alt="Imagen principal del producto" />
        </div>
        <div class="thumbnail-strip">
            <div
                v-for="image in visibleThumbnails"
                :key="image.id" class="thumbnail"
                @click="selectImage(image.path)"      
                :class="{ 'active': activeImage === image.path }"
            >
                <img :src="image.path" alt="Miniatura del producto" />
            </div>

            <div v-if="hiddenThumbnails.length > 0" class="more-tile" @click="openAll">
                +{{ hiddenThumbnails.length }}
            </div>
        </div>

        <div v-if="showAll" class="modal-backdrop" @click.self="closeAll">
            <div class="modal-content">
                <button class="close-btn" type="button" @click="closeAll">×</button>

                <div class="carousel">
                    <button class="nav prev" type="button" @click.stop="prev">‹</button>
                    <img :src="allImages[currentIndex]?.path" alt="Imagen del carrusel" />
                    <button class="nav next" type="button" @click.stop="next">›</button>
                </div>

                <div class="thumbs-wrapper" :class="{ 'has-overflow': hasThumbsOverflow }">
                    <button v-show="hasThumbsOverflow" class="thumbs-nav prev" type="button" @click="scrollThumbs(-1)">‹</button>
                    <div class="modal-thumbs" ref="thumbsRef">
                        <div
                            v-for="(image, i) in allImages"
                            :key="`all-${image.id}`"
                            class="thumbnail modal-thumb"
                            @click="setIndex(i)"
                            :class="{ 'active': allImages[currentIndex]?.path === image.path }"
                        >
                            <img :src="image.path" alt="Miniatura del producto" />
                        </div>
                    </div>
                    <button v-show="hasThumbsOverflow" class="thumbs-nav next" type="button" @click="scrollThumbs(1)">›</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.main-image img {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #eee;
}
.thumbnail-strip {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
.thumbnail {
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 5px;
    padding: 2px;
}
.thumbnail.active {
    border-color: #007bff; /* Un azul para resaltar la activa */
}
.thumbnail img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    display: block;
    border-radius: 4px;
}

.more-tile {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    color: #111827;
    border-radius: 4px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid #e5e7eb;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 20px;
}
.modal-content {
    background: #ffffff;
    border-radius: 8px;
    padding: 16px 20px 20px 20px;
    max-width: 900px;
    width: 100%;
    max-height: 80vh;
    overflow: auto;
    position: relative;
}
.carousel {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 60vh;
    min-height: 320px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 12px;
}
.carousel img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.85);
    border: 1px solid #e5e7eb;
    border-radius: 9999px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    cursor: pointer;
    user-select: none;
}
.nav.prev { left: 10px; }
.nav.next { right: 10px; }

.modal-thumbs {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(84px, 1fr);
    gap: 10px;
    overflow-x: auto;
    padding: 6px 2px 10px 2px;
}
.modal-thumbs::-webkit-scrollbar {
    height: 8px;
}
.modal-thumbs::-webkit-scrollbar-track {
    background: transparent;
}
.modal-thumbs::-webkit-scrollbar-thumb {
    background: #d1d5db; /* gris suave */
    border-radius: 9999px;
}
.thumbs-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
}
.thumbs-nav {
    background: rgba(255,255,255,0.9);
    border: 1px solid #e5e7eb;
    border-radius: 9999px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s ease;
}
.thumbs-wrapper.has-overflow:hover .thumbs-nav { opacity: 1; }
.thumbs-nav.prev { margin-left: -4px; }
.thumbs-nav.next { margin-right: -4px; }
.modal-thumb img {
    width: 84px;
    height: 84px;
    object-fit: cover;
    border-radius: 6px;
}
.modal-thumb {
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
}
.modal-thumb.active {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.2);
}
.close-btn {
    position: absolute;
    top: 8px;
    right: 12px;
    background: rgba(17,24,39,0.7);
    color: #ffffff;
    border: 1px solid #111827;
    border-radius: 9999px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    z-index: 60;
}
</style>