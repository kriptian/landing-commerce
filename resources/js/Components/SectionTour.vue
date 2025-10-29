<template>
  <div v-if="showTour" class="fixed inset-0 z-50">
    <!-- Overlay oscuro -->
    <div class="absolute inset-0 bg-black bg-opacity-50" @click="skipTour"></div>
    
    <!-- Spotlight effect -->
    <div 
      v-if="currentStep && currentStep.target"
      class="absolute border-4 border-blue-500 rounded-lg shadow-2xl transition-all duration-700"
      :class="{
        'ring-4 ring-blue-200': true,
        'bg-blue-50 bg-opacity-30': true,
        'animate-pulse': true
      }"
      :style="spotlightStyle"
    >
      <!-- Efecto de brillo adicional -->
      <div class="absolute inset-0 rounded-lg bg-gradient-to-r from-blue-400 to-blue-600 opacity-20 animate-ping"></div>
    </div>
    
    <!-- Tooltip del tour -->
    <div 
      ref="tooltipRef"
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
            <span>Paso {{ currentStepIndex + 1 }} de {{ props.steps.length }}</span>
            <span>{{ Math.round(((currentStepIndex + 1) / props.steps.length) * 100) }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div 
              class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-500 ease-out"
              :style="{ width: `${((currentStepIndex + 1) / props.steps.length) * 100}%` }"
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
              v-for="(step, index) in props.steps" 
              :key="index"
              class="w-2 h-2 rounded-full transition-all duration-300"
              :class="index <= currentStepIndex ? 'bg-blue-500 scale-110' : 'bg-gray-300'"
            ></div>
          </div>
          
          <button 
            @click="nextStep"
            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-200 flex items-center gap-1 shadow-lg hover:shadow-xl transform hover:scale-105"
          >
            {{ currentStepIndex === props.steps.length - 1 ? 'Finalizar' : 'Siguiente' }}
            <svg v-if="currentStepIndex < props.steps.length - 1" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
  },
  section: {
    type: String,
    required: true
  },
  steps: {
    type: Array,
    required: true
  }
});

const emit = defineEmits(['complete']);

const page = usePage();
const showTour = ref(props.show);
const currentStepIndex = ref(0);
const tooltipRef = ref(null);

const currentStep = computed(() => {
  return props.steps[currentStepIndex.value];
});

const spotlightStyle = computed(() => {
  // Desactivar spotlight para el tour de perfil o dispositivos móviles
  if (props.section === 'profile' || isMobile.value) return {};
  
  if (!currentStep.value?.target) return {};
  
  const element = findElement(currentStep.value.target);
  if (!element) return {};
  
  const rect = element.getBoundingClientRect();
  return {
    left: `${rect.left - 12}px`,
    top: `${rect.top - 12}px`,
    width: `${rect.width + 24}px`,
    height: `${rect.height + 24}px`,
  };
});

// Función para encontrar el elemento con múltiples selectores
const findElement = (target) => {
  if (typeof target === 'string') {
    // Intentar el selector directamente primero
    try {
      const element = document.querySelector(target);
      if (element) return element;
    } catch (error) {
      console.log('❌ Selector inválido:', target, error);
    }
    
    // Si el target parece ser un ID simple (empieza con #), intentar selectores alternativos
    if (target.startsWith('#')) {
      const id = target.replace('#', '');
      const alternatives = [
        `input[id="${id}"]`,
        `#${id}`,
        `[id="${id}"]`,
        target
      ];
      
      for (const alt of alternatives) {
        try {
          const el = document.querySelector(alt);
          if (el) {
            console.log(`✅ Elemento encontrado con selector alternativo: ${alt}`);
            return el;
          }
        } catch (error) {
          console.log('❌ Selector alternativo inválido:', alt, error);
        }
      }
    }
  }
  
  return null;
};

