# Guía de Tours por Sección

## Descripción
Sistema de tours contextuales que se activan cuando un usuario nuevo ingresa a diferentes secciones del sistema por primera vez.

## Archivos Creados

### Backend
1. **Migración**: `database/migrations/2024_12_19_000001_add_tour_tracking_to_users_table.php`
   - Agrega campo `completed_tours` (JSON) para rastrear tours completados por sección

2. **Modelo User**: Actualizado con `completed_tours` en fillable y casts

3. **TourController**: Actualizado para manejar tours específicos por sección

### Frontend
1. **SectionTour.vue**: Componente reutilizable para tours de sección
2. **useSectionTour.js**: Composable con lógica de tours por sección
3. **Tours definidos**: Para usuarios, productos, categorías, órdenes, reportes e inventario

## Cómo Agregar Tours a una Nueva Sección

### 1. Agregar pasos del tour en `useSectionTour.js`

```javascript
const tourSteps = {
  // ... tours existentes ...
  
  nueva_seccion: [
    {
      target: 'h2',
      title: 'Título del Paso',
      content: 'Descripción de lo que hace este elemento.',
      position: 'bottom'
    },
    {
      target: '.elemento-especifico',
      title: 'Otro Paso',
      content: 'Otra descripción.',
      position: 'top'
    }
  ]
};
```

### 2. Integrar en la página Vue

```vue
<script setup>
import SectionTour from '@/Components/SectionTour.vue';
import { useSectionTour } from '@/utils/useSectionTour.js';

// Tour de sección
const { showTour, steps, handleTourComplete } = useSectionTour('nueva_seccion');
</script>

<template>
  <!-- Tu contenido existente -->
  
  <!-- Tour de sección -->
  <SectionTour 
    :show="showTour" 
    section="nueva_seccion"
    :steps="steps"
    @complete="handleTourComplete"
  />
</template>
```

## Tours Disponibles

### 1. **Usuarios** (`users`)
- Gestión de usuarios
- Crear nuevo usuario
- Lista de usuarios

### 2. **Productos** (`products`)
- Gestión de productos
- Crear producto
- Lista de productos

### 3. **Categorías** (`categories`)
- Gestión de categorías
- Crear categoría
- Lista de categorías

### 4. **Órdenes** (`orders`)
- Gestión de órdenes
- Lista de órdenes
- Filtros por estado

### 5. **Reportes** (`reports`)
- Reportes y análisis
- Métricas principales
- Gráficos

### 6. **Inventario** (`inventory`)
- Control de inventario
- Lista de productos
- Exportar inventario

## Lógica del Sistema

### Cuándo se Muestra un Tour
1. **Usuario NO es nuevo** (ya completó el tour principal)
2. **No ha completado** el tour de esa sección específica
3. **Ingresa por primera vez** a esa sección

### Tracking
- Cada tour completado se guarda en `completed_tours` (array JSON)
- El tour principal se marca en `first_login = false`
- Tours de sección se marcan individualmente

## Personalización

### Posiciones del Tooltip
- `bottom`: Debajo del elemento
- `top`: Encima del elemento
- `left`: A la izquierda del elemento
- `right`: A la derecha del elemento

### Selectores CSS
- Usa selectores CSS específicos para apuntar a elementos
- Ejemplos: `h2`, `.clase`, `#id`, `a[href*="create"]`

## Instalación

1. **Ejecutar migración**:
   ```bash
   php artisan migrate
   ```

2. **El sistema funciona automáticamente** - no requiere configuración adicional

## Ejemplo Completo

```vue
<!-- En resources/js/Pages/MiNuevaSeccion/Index.vue -->
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SectionTour from '@/Components/SectionTour.vue';
import { useSectionTour } from '@/utils/useSectionTour.js';

const { showTour, steps, handleTourComplete } = useSectionTour('mi_nueva_seccion');
</script>

<template>
  <AuthenticatedLayout>
    <div>
      <h2>Mi Nueva Sección</h2>
      <!-- Contenido de la página -->
    </div>
    
    <!-- Tour de sección -->
    <SectionTour 
      :show="showTour" 
      section="mi_nueva_seccion"
      :steps="steps"
      @complete="handleTourComplete"
    />
  </AuthenticatedLayout>
</template>
```

## Notas Importantes

- Los tours solo aparecen para usuarios que ya completaron el tour principal
- Cada tour se muestra solo una vez por usuario
- Los tours son opcionales - el usuario puede saltarlos
- El sistema es completamente automático una vez configurado
