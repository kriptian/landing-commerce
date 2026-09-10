<script setup>
import AdminNavItem from './AdminNavItem.vue';

defineProps({
    groups: { type: Array, required: true },
});

const emit = defineEmits(['navigate', 'upgrade']);
</script>

<template>
    <nav aria-label="Navegacion administrativa" class="space-y-6">
        <section v-for="group in groups" :key="group.label">
            <h2 class="mb-2 px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-600">
                {{ group.label }}
            </h2>
            <div class="space-y-1">
                <AdminNavItem
                    v-for="item in group.items"
                    :key="item.id"
                    :item="item"
                    @navigate="emit('navigate')"
                    @upgrade="emit('upgrade')"
                />
            </div>
        </section>
    </nav>
</template>
