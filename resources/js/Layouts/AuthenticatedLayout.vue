<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AlertModal from '@/Components/AlertModal.vue';
import AdminNavigation from '@/Components/Admin/AdminNavigation.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { resolveAdminNavigation } from '@/Navigation/adminNavigation';

const page = usePage();
const drawerOpen = ref(false);
const drawer = ref(null);
const drawerTrigger = ref(null);
const showUpgradeStep1 = ref(false);
const showUpgradeStep2 = ref(false);

const auth = computed(() => page.props.auth || {});
const store = computed(() => auth.value.store || auth.value.user?.store || null);
const plan = computed(() => store.value?.plan || 'emprendedor');
const isPos = computed(() => route().current('admin.physical-sales.index'));
const navigationGroups = computed(() => resolveAdminNavigation(auth.value, page.props.adminNotifications));
const shellHomeHref = computed(() => {
    if (auth.value.capabilities?.dashboard === 'enabled') return route('dashboard');
    const firstEnabled = navigationGroups.value
        .flatMap((group) => group.items)
        .find((item) => item.state === 'enabled');
    return firstEnabled ? route(firstEnabled.routeName) : route('profile.edit');
});
const catalogUrl = computed(() => store.value?.slug
    ? route('catalogo.index', { store: store.value.slug })
    : null);
const whatsappUpgradeHref = computed(() => {
    const text = encodeURIComponent(`Hola, soy la tienda ${store.value?.name || 'Mi tienda'} y deseo mejorar mi plan.`);
    return `https://wa.me/573208204198?text=${text}`;
});

const openUpgrade = () => {
    drawerOpen.value = false;
    showUpgradeStep1.value = true;
};
const cancelUpgrade = () => {
    showUpgradeStep1.value = false;
    showUpgradeStep2.value = false;
};
const toUpgradeContact = () => {
    showUpgradeStep1.value = false;
    showUpgradeStep2.value = true;
};
const closeDrawer = () => {
    drawerOpen.value = false;
};

