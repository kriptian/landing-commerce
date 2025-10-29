import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useSectionTour(section) {
  const page = usePage();
  
  // Verificar si el usuario es nuevo y no ha completado este tour específico
  const shouldShowTour = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    
    // Si es el primer login, no mostrar tours de sección hasta completar el principal
    if (user.first_login) return false;
    
    // Verificar si el tour está en "nunca mostrar"
    const neverShowTours = user.never_show_tours || [];
    if (neverShowTours.includes(section)) return false;
    
    // Verificar si el tour está programado para recordar
    const remindLaterTours = user.remind_later_tours || [];
    if (remindLaterTours.includes(section)) return true;
    
    // Si no está en recordatorios, verificar si ya completó este tour específico
    const completedTours = user.completed_tours || [];
    return !completedTours.includes(section);
  });
  
  const showTour = ref(shouldShowTour.value);
  
  // Tours específicos por sección
  const tourSteps = {
    users: [
      {
        target: 'h2',
        title: 'Gestión de Usuarios',
        content: 'Aquí puedes crear y gestionar los usuarios de tu tienda. Cada usuario puede tener diferentes roles y permisos.',
        position: 'bottom'
      },
      {
        target: 'a[href*="create"]',
        title: 'Crear Nuevo Usuario',
        content: 'Haz clic aquí para agregar un nuevo usuario a tu tienda. Podrás asignarle un rol específico.',
        position: 'bottom'
      },
      {
        target: 'table',
        title: 'Lista de Usuarios',
        content: 'Esta tabla muestra todos los usuarios de tu tienda. Puedes editar, eliminar o cambiar roles desde aquí.',
        position: 'top'
      }
    ],
    'users-create': [
      {
        target: 'h2',
        title: 'Crear Nuevo Usuario',
        content: 'Completa este formulario para agregar un nuevo usuario a tu tienda.',
        position: 'bottom'
      },
      {
        target: 'input[name="name"]',
        title: 'Nombre del Usuario',
        content: 'Ingresa el nombre completo del usuario que quieres agregar.',
        position: 'right'
      },
      {
        target: 'input[name="email"]',
        title: 'Correo Electrónico',
        content: 'El email será usado para que el usuario inicie sesión en el sistema.',
        position: 'right'
      },
      {
        target: 'select[name="role"]',
        title: 'Asignar Rol',
        content: 'Selecciona el rol que tendrá este usuario: Administrador, Vendedor, etc.',
        position: 'right'
      },
      {
        target: 'button[type="submit"]',
        title: 'Guardar Usuario',
        content: 'Haz clic aquí para crear el usuario con la información proporcionada.',
        position: 'top'
      }
    ],
    products: [
      {
        target: 'h2',
        title: 'Gestión de Productos',
        content: 'Aquí puedes crear, editar y organizar todos los productos de tu tienda.',
        position: 'bottom'
      },
      {
        target: 'a[href*="create"]',
        title: 'Crear Producto',
        content: 'Haz clic aquí para agregar un nuevo producto. Podrás configurar precios, inventario, variantes y más.',
        position: 'bottom'
      },
      {
        target: 'table',
        title: 'Lista de Productos',
        content: 'Esta tabla muestra todos tus productos. Puedes editar, eliminar o ver detalles de cada uno.',
        position: 'top'
      }
    ],
    'products-create': [
      {
        target: 'h2',
        title: 'Crear Nuevo Producto',
        content: 'Completa este formulario para agregar un nuevo producto a tu tienda.',
        position: 'bottom'
      },
      {
        target: 'input[name="name"]',
        title: 'Nombre del Producto',
        content: 'Ingresa el nombre que aparecerá en tu catálogo. Sé descriptivo para que los clientes lo encuentren fácilmente.',
        position: 'right'
      },
      {
        target: 'input[name="price"]',
        title: 'Precio Principal',
        content: 'Establece el precio de venta de tu producto. Este será el precio que verán tus clientes.',
        position: 'right'
      },
      {
        target: 'input[type="checkbox"][name="track_inventory"]',
        title: 'Control de Inventario',
        content: 'Activa esta opción si quieres controlar cuántas unidades tienes disponibles.',
        position: 'right'
      },
      {
        target: 'input[name="quantity"]',
        title: 'Cantidad en Stock',
        content: 'Especifica cuántas unidades tienes disponibles. Si usas variantes, esto se calculará automáticamente.',
        position: 'right'
      },
      {
        target: 'select[name="category_id"]',
        title: 'Categoría del Producto',
        content: 'Selecciona la categoría que mejor describa tu producto. Esto ayuda a los clientes a encontrarlo.',
        position: 'right'
      },
      {
        target: 'textarea[name="short_description"]',
        title: 'Descripción Corta',
        content: 'Escribe una descripción breve que aparecerá en las listas de productos.',
        position: 'right'
      },
      {
        target: 'textarea[name="long_description"]',
        title: 'Descripción Detallada',
        content: 'Proporciona información detallada sobre las características y beneficios de tu producto.',
        position: 'right'
      },
      {
        target: 'input[type="file"]',
        title: 'Imágenes del Producto',
        content: 'Sube imágenes de alta calidad de tu producto. Los clientes compran con los ojos.',
        position: 'right'
      },
      {
        target: 'button[type="submit"]',
        title: 'Crear Producto',
        content: 'Haz clic aquí para guardar tu producto y agregarlo al catálogo.',
        position: 'top'
      }
    ],
    categories: [
      {
        target: 'h2',
        title: 'Gestión de Categorías',
        content: 'Organiza tus productos en categorías para que tus clientes puedan encontrarlos fácilmente.',
        position: 'bottom'
      },
      {
        target: 'a[href*="create"]',
        title: 'Crear Categoría',
        content: 'Haz clic aquí para crear una nueva categoría. Puedes crear categorías principales y subcategorías.',
        position: 'bottom'
      },
      {
        target: 'table',
        title: 'Lista de Categorías',
        content: 'Esta tabla muestra todas tus categorías organizadas jerárquicamente.',
        position: 'top'
      }
    ],
    'categories-create': [
      {
        target: 'h2',
        title: 'Crear Nueva Categoría',
        content: 'Completa este formulario para agregar una nueva categoría a tu tienda.',
        position: 'bottom'
      },
      {
        target: 'input[name="name"]',
        title: 'Nombre de la Categoría',
        content: 'Ingresa un nombre claro y descriptivo para tu categoría. Ejemplo: "Electrodomésticos", "Ropa de Hombre".',
        position: 'right'
      },
      {
        target: 'textarea[name="description"]',
        title: 'Descripción de la Categoría',
        content: 'Opcional: Describe qué tipo de productos pertenecen a esta categoría.',
        position: 'right'
      },
      {
        target: 'select[name="parent_id"]',
        title: 'Categoría Padre',
        content: 'Si quieres crear una subcategoría, selecciona la categoría principal. Déjalo vacío para crear una categoría principal.',
        position: 'right'
      },
      {
        target: 'input[type="file"]',
        title: 'Imagen de la Categoría',
        content: 'Opcional: Sube una imagen representativa de esta categoría.',
        position: 'right'
      },
      {
        target: 'button[type="submit"]',
        title: 'Crear Categoría',
        content: 'Haz clic aquí para guardar tu nueva categoría.',
        position: 'top'
      }
    ],
    orders: [
      {
        target: 'h2',
        title: 'Gestión de Órdenes',
        content: 'Aquí puedes ver y gestionar todos los pedidos de tus clientes. Controla el flujo completo desde la recepción hasta la entrega.',
        position: 'bottom'
      },
      {
        target: 'input[placeholder*="buscar"], input[placeholder*="search"], input[type="search"], input[name*="search"]',
        title: 'Buscar Órdenes',
        content: 'Busca órdenes por número, cliente o cualquier término específico.',
        position: 'bottom'
      },
      {
        target: 'select[name*="status"], select[name*="estado"], select:first-of-type',
        title: 'Filtrar por Estado',
        content: 'Filtra órdenes por estado: Pendiente, Confirmada, En Proceso, Enviada, Entregada, Cancelada.',
        position: 'bottom'
      },
      {
        target: 'table th:first-child',
        title: 'Número de Orden',
        content: 'Identificador único de cada orden. Haz clic para ver detalles completos.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(2)',
        title: 'Cliente',
        content: 'Nombre del cliente que realizó la orden.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(3)',
        title: 'Teléfono',
        content: 'Número de teléfono de contacto del cliente.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(4)',
        title: 'Fecha',
        content: 'Fecha y hora en que se realizó la orden.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(5)',
        title: 'Items',
        content: 'Cantidad de productos incluidos en la orden.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(6)',
        title: 'Total',
        content: 'Valor total de la orden incluyendo productos, impuestos y envío.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(7)',
        title: 'Estado',
        content: 'Estado actual de la orden. Puedes cambiarlo haciendo clic en el botón de acciones.',
        position: 'bottom'
      },
      {
        target: 'table th:last-child',
        title: 'Acciones',
        content: 'Desde aquí puedes ver detalles, editar, cambiar estado o cancelar la orden.',
        position: 'bottom'
      }
    ],
    reports: [
      {
        target: 'h2',
        title: 'Reportes y Análisis',
        content: 'Aquí puedes generar y visualizar reportes detallados sobre el rendimiento de tu tienda.',
        position: 'bottom'
      },
      {
        target: 'button[href*="reports/sales"]',
        title: 'Reporte de Ventas',
        content: 'Genera reportes de ventas por período, producto o cliente. Incluye gráficos y estadísticas.',
        position: 'bottom'
      },
      {
        target: 'button[href*="reports/inventory"]',
        title: 'Reporte de Inventario',
        content: 'Analiza el movimiento de stock, productos más vendidos y alertas de inventario bajo.',
        position: 'bottom'
      },
      {
        target: 'button[href*="reports/customers"]',
        title: 'Reporte de Clientes',
        content: 'Visualiza información detallada sobre tus clientes: compras, preferencias y comportamiento.',
        position: 'bottom'
      },
      {
        target: 'input[type="date"]',
        title: 'Filtro de Fechas',
        content: 'Selecciona el rango de fechas para generar reportes específicos.',
        position: 'bottom'
      },
      {
        target: '.grid',
        title: 'Métricas Principales',
        content: 'Estas tarjetas muestran las métricas más importantes: ventas totales, órdenes, clientes activos.',
        position: 'bottom'
      },
      {
        target: 'canvas',
        title: 'Gráficos Interactivos',
        content: 'Los gráficos te ayudan a visualizar tendencias, comparar períodos y identificar patrones.',
        position: 'top'
      },
      {
        target: 'button[href*="export"]',
        title: 'Exportar Reportes',
        content: 'Exporta cualquier reporte a Excel o PDF para análisis externos o presentaciones.',
        position: 'bottom'
      }
    ],
    inventory: [
      {
        target: 'h2',
        title: 'Control de Inventario',
        content: 'Aquí puedes gestionar el stock de tus productos, recibir alertas y controlar el flujo de mercancía.',
        position: 'bottom'
      },
      {
        target: 'input[placeholder*="buscar"], input[placeholder*="search"], input[type="search"], input[name*="search"]',
        title: 'Buscar Productos',
        content: 'Busca productos por nombre, categoría o código de barras.',
        position: 'bottom'
      },
      {
        target: 'select[name*="category"], select[name*="categoria"], select:first-of-type',
        title: 'Filtrar por Categoría',
        content: 'Filtra productos por categoría para una vista más organizada.',
        position: 'bottom'
      },
      {
        target: 'table th:first-child',
        title: 'Producto',
        content: 'Nombre e imagen del producto en el inventario.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(2)',
        title: 'Stock Actual',
        content: 'Cantidad disponible en inventario. Los números en rojo indican stock bajo.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(3)',
        title: 'Precio de Compra',
        content: 'Precio de compra del producto para control de costos.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(4)',
        title: 'Precio de Venta',
        content: 'Precio al que se vende el producto al cliente.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(5)',
        title: 'Porcentaje de Ganancia',
        content: 'Porcentaje de ganancia sobre el precio de compra.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(6)',
        title: 'Ganancia Estimada',
        content: 'Ganancia total estimada si se vende todo el stock actual.',
        position: 'bottom'
      },
      {
        target: 'table th:nth-child(7)',
        title: 'Estado',
        content: 'Estado del producto: Activo, Inactivo, Agotado o Stock Bajo.',
        position: 'bottom'
      },
      {
        target: 'table th:last-child',
        title: 'Acciones',
        content: 'Desde aquí puedes editar, eliminar, ajustar stock o ver historial de movimientos.',
        position: 'bottom'
      },
      {
        target: 'a[href*="export"], button[class*="bg-green"]',
        title: 'Exportar Inventario',
        content: 'Exporta tu inventario completo a Excel para análisis externos o auditorías.',
        position: 'bottom'
      }
    ],
    profile: [
      {
        target: 'h2',
        title: 'Perfil de Usuario',
        content: 'Aquí puedes actualizar tu información personal, cambiar contraseña y configurar tu cuenta.',
        position: 'bottom'
      },
      {
        target: 'input[id="name"]',
        title: 'Tu Nombre',
        content: 'Actualiza tu nombre completo que aparecerá en la plataforma.',
        position: 'right'
      },
      {
        target: 'input[id="email"]',
        title: 'Email',
        content: 'Tu dirección de correo electrónico para notificaciones y acceso.',
        position: 'right'
      },
      {
        target: 'input[id="phone"]',
        title: 'WhatsApp de la Tienda',
        content: 'Número de WhatsApp para que los clientes puedan contactarte.',
        position: 'right'
      },
      {
        target: 'input[id="store_name"]',
        title: 'Nombre de la Tienda',
        content: 'El nombre que aparecerá en tu tienda pública.',
        position: 'right'
      },
      {
        target: 'input[type="file"]',
        title: 'Logo de la Tienda',
        content: 'Sube el logo que representará tu tienda en el catálogo público.',
        position: 'right'
      },
      {
        target: 'input[id="custom_domain"]',
        title: 'Dominio Propio',
        content: 'Opcional: Configura tu propio dominio personalizado para tu tienda.',
        position: 'right'
      },
      {
        target: 'input[id="facebook_url"]',
        title: 'URL de Facebook',
        content: 'Enlace a tu página de Facebook para que los clientes puedan seguirte.',
        position: 'right'
      },
      {
        target: 'input[id="instagram_url"]',
        title: 'URL de Instagram',
        content: 'Enlace a tu perfil de Instagram para mostrar tus productos.',
        position: 'right'
      },
      {
        target: 'input[id="tiktok_url"]',
        title: 'URL de TikTok',
        content: 'Enlace a tu perfil de TikTok para promocionar tu tienda.',
        position: 'right'
      },
      {
        target: 'button[type="submit"]',
        title: 'Guardar Cambios',
        content: 'Haz clic aquí para guardar todas las modificaciones realizadas.',
        position: 'top'
      }
    ]
  };
  
  const steps = tourSteps[section] || [];
  
  const handleTourComplete = () => {
    showTour.value = false;
  };
  
  return {
    showTour,
    steps,
    handleTourComplete
  };
}
