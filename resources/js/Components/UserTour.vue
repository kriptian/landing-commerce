<template>
  <div v-if="showTour" class="fixed inset-0 z-50">
    <!-- Overlay oscuro -->
    <div class="absolute inset-0 bg-black bg-opacity-50" @click="skipTour"></div>
    
    <!-- Spotlight effect -->
    <div 
      v-if="currentStep && currentStep.target"
      class="absolute border-4 border-blue-500 rounded-lg shadow-2xl transition-all duration-300"
      :class="{
        'ring-4 ring-blue-200': currentStepIndex === 9, // Paso del perfil
        'bg-blue-50 bg-opacity-20': currentStepIndex === 9
      }"
      :style="spotlightStyle"
    ></div>
    
    <!-- Tooltip del tour -->
    <div 
      v-if="currentStep"
      class="absolute bg-gradient-to-br from-white to-blue-50 rounded-2xl shadow-2xl max-w-sm z-10 border border-blue-100 overflow-hidden backdrop-blur-sm"
      :style="tooltipStyle"
    >
      <!-- Header con gradiente y logo -->
      <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 text-white">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <!-- Logo -->
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center overflow-hidden">
              <img src="/images/New_Logo_ondgtl.png" alt="Logo" class="w-8 h-8 object-contain" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-white bg-opacity-30 rounded-full flex items-center justify-center">
                  <span class="text-white font-bold text-xs">{{ currentStepIndex + 1 }}</span>
                </div>
                <h3 class="text-lg font-bold">{{ currentStep.title }}</h3>
              </div>
            </div>
          </div>
          <button 
            @click="skipTour"
            class="text-white hover:text-blue-200 transition-colors p-1 rounded-full hover:bg-white hover:bg-opacity-20"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>
      
      <!-- Contenido del tooltip -->
      <div class="px-6 py-4">
        <p class="text-gray-700 mb-4 leading-relaxed">{{ currentStep.content }}</p>
        
        <!-- Barra de progreso mejorada -->
        <div class="mb-4">
          <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>Paso {{ currentStepIndex + 1 }} de {{ tourSteps.length }}</span>
            <span>{{ Math.round(((currentStepIndex + 1) / tourSteps.length) * 100) }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div 
              class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-500 ease-out"
              :style="{ width: `${((currentStepIndex + 1) / tourSteps.length) * 100}%` }"
            ></div>
          </div>
        </div>
        
        <!-- Navegación mejorada -->
        <div class="flex items-center justify-between">
          <button 
            v-if="currentStepIndex > 0"
            @click="previousStep"
            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-all duration-200 flex items-center gap-1"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Anterior
          </button>
          <div v-else></div>
          
          <!-- Indicadores de progreso -->
          <div class="flex space-x-1">
            <div 
              v-for="(step, index) in tourSteps" 
              :key="index"
              class="w-2 h-2 rounded-full transition-all duration-300"
              :class="index <= currentStepIndex ? 'bg-blue-500 scale-110' : 'bg-gray-300'"
            ></div>
          </div>
          
          <button 
            @click="nextStep"
            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-200 flex items-center gap-1 shadow-lg hover:shadow-xl transform hover:scale-105"
          >
            {{ currentStepIndex === tourSteps.length - 1 ? 'Finalizar' : 'Siguiente' }}
            <svg v-if="currentStepIndex < tourSteps.length - 1" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </button>
        </div>
        
        <!-- Opciones de tour -->
        <div class="mt-4 pt-4 border-t border-gray-200">
          <p class="text-sm text-gray-600 mb-3 text-center">¿Qué te gustaría hacer?</p>
          <div class="flex gap-2">
            <button 
              @click="remindLater"
              class="flex-1 px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
            >
              Ver después
            </button>
            <button 
              @click="neverShowAgain"
              class="flex-1 px-3 py-2 text-sm font-medium text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
            >
              No volver a mostrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['complete']);

const page = usePage();
const isTourClosed = ref(false);

const showTour = computed(() => {
  // Si el tour fue cerrado localmente, no mostrarlo
  if (isTourClosed.value) {
    return false;
  }
  
  const value = page.props.show_tour;
  // Convertir string "1" a boolean true, string "0" a boolean false
  if (typeof value === 'string') {
    return value === '1' || value === 'true';
  }
  return Boolean(value);
});


