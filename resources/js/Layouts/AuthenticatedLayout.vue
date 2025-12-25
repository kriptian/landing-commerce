<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import AlertModal from '@/Components/AlertModal.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import UserTour from '@/Components/UserTour.vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    show_tour: {
        type: Boolean,
        default: false
    }
});

const showingNavigationDropdown = ref(false);

const store = usePage().props.auth.user.store;
const plan = ref((store && store.plan) ? store.plan : 'emprendedor');
const isNegociante = computed(() => plan.value === 'negociante' || (usePage().props.auth?.isSuperAdmin === true));

// Tour del usuario
const page = usePage();
const showTour = computed(() => {
  const value = page.props.show_tour;
  // Convertir string "1" a boolean true, string "0" a boolean false
  if (typeof value === 'string') {
    return value === '1' || value === 'true';
  }
  return Boolean(value);
});

const showUpgradeStep1 = ref(false);
const showUpgradeStep2 = ref(false);

const openUpgrade = () => { showUpgradeStep1.value = true; };
const toStep2 = () => { showUpgradeStep1.value = false; showUpgradeStep2.value = true; };
const cancelUpgrade = () => { showUpgradeStep1.value = false; showUpgradeStep2.value = false; };
const whatsappUpgradeHref = () => {
  try {
    const storeName = store?.name || 'Mi tienda';
    const text = `Hola, soy la tienda ${storeName} y deseo mejorar mi plan.`;
    const encoded = encodeURIComponent(text);
    return `https://wa.me/573024061771?text=${encoded}`;
  } catch (e) { return `https://wa.me/573024061771`; }
};

// Permitir que otras vistas abran el flujo de upgrade (p.ej., cards del Dashboard)
const handleExternalUpgrade = () => openUpgrade();
onMounted(() => {
  try { window.addEventListener('open-upgrade-plan', handleExternalUpgrade); } catch (e) {}
});
onBeforeUnmount(() => {
  try { window.removeEventListener('open-upgrade-plan', handleExternalUpgrade); } catch (e) {}
});
const can = (perm) => {
  const p = usePage().props.auth?.permissions || [];
  const roles = usePage().props.auth?.roles || [];
  const isStoreAdmin = Array.isArray(roles) && roles.includes('Administrador');
  if (isStoreAdmin) return true; // Rol Administrador ve todo (excepto SuperStores que depende de is_admin)
  return Array.isArray(p) && p.includes(perm);
};

// Toast de confirmación
const showToast = ref(false);
const toastMessage = ref('');
const confirmUpgrade = () => {
  try {
    router.post(route('store.upgrade'), {}, {
      preserveScroll: true,
      onSuccess: () => {
        plan.value = 'negociante';
        toastMessage.value = 'Tu plan fue actualizado a Negociante';
        showToast.value = true;
        try { setTimeout(() => { showToast.value = false; }, 4000); } catch (e) {}
        try { router.reload({ only: ['auth'] }); } catch (e) {}
      },
    });
  } finally {
    cancelUpgrade();
  }
};

