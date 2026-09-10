export const adminNavigationGroups = [
    {
        label: 'Resumen',
        items: [
            { id: 'dashboard', label: 'Inicio', routeName: 'dashboard', active: ['dashboard'], icon: 'home' },
        ],
    },
    {
        label: 'Ventas',
        items: [
            { id: 'physicalSales', label: 'Punto de venta', routeName: 'admin.physical-sales.index', active: ['admin.physical-sales.*'], icon: 'cart' },
            { id: 'orders', label: 'Ordenes', routeName: 'admin.orders.index', active: ['admin.orders.*'], icon: 'orders', badge: 'newOrdersCount' },
            { id: 'customers', label: 'Clientes', routeName: 'admin.customers.index', active: ['admin.customers.*'], icon: 'users' },
            { id: 'coupons', label: 'Cupones', routeName: 'admin.coupons.index', active: ['admin.coupons.*'], icon: 'ticket' },
        ],
    },
    {
        label: 'Catalogo',
        items: [
            { id: 'products', label: 'Productos', routeName: 'admin.products.index', active: ['admin.products.*'], icon: 'box' },
            { id: 'categories', label: 'Categorias', routeName: 'admin.categories.index', active: ['admin.categories.*'], icon: 'grid' },
            { id: 'inventory', label: 'Inventario', routeName: 'admin.inventory.index', active: ['admin.inventory.*'], icon: 'inventory' },
            { id: 'catalogCustomization', label: 'Personalizar tienda', routeName: 'admin.catalog-customization.index', active: ['admin.catalog-customization.*'], icon: 'brush' },
            { id: 'gallery', label: 'Galeria', routeName: 'admin.gallery-images.index', active: ['admin.gallery-images.*'], icon: 'image' },
            { id: 'pdfCatalogBuilder', label: 'Catalogo PDF', routeName: 'admin.pdf-catalog-builder.index', active: ['admin.pdf-catalog-builder.*'], icon: 'file' },
        ],
    },
    {
        label: 'Analisis',
        items: [
            { id: 'reports', label: 'Reportes', routeName: 'admin.reports.index', active: ['admin.reports.*'], icon: 'chart' },
        ],
    },
    {
        label: 'Equipo',
        items: [
            { id: 'users', label: 'Usuarios y roles', routeName: 'admin.users.index', active: ['admin.users.*', 'admin.roles.*'], icon: 'team' },
        ],
    },
    {
        label: 'Sistema',
        items: [
            { id: 'superStores', label: 'Tiendas', routeName: 'super.stores.index', active: ['super.stores.*'], icon: 'store' },
            { id: 'deployments', label: 'Despliegues', routeName: 'admin.deployments.index', active: ['admin.deployments.*'], icon: 'deploy' },
        ],
    },
];

export const resolveAdminNavigation = (auth, notifications = {}) => (
    adminNavigationGroups
        .map((group) => ({
            ...group,
            items: group.items
                .map((item) => ({
                    ...item,
                    state: auth?.capabilities?.[item.id] || 'hidden',
                    badge: item.badge ? notifications[item.badge] || 0 : 0,
                }))
                .filter((item) => item.state !== 'hidden'),
        }))
        .filter((group) => group.items.length > 0)
);

export const isNavigationItemActive = (item) => (
    item.active.some((pattern) => route().current(pattern))
);