const currentStepIndex = ref(0);

// Definición de los pasos del tour
const tourSteps = ref([
  {
    target: '#dashboard-welcome',
    title: '¡Bienvenido!',
    content: 'Este es tu panel de administración principal. Aquí podrás ver un resumen de tu tienda y acceder a todas las funcionalidades.',
    position: 'bottom'
  },
  {
    target: 'nav[class*="space-x-8"]',
    title: 'Navegación Principal',
    content: 'Desde aquí puedes acceder a todas las secciones de tu tienda: Dashboard, Órdenes, Reportes, Inventario y más.',
    position: 'bottom'
  },
  {
    target: '.bg-white.border.border-gray-100.rounded-xl.p-6:first-of-type',
    title: 'Gestión de Productos',
    content: 'Aquí podrás crear, editar y gestionar todos tus productos. Puedes agregar variantes, imágenes, precios y controlar el inventario.',
    position: 'bottom'
  },
  {
    target: '.bg-white.border.border-gray-100.rounded-xl.p-6:nth-of-type(2)',
    title: 'Categorías',
    content: 'Organiza tus productos en categorías para que tus clientes puedan encontrarlos fácilmente. Puedes crear categorías y subcategorías.',
    position: 'bottom'
  },
  {
    target: '.bg-white.border.border-gray-100.rounded-xl.p-6:nth-of-type(3)',
    title: 'Gestión de Usuarios',
    content: 'Administra los usuarios y sus roles en el sistema. Puedes crear, editar y gestionar permisos de acceso.',
    position: 'bottom'
  },
  {
    target: 'a[href*="catalog"], a[href*="tienda"], a[href*="store"]',
    title: 'Enlace del Catálogo',
    content: 'Aquí está el enlace de tu catálogo público. Los clientes pueden visitar este enlace para ver todos tus productos. ¡Cópialo y compártelo!',
    position: 'bottom'
  },
  {
    target: 'a[href*="orders"]',
    title: 'Órdenes (Plan Negociante)',
    content: 'Gestiona todos los pedidos de tus clientes. Ve el estado de cada orden, confirma pedidos y recibe notificaciones automáticas.',
    position: 'bottom'
  },
  {
    target: 'a[href*="reports"]',
    title: 'Reportes (Plan Negociante)',
    content: 'Analiza el rendimiento de tu tienda con reportes detallados de ventas, productos más vendidos y gráficos informativos.',
    position: 'bottom'
  },
  {
    target: 'a[href*="inventory"]',
    title: 'Inventario (Plan Negociante)',
    content: 'Controla tu stock con alertas automáticas cuando los productos se agoten. Exporta reportes de inventario.',
    position: 'bottom'
  },
  {
    target: '.relative.ms-3',
    title: 'Perfil de Usuario',
    content: 'Accede a tu perfil para actualizar tu información personal, cambiar contraseña y configurar tu cuenta.',
    position: 'left'
  }
]);

const currentStep = computed(() => {
  return tourSteps.value[currentStepIndex.value];
});

const spotlightStyle = computed(() => {
  if (!currentStep.value?.target) return {};
  
  const element = document.querySelector(currentStep.value.target);
  if (!element) return {};
  
  const rect = element.getBoundingClientRect();
  return {
    left: `${rect.left - 8}px`,
    top: `${rect.top - 8}px`,
    width: `${rect.width + 16}px`,
    height: `${rect.height + 16}px`,
  };
});

