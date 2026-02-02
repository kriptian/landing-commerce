<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, onMounted, computed } from 'vue';

const props = defineProps({
    store: {
        type: Object,
        required: true,
    },
});

const show = ref(false);

const hasPopupConfig = computed(() => {
    return props.store.popup_active && (props.store.popup_image_path || props.store.popup_button_text);
});

const PROP_POPUP_EXPIRY_MINUTES = 60; // Configurable: volver a mostrar cada 60 mins

onMounted(() => {
    if (hasPopupConfig.value) {
        if (props.store.popup_frequency === 'hourly') {
            // Lógica "Cada Hora" (Apps Grandes)
            const storageKey = `popup_data_${props.store.id}`;
            const rawData = localStorage.getItem(storageKey);
            
            let shouldShow = true;
            if (rawData) {
                try {
                    const data = JSON.parse(rawData);
                    const now = new Date().getTime();
                    // Si ya fue visto y aún no ha expirado el tiempo
                    if (data.viewed && (now - data.timestamp) < (PROP_POPUP_EXPIRY_MINUTES * 60 * 1000)) {
                        shouldShow = false;
                    }
                } catch (e) { }
            }

            if (shouldShow) {
                setTimeout(() => { show.value = true; }, 500);
            }
        } else {
            // Lógica "Por Sesión" (Clásica)
            const storageKey = `popup_viewed_session_${props.store.id}`;
            const viewedInThisSession = sessionStorage.getItem(storageKey);

            if (!viewedInThisSession) {
                setTimeout(() => { show.value = true; }, 500);
            }
        }
    }
});

const close = () => {
    show.value = false;
    
    if (props.store.popup_frequency === 'hourly') {
        const storageKey = `popup_data_${props.store.id}`;
        const data = {
            viewed: true,
            timestamp: new Date().getTime()
        };
        localStorage.setItem(storageKey, JSON.stringify(data));
    } else {
        const storageKey = `popup_viewed_session_${props.store.id}`;
        sessionStorage.setItem(storageKey, 'true');
    }
};

const emit = defineEmits(['open-register']);

const handleAction = () => {
    close(); // Always close popup on action
    
    if (props.store.popup_button_link === '#register') {
        emit('open-register');
    } else if (props.store.popup_button_link) {
        window.location.href = props.store.popup_button_link;
    }
};
</script>

<template>
    <Modal :show="show" @close="close" maxWidth="md">
        <div class="relative bg-white rounded-lg overflow-hidden">
            <!-- Botón cerrar superior -->
            <button 
                @click="close" 
                class="absolute top-2 right-2 p-1 bg-white/50 hover:bg-white rounded-full text-gray-600 transition-colors z-10"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Imagen -->
            <div v-if="store.popup_image_path">
                <img 
                    :src="store.popup_image_path" 
                    alt="Promoción" 
                    class="w-full h-auto object-cover max-h-[70vh]"
                />
            </div>
            
            <!-- Contenido adicional si no hay imagen o si se quiere texto -->
            <!-- El requerimiento dice "cargar la imagen que el cliente quiera y tener la opción de botón" -->
            <!-- Si no hay imagen, tal vez mostrar solo botón? Asumo imagen es principal -->

            <!-- Botón de acción -->
            <div 
                v-if="store.popup_show_button && store.popup_button_text" 
                class="p-4 flex justify-center bg-white"
            >
                <button
                    @click="handleAction"
                    class="w-full py-3 px-6 rounded-lg font-bold text-white shadow-lg transform transition hover:scale-[1.02] active:scale-[0.98]"
                    :style="{ 
                        backgroundColor: store.popup_button_color || store.catalog_button_bg_color || '#1F2937',
                        color: store.popup_button_text_color || store.catalog_button_text_color || '#FFFFFF'
                    }"
                >
                    {{ store.popup_button_text }}
                </button>
            </div>
        </div>
    </Modal>
</template>
