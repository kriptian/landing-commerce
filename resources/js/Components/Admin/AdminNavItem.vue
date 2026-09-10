<script setup>
import { Link } from '@inertiajs/vue3';
import { isNavigationItemActive } from '@/Navigation/adminNavigation';

const props = defineProps({
    item: { type: Object, required: true },
});

const emit = defineEmits(['navigate', 'upgrade']);

const iconPaths = {
    home: 'M3 11.5 12 4l9 7.5M5.5 10v9h13v-9M9.5 19v-5h5v5',
    cart: 'M4 5h2l1.4 8.2a2 2 0 0 0 2 1.7h7.8a2 2 0 0 0 2-1.6L20 8H7M10 19h.01M17 19h.01',
    orders: 'M7 4h10v16H7zM9.5 8h5M9.5 12h5M9.5 16h3',
    users: 'M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 11a3 3 0 0 0 0-6M17 14.5a4 4 0 0 1 4 4V20',
    ticket: 'M4 7h16v3a2 2 0 0 0 0 4v3H4v-3a2 2 0 0 0 0-4V7Zm8 2v6',
    box: 'm4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10',
    grid: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
    inventory: 'M4 8h16v12H4zM3 4h18v4H3zM9 12h6',
    brush: 'm14 5 5 5M12.5 6.5l5 5L9 20H4v-5L12.5 6.5Z',
    image: 'M4 5h16v14H4zM7 15l3-3 2.5 2.5L15 12l3 4M8.5 9h.01',
    file: 'M6 3h8l4 4v14H6zM14 3v5h4M9 13h6M9 17h6',
    chart: 'M5 19V9M12 19V5M19 19v-7M3 19h18',
    team: 'M8.5 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20v-2a4.5 4.5 0 0 1 4.5-4.5h3a4.5 4.5 0 0 1 4.5 4.5v2M16 6a3 3 0 0 1 0 6M16 14a4 4 0 0 1 4 4v2',
    store: 'M4 9h16l-1-5H5L4 9Zm1 0v11h14V9M9 20v-6h6v6',
    deploy: 'M12 3v12M7 8l5-5 5 5M5 14v6h14v-6',
};
</script>

<template>
    <button
        v-if="item.state === 'locked'"
        type="button"
        class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-500 transition hover:bg-white/5 hover:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400"
        @click="emit('upgrade')"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path :d="iconPaths[item.icon]" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
        <span class="rounded-md border border-amber-400/20 bg-amber-400/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300">Pro</span>
    </button>

    <Link
        v-else
        :href="route(item.routeName)"
        :aria-current="isNavigationItemActive(item) ? 'page' : undefined"
        class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400"
        :class="isNavigationItemActive(item) ? 'bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-950/20' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
        @click="emit('navigate')"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path :d="iconPaths[item.icon]" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
        <span
            v-if="item.badge > 0"
            class="flex min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[11px] font-bold text-white"
            :aria-label="`${item.badge} ordenes nuevas`"
        >{{ item.badge }}</span>
    </Link>
</template>