const tooltipStyle = computed(() => {
  // Para dispositivos móviles, usar posición fija en la parte superior
  if (window.innerWidth < 768) {
    return {
      position: 'fixed',
      left: '50%',
      top: '10px',
      transform: 'translateX(-50%)',
      zIndex: 9999,
      maxWidth: '95vw',
      maxHeight: '60vh',
      margin: '0 10px',
      width: 'calc(100vw - 20px)'
    };
  }
  
  if (!currentStep.value?.target) return {};
  
  const element = document.querySelector(currentStep.value.target);
  if (!element) return {};
  
  const rect = element.getBoundingClientRect();
  const position = currentStep.value.position || 'bottom';
  
  // Dimensiones del tooltip
  const tooltipWidth = 300;
  const tooltipHeight = 200;
  
  let left, top;
  
  switch (position) {
    case 'bottom':
      // Centrar horizontalmente y colocar debajo con más espacio
      left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
      top = `${rect.bottom + 50}px`;
      break;
    case 'top':
      // Centrar horizontalmente y colocar arriba con más espacio
      left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
      top = `${rect.top - tooltipHeight - 50}px`;
      break;
    case 'left':
      // Colocar a la izquierda, centrado verticalmente
      left = `${rect.left - tooltipWidth - 50}px`;
      top = `${rect.top + (rect.height / 2) - (tooltipHeight / 2)}px`;
      break;
    case 'right':
      // Colocar a la derecha, centrado verticalmente
      left = `${rect.right + 50}px`;
      top = `${rect.top + (rect.height / 2) - (tooltipHeight / 2)}px`;
      break;
    default:
      // Por defecto, abajo y centrado
      left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
      top = `${rect.bottom + 50}px`;
  }
  
  // Asegurar que el tooltip esté dentro de la ventana
  const maxLeft = window.innerWidth - tooltipWidth - 20;
  const maxTop = window.innerHeight - tooltipHeight - 20;
  
  // Si se sale por la derecha, mover a la izquierda
  if (parseInt(left) > maxLeft) {
    left = `${maxLeft}px`;
  }
  
  // Si se sale por abajo, mover arriba
  if (parseInt(top) > maxTop) {
    top = `${rect.top - tooltipHeight - 50}px`;
  }
  
  // Si se sale por arriba, mover abajo
  if (parseInt(top) < 20) {
    top = `${rect.bottom + 50}px`;
  }
  
  // Si se sale por la izquierda, mover a la derecha
  if (parseInt(left) < 20) {
    left = `${rect.right + 50}px`;
  }
  
  return {
    left: `${Math.max(parseInt(left), 20)}px`,
    top: `${Math.max(parseInt(top), 20)}px`,
  };
});

const nextStep = () => {
  if (currentStepIndex.value < tourSteps.value.length - 1) {
    currentStepIndex.value++;
  } else {
    completeTour();
  }
};

const previousStep = () => {
  if (currentStepIndex.value > 0) {
    currentStepIndex.value--;
  }
};

const skipTour = () => {
  completeTour();
};

const completeTour = async () => {
  // Cerrar el tour inmediatamente
  isTourClosed.value = true;
  emit('complete');
  
  try {
    await router.post(route('tour.complete'), {}, {
      preserveScroll: true,
      onError: (errors) => {
        // Error silenciado
      }
    });
  } catch (error) {
    // Error silenciado
  }
};

const remindLater = async () => {
  // Cerrar el tour inmediatamente
  isTourClosed.value = true;
  emit('complete');
  
  try {
    await router.post(route('tour.remind-later'), { section: 'main' }, {
      preserveScroll: true,
      onError: (errors) => {
        // Error silenciado
      }
    });
  } catch (error) {
    // Error silenciado
  }
};

const neverShowAgain = async () => {
  // Cerrar el tour inmediatamente
  isTourClosed.value = true;
  emit('complete');
  
  try {
    await router.post(route('tour.never-show'), { section: 'main' }, {
      preserveScroll: true,
      onError: (errors) => {
        // Error silenciado
      }
    });
  } catch (error) {
    // Error silenciado
  }
};

// Asegurar que los elementos estén disponibles antes de mostrar el tour
const waitForElements = async () => {
  let attempts = 0;
  const maxAttempts = 50;
  
  while (attempts < maxAttempts) {
    const element = document.querySelector(currentStep.value.target);
    if (element) {
      return true;
    }
    await new Promise(resolve => setTimeout(resolve, 100));
    attempts++;
  }
  return false;
};

onMounted(async () => {
  if (showTour.value) {
    await nextTick();
    await waitForElements();
  }
});

// Manejar redimensionamiento de ventana
const handleResize = () => {
  // Recalcular posiciones si el tour está activo
  if (showTour.value) {
    nextTick();
  }
};

onMounted(() => {
  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
});
</script>

<style scoped>
/* Animaciones suaves para el spotlight */
.absolute {
  transition: all 0.3s ease-in-out;
}

/* Asegurar que el tooltip esté por encima de todo */
.z-10 {
  z-index: 60;
}
</style>
