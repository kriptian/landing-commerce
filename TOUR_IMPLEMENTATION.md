# Tour Guiado para Usuarios Nuevos

## Descripción
Se ha implementado un sistema de tour guiado que se activa automáticamente para usuarios nuevos en su primer login. El tour explica las funcionalidades principales de cada pestaña del sistema.

## Archivos Modificados/Creados

### Backend (Laravel)
1. **Migración**: `database/migrations/2024_12_19_000000_add_tour_fields_to_users_table.php`
   - Agrega campos `first_login` y `tour_completed_at` a la tabla users

2. **Controlador**: `app/Http/Controllers/TourController.php`
   - Maneja la finalización del tour y el estado del mismo

3. **Rutas**: `routes/web.php`
   - Agregadas rutas para `/tour/complete` y `/tour/status`

4. **Controlador de Autenticación**: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
   - Detecta si es el primer login del usuario

### Frontend (Vue.js)
1. **Componente Tour**: `resources/js/Components/UserTour.vue`
   - Componente principal del tour con spotlight effect
   - 8 pasos que explican las funcionalidades principales

2. **Layout**: `resources/js/Layouts/AuthenticatedLayout.vue`
   - Integra el componente UserTour
   - Maneja la lógica de mostrar/ocultar el tour

3. **Dashboard**: `resources/js/Pages/Dashboard.vue`
   - Agregado ID `dashboard-welcome` para el primer paso del tour

## Pasos del Tour

1. **¡Bienvenido!** - Explica el panel de administración principal
2. **Navegación Principal** - Muestra las pestañas principales del menú
3. **Gestión de Productos** - Explica cómo crear y gestionar productos
4. **Categorías** - Explica el sistema de categorías
5. **Órdenes (Plan Negociante)** - Funcionalidad de gestión de pedidos
6. **Reportes (Plan Negociante)** - Sistema de reportes y análisis
7. **Inventario (Plan Negociante)** - Control de stock y alertas
8. **Perfil de Usuario** - Acceso a configuración personal

## Características del Tour

- **Spotlight Effect**: Resalta el elemento actual con un borde azul
- **Tooltip Inteligente**: Se posiciona automáticamente según el elemento
- **Navegación**: Botones Anterior/Siguiente y opción de saltar
- **Progreso Visual**: Indicadores de pasos completados
- **Responsive**: Funciona en dispositivos móviles y desktop
- **Persistencia**: Se marca como completado en la base de datos

## Instalación

1. **Ejecutar la migración**:
   ```bash
   php artisan migrate
   ```

2. **El tour se activará automáticamente** para usuarios nuevos en su primer login

## Personalización

Para modificar los pasos del tour, editar el array `tourSteps` en `resources/js/Components/UserTour.vue`:

```javascript
const tourSteps = ref([
  {
    target: '#elemento-css-selector',
    title: 'Título del paso',
    content: 'Descripción del paso',
    position: 'bottom' // top, bottom, left, right
  },
  // ... más pasos
]);
```

## Testing

Para probar el tour:
1. Crear un nuevo usuario
2. Hacer login por primera vez
3. El tour se activará automáticamente
4. Completar o saltar el tour
5. En futuros logins no se mostrará

## Notas Técnicas

- El tour usa CSS puro para el spotlight effect
- Se posiciona automáticamente para evitar que salga de la pantalla
- Es compatible con todos los navegadores modernos
- No requiere librerías externas