// Manejar finalización del tour
const handleTourComplete = () => {
  // El tour se cierra automáticamente cuando page.props.show_tour cambia
  // No necesitamos hacer nada aquí, el computed se actualiza automáticamente
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center space-x-3">
                                <Link :href="route('dashboard')">
                                    <img v-if="store && store.logo_url" :src="store.logo_url" class="block h-9 w-9 rounded-full object-cover">
                                    <ApplicationLogo v-else class="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                                <span v-if="store" class="font-semibold text-gray-800">{{ store.name }}</span>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink v-if="can('ver dashboard')" :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                                
                                <NavLink v-if="can('ver inventario')" :href="route('admin.physical-sales.index')" :active="route().current('admin.physical-sales.*')">
                                    Ventas
                                </NavLink>
                                
                                <NavLink v-if="isNegociante && (can('ver ordenes') || can('gestionar ordenes'))" :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                                    <div class="relative flex items-center">
                                        <span>Órdenes</span>
                                        <span v-if="$page.props.adminNotifications.newOrdersCount > 0" 
                                              class="ms-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                                            {{ $page.props.adminNotifications.newOrdersCount }}
                                        </span>
                                    </div>
                                </NavLink>
                                <button v-else type="button" @click="openUpgrade" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-400 hover:text-gray-500 cursor-pointer">
                                    Órdenes
                                </button>

                                <NavLink v-if="isNegociante" :href="route('admin.customers.index')" :active="route().current('admin.customers.*') || route().current('admin.coupons.*')">
                                    Clientes
                                </NavLink>

                                <NavLink v-if="isNegociante && can('ver reportes')" :href="route('admin.reports.index')" :active="route().current('admin.reports.index')">Reportes</NavLink>
                                <button v-else type="button" @click="openUpgrade" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-400 hover:text-gray-500">Reportes</button>
                                
                                <NavLink v-if="isNegociante && can('ver inventario')" :href="route('admin.inventory.index')" :active="route().current('admin.inventory.index')">Inventario</NavLink>
                                <button v-else type="button" @click="openUpgrade" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-400 hover:text-gray-500">Inventario</button>
                                
                                <NavLink v-if="isNegociante" :href="route('admin.catalog-customization.index')" :active="route().current('admin.catalog-customization.*')">Personalizar catálogo</NavLink>
                                
                                <button v-if="!isNegociante" type="button" @click="openUpgrade" class="inline-flex items-center gap-2 text-green-700 hover:text-green-800">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                    Mejorar plan
                                </button>
                                
                                <NavLink v-if="$page.props.auth?.isSuperAdmin" :href="route('super.stores.index')" :active="route().current('super.stores.*')">
                                    SuperStores
                                </NavLink>
                                </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>

                        <ResponsiveNavLink v-if="can('ver inventario')" :href="route('admin.physical-sales.index')" :active="route().current('admin.physical-sales.*')">
                            Ventas
                        </ResponsiveNavLink>

                        <ResponsiveNavLink v-if="isNegociante" :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                            <div class="relative flex items-center">
                                <span>Órdenes</span>
                                <span v-if="$page.props.adminNotifications.newOrdersCount > 0" 
                                      class="ms-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                                    {{ $page.props.adminNotifications.newOrdersCount }}
                                </span>
                            </div>
                        </ResponsiveNavLink>
                        <button v-else type="button" class="w-full text-left px-3 py-2 text-gray-400 hover:text-gray-500" @click="openUpgrade">Órdenes</button>

                        <ResponsiveNavLink v-if="isNegociante" :href="route('admin.customers.index')" :active="route().current('admin.customers.*') || route().current('admin.coupons.*')">Clientes</ResponsiveNavLink>

                        <ResponsiveNavLink v-if="isNegociante" :href="route('admin.reports.index')" :active="route().current('admin.reports.index')">Reportes</ResponsiveNavLink>
                        <button v-else type="button" class="w-full text-left px-3 py-2 text-gray-400 hover:text-gray-500" @click="openUpgrade">Reportes</button>

                        <ResponsiveNavLink v-if="isNegociante" :href="route('admin.inventory.index')" :active="route().current('admin.inventory.index')">Inventario</ResponsiveNavLink>
                        <button v-else type="button" class="w-full text-left px-3 py-2 text-gray-400 hover:text-gray-500" @click="openUpgrade">Inventario</button>
                        
                        <ResponsiveNavLink v-if="isNegociante" :href="route('admin.catalog-customization.index')" :active="route().current('admin.catalog-customization.*')">Personalizar catálogo</ResponsiveNavLink>
                        
                        <button v-if="!isNegociante" type="button" class="w-full text-left px-3 py-2 text-green-700 hover:text-green-800" @click="openUpgrade">Mejorar plan</button>
                        <ResponsiveNavLink v-if="$page.props.auth?.isSuperAdmin" :href="route('super.stores.index')" :active="route().current('super.stores.*')">
                            SuperStores
                        </ResponsiveNavLink>
                        </div>

                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header
                class="bg-white shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
            <!-- Modales de mejora de plan (dentro del template principal) -->
            <AlertModal :show="showUpgradeStep1" type="warning" title="Activar funcionalidades avanzadas" message="Hacer uso de estas funcionalidades puede tener costo extra. ¿Deseas activarlo?" primary-text="Sí, continuar" secondary-text="Cancelar" @primary="toStep2" @secondary="cancelUpgrade" @close="cancelUpgrade" />
            <AlertModal :show="showUpgradeStep2" type="warning" title="Confirmación final" message="Estás a punto de activar funcionalidades avanzadas. Esto tiene un costo adicional. ¿Deseas continuar?" primary-text="Sí, activar plan" secondary-text="Volver" :primary-href="null" @primary="confirmUpgrade" @secondary="() => { showUpgradeStep2 = false; showUpgradeStep1 = true; }" @close="cancelUpgrade" />
            
            <!-- Toast de confirmación -->
            <transition 
                enter-active-class="transition ease-out duration-200" 
                enter-from-class="translate-y-2 opacity-0" 
                enter-to-class="translate-y-0 opacity-100" 
                leave-active-class="transition ease-in duration-150" 
                leave-from-class="opacity-100" 
                leave-to-class="opacity-0 translate-y-2">
                <div v-if="showToast" class="fixed top-6 right-6 z-[60]">
                    <div class="rounded-lg bg-white shadow-lg border border-green-200 text-green-800 px-4 py-3 text-sm">
                        {{ toastMessage }}
                    </div>
                </div>
            </transition>
            
            <!-- Tour del usuario -->
            <UserTour 
                :show="showTour" 
                @complete="handleTourComplete"
            />
        </div>
    </div>
</template>