const handleKeydown = (event) => {
    if (! drawerOpen.value) return;

    if (event.key === 'Escape') {
        closeDrawer();
        return;
    }

    if (event.key !== 'Tab') return;
    const focusable = drawer.value?.querySelectorAll('a[href], button:not([disabled])') || [];
    if (! focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (! event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const handleExternalUpgrade = () => openUpgrade();

watch(drawerOpen, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        await nextTick();
        drawer.value?.querySelector('a[href], button:not([disabled])')?.focus();
    } else {
        drawerTrigger.value?.focus();
    }
});

watch(() => page.url, closeDrawer);

onMounted(() => {
    window.addEventListener('open-upgrade-plan', handleExternalUpgrade);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('open-upgrade-plan', handleExternalUpgrade);
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <main v-if="isPos" id="main-content">
        <slot />
    </main>

    <div v-else class="min-h-screen bg-[#f4f6f8] text-slate-900">
        <a href="#main-content" class="sr-only z-[70] rounded-lg bg-cyan-400 px-4 py-2 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            Ir al contenido
        </a>

        <aside dusk="admin-sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col overflow-hidden bg-slate-950 lg:flex">
            <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
                <Link :href="shellHomeHref" class="flex min-w-0 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">
                    <img v-if="store?.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-10 w-10 rounded-xl object-cover ring-1 ring-white/10">
                    <span v-else class="flex h-10 w-10 items-center justify-center rounded-xl bg-white p-2">
                        <ApplicationLogo class="h-6 w-auto fill-current text-slate-900" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-bold text-white">{{ store?.name || 'Mi tienda' }}</span>
                        <span class="block text-xs capitalize text-slate-500">Plan {{ plan.replace('_', ' ') }}</span>
                    </span>
                </Link>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-6 [scrollbar-width:thin] [scrollbar-color:#334155_transparent]">
                <AdminNavigation :groups="navigationGroups" @upgrade="openUpgrade" />
            </div>

            <div class="border-t border-white/10 p-4">
                <a
                    v-if="catalogUrl"
                    :href="catalogUrl"
                    target="_blank"
                    rel="noopener"
                    class="mb-3 flex items-center justify-between rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white"
                >
                    Ver tienda
                    <span aria-hidden="true">↗</span>
                </a>
                <button
                    v-if="plan === 'emprendedor'"
                    type="button"
                    class="mb-3 w-full rounded-xl bg-gradient-to-r from-cyan-400 to-emerald-400 px-3 py-2.5 text-sm font-bold text-slate-950 transition hover:brightness-105"
                    @click="openUpgrade"
                >
                    Mejorar plan
                </button>
                <div class="flex items-center gap-3 rounded-xl bg-white/[0.04] p-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-cyan-300">
                        {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-white">{{ auth.user?.name }}</span>
                        <Link :href="route('profile.edit')" class="text-xs text-slate-500 hover:text-cyan-300">Ver perfil</Link>
                    </span>
                    <Link :href="route('logout')" method="post" as="button" class="rounded-lg p-2 text-slate-500 hover:bg-white/5 hover:text-white" aria-label="Cerrar sesion">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur lg:hidden">
            <div class="flex min-w-0 items-center gap-3">
                <img v-if="store?.logo_url" :src="store.logo_url" :alt="`Logo de ${store.name}`" class="h-9 w-9 rounded-xl object-cover">
                <ApplicationLogo v-else class="h-8 w-auto fill-current text-slate-900" />
                <span class="truncate text-sm font-bold">{{ store?.name || 'Mi tienda' }}</span>
            </div>
            <button
                ref="drawerTrigger"
                dusk="admin-mobile-trigger"
                type="button"
                class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500"
                aria-label="Abrir menu"
                aria-controls="admin-mobile-drawer"
                :aria-expanded="drawerOpen"
                @click="drawerOpen = true"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" /></svg>
            </button>
        </header>

        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="drawerOpen" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click.self="closeDrawer">
                <aside
                    id="admin-mobile-drawer"
                    ref="drawer"
                    dusk="admin-mobile-drawer"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="admin-drawer-title"
                    class="flex h-full w-[min(88vw,22rem)] flex-col bg-slate-950 shadow-2xl"
                >
                    <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
                        <div>
                            <p id="admin-drawer-title" class="font-bold text-white">{{ store?.name || 'Mi tienda' }}</p>
                            <p class="text-xs capitalize text-slate-500">Plan {{ plan.replace('_', ' ') }}</p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-white/5 hover:text-white" aria-label="Cerrar menu" @click="closeDrawer">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-4 py-6">
                        <AdminNavigation :groups="navigationGroups" @navigate="closeDrawer" @upgrade="openUpgrade" />
                    </div>
                    <div class="space-y-2 border-t border-white/10 p-4">
                        <a v-if="catalogUrl" :href="catalogUrl" target="_blank" rel="noopener" class="block rounded-xl bg-white/5 px-3 py-2.5 text-sm font-medium text-slate-300">Ver tienda ↗</a>
                        <Link :href="route('profile.edit')" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300" @click="closeDrawer">Perfil</Link>
                        <Link :href="route('logout')" method="post" as="button" class="w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium text-rose-300">Cerrar sesion</Link>
                    </div>
                </aside>
            </div>
        </Transition>

        <div class="lg:pl-72">
            <header v-if="$slots.header" class="border-b border-slate-200/80 bg-white">
                <div class="mx-auto max-w-[92rem] px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>
            <main id="main-content" class="admin-content min-w-0">
                <slot />
            </main>
        </div>

        <AlertModal
            :show="showUpgradeStep1"
            type="info"
            title="Lleva tu tienda al siguiente nivel"
            message="Las funciones marcadas como Pro requieren el plan Negociante. Te ayudaremos a elegir la mejor opcion para tu tienda."
            primary-text="Ver opciones"
            secondary-text="Ahora no"
            @primary="toUpgradeContact"
            @secondary="cancelUpgrade"
            @close="cancelUpgrade"
        />
        <AlertModal
            :show="showUpgradeStep2"
            type="warning"
            title="Habla con nuestro equipo"
            message="La activacion requiere confirmar el servicio y el pago. Continuaremos por WhatsApp sin cambiar tu plan automaticamente."
            primary-text="Abrir WhatsApp"
            secondary-text="Volver"
            :primary-href="whatsappUpgradeHref"
            @primary="cancelUpgrade"
            @secondary="() => { showUpgradeStep2 = false; showUpgradeStep1 = true; }"
            @close="cancelUpgrade"
        />
    </div>
</template>