const tooltipStyle = computed(() => {
  // Para el tour de perfil, usar posición fija centrada
  if (props.section === 'profile') {
    return {
      position: 'fixed',
      left: '50%',
      top: '50%',
      transform: 'translate(-50%, -50%)',
      zIndex: 9999,
      maxWidth: '90vw',
      maxHeight: '80vh'
    };
  }
  
  // Para dispositivos móviles, usar posición fija en la parte superior
  if (isMobile.value) {
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
  
  const element = findElement(currentStep.value.target);
  if (!element) {
    console.log('❌ Elemento no encontrado:', currentStep.value.target);
    return {};
  }
  
  // Debug temporal
  if (import.meta.env.DEV) {
    console.log('✅ Elemento encontrado:', element, 'Target:', currentStep.value.target, 'Step:', currentStepIndex.value + 1);
  }
  
  const rect = element.getBoundingClientRect();
  const position = currentStep.value.position || 'bottom';
  
  // Obtener dimensiones reales del tooltip si está disponible
  let tooltipWidth = 320;
  let tooltipHeight = 180;
  
  if (tooltipRef.value) {
    const tooltipRect = tooltipRef.value.getBoundingClientRect();
    tooltipWidth = tooltipRect.width;
    tooltipHeight = tooltipRect.height;
  }
  
  let left, top;
  const spacing = 60; // Aumentar espaciado para evitar solapamiento
  
  switch (position) {
    case 'bottom':
      // Centrar horizontalmente y colocar debajo con más espacio
      left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
      top = `${rect.bottom + spacing}px`;
      break;
    case 'top':
      // Centrar horizontalmente y colocar arriba con más espacio
      left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
      top = `${rect.top - tooltipHeight - spacing}px`;
      break;
    case 'left':
      // Colocar a la izquierda, centrado verticalmente
      left = `${rect.left - tooltipWidth - spacing}px`;
      top = `${rect.top + (rect.height / 2) - (tooltipHeight / 2)}px`;
      break;
    case 'right':
      // Colocar a la derecha, centrado verticalmente
      left = `${rect.right + spacing}px`;
      top = `${rect.top + (rect.height / 2) - (tooltipHeight / 2)}px`;
      break;
    default:
      // Por defecto, abajo y centrado
      left = `${rect.left + (rect.width / 2) - (tooltipWidth / 2)}px`;
      top = `${rect.bottom + spacing}px`;
  }
  
  // Asegurar que el tooltip esté dentro de la ventana
  const maxLeft = window.innerWidth - tooltipWidth - 20;
  const maxTop = window.innerHeight - tooltipHeight - 20;
  
  // Lógica mejorada para evitar solapamiento
  const tooltipLeft = parseInt(left);
  const tooltipTop = parseInt(top);
  
  // Si el tooltip se solapa con el elemento, ajustar posición
  if (position === 'right' && tooltipLeft < rect.right + 20) {
    // Si está muy cerca del elemento, mover más a la derecha
    left = `${rect.right + spacing + 20}px`;
  } else if (position === 'left' && tooltipLeft + tooltipWidth > rect.left - 20) {
    // Si está muy cerca del elemento, mover más a la izquierda
    left = `${rect.left - tooltipWidth - spacing - 20}px`;
  } else if (position === 'bottom' && tooltipTop < rect.bottom + 20) {
    // Si está muy cerca del elemento, mover más abajo
    top = `${rect.bottom + spacing + 20}px`;
  } else if (position === 'top' && tooltipTop + tooltipHeight > rect.top - 20) {
    // Si está muy cerca del elemento, mover más arriba
    top = `${rect.top - tooltipHeight - spacing - 20}px`;
  }
  
  // Asegurar que el tooltip esté dentro de la ventana
  if (parseInt(left) > maxLeft) {
    left = `${maxLeft}px`;
  }
  
  if (parseInt(top) > maxTop) {
    top = `${rect.top - tooltipHeight - spacing}px`;
  }
  
  if (parseInt(top) < 20) {
    top = `${rect.bottom + spacing}px`;
  }
  
  if (parseInt(left) < 20) {
    left = `${rect.right + spacing}px`;
  }
  
  return {
    left: `${Math.max(parseInt(left), 20)}px`,
    top: `${Math.max(parseInt(top), 20)}px`,
  };
});

const nextStep = () => {
  if (currentStepIndex.value < props.steps.length - 1) {
    currentStepIndex.value++;
    nextTick(() => {
      scrollToCurrentElement();
    });
  } else {
    completeTour();
  }
};

const previousStep = () => {
  if (currentStepIndex.value > 0) {
    currentStepIndex.value--;
    nextTick(() => {
      scrollToCurrentElement();
    });
  }
};

const skipTour = () => {
  completeTour();
};

const remindLater = async () => {
  try {
    await router.post(route('tour.remind-later'), {
      section: props.section
    }, {
      preserveScroll: true,
      onSuccess: () => {
        showTour.value = false;
        emit('complete');
      },
      onError: (errors) => {
        console.error('Error al programar recordatorio:', errors);
        showTour.value = false;
        emit('complete');
      }
    });
  } catch (error) {
    console.error('Error al programar recordatorio:', error);
    showTour.value = false;
    emit('complete');
  }
};

const neverShowAgain = async () => {
  try {
    await router.post(route('tour.never-show'), {
      section: props.section
    }, {
      preserveScroll: true,
      onSuccess: () => {
        showTour.value = false;
        emit('complete');
      },
      onError: (errors) => {
        console.error('Error al desactivar tour:', errors);
        showTour.value = false;
        emit('complete');
      }
    });
  } catch (error) {
    console.error('Error al desactivar tour:', error);
    showTour.value = false;
    emit('complete');
  }
};

const completeTour = async () => {
  try {
    await router.post(route('tour.complete'), {
      section: props.section
    }, {
      preserveScroll: true,
      onSuccess: () => {
        showTour.value = false;
        emit('complete');
      },
      onError: (errors) => {
        console.error('Error completing tour:', errors);
        showTour.value = false;
        emit('complete');
      }
    });
  } catch (error) {
    console.error('Error completing tour:', error);
    showTour.value = false;
    emit('complete');
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

const scrollToCurrentElement = () => {
  // Desactivar scroll automático para el tour de perfil o dispositivos móviles
  if (props.section === 'profile' || isMobile.value) return;
  
  nextTick(() => {
    if (currentStep.value?.target) {
      const element = findElement(currentStep.value.target);
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
          inline: 'center'
        });
      }
    }
  });
};

onMounted(async () => {
  if (showTour.value) {
    await nextTick();
    await waitForElements();
    scrollToCurrentElement();
  }
});

// Manejar redimensionamiento de ventana
const handleResize = () => {
  if (showTour.value) {
    nextTick();
  }
};

// Variable reactiva para el tamaño de pantalla
const isMobile = ref(window.innerWidth < 768);

// Actualizar el estado de móvil cuando cambie el tamaño
const updateMobileState = () => {
  isMobile.value = window.innerWidth < 768;
};

onMounted(() => {
  window.addEventListener('resize', () => {
    handleResize();
    updateMobileState();
  });
});

onUnmounted(() => {
  window.removeEventListener('resize', () => {
    handleResize();
    updateMobileState();
  });
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
