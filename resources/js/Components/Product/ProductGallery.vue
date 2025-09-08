<script setup>
import { ref } from 'vue';

const props = defineProps({
    mainImageUrl: String,
    images: {
        type: Array,
        default: () => [],
    },
});

const activeImage = ref(props.mainImageUrl);
</script>

<template>
    <div class="gallery-container">
        <div class="main-image">
            <img :src="activeImage" alt="Imagen principal del producto" />
        </div>
        <div class="thumbnail-strip">
            <div
                v-for="image in images"
                :key="image.id" class="thumbnail"
                @click="activeImage = image.path"      
                :class="{ 'active': activeImage === image.path }"
            >
                <img :src="image.path" alt="Miniatura del producto" />
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
</style>