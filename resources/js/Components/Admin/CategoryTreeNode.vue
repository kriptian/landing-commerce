<script setup>
import { Link } from '@inertiajs/vue3';

defineOptions({ name: 'CategoryTreeNode' });

const props = defineProps({
    category: { type: Object, required: true },
    expandedIds: { type: Set, required: true },
    searching: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'delete']);
</script>

<template>
    <li>
        <div class="group rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 hover:shadow-sm sm:p-4">
            <div class="flex items-start gap-3">
                <button
                    v-if="category.children.length"
                    type="button"
                    class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :aria-label="`${expandedIds.has(category.id) || searching ? 'Ocultar' : 'Mostrar'} subcategorias de ${category.name}`"
                    :aria-expanded="expandedIds.has(category.id) || searching"
                    @click="emit('toggle', category.id)"
                >
                    <svg class="h-4 w-4 transition" :class="{ 'rotate-90': expandedIds.has(category.id) || searching }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 0 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <span v-else class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700" title="Categoria disponible para productos">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.5 5.75A2.75 2.75 0 0 1 5.25 3h3.086c.73 0 1.43.29 1.945.805l.664.665c.235.234.553.366.884.366h2.921a2.75 2.75 0 0 1 2.75 2.75v6.664A2.75 2.75 0 0 1 14.75 17h-9.5a2.75 2.75 0 0 1-2.75-2.75v-8.5Z" /></svg>
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h3 class="font-semibold text-slate-900">{{ category.name }}</h3>
                        <span v-if="category.children_count === 0" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Lista para usar</span>
                    </div>
                    <p class="mt-1 truncate text-xs text-slate-500" :title="category.path">{{ category.path }}</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                        <span class="rounded-md bg-slate-100 px-2 py-1">
                            {{ category.subtree_products_count }} {{ category.subtree_products_count === 1 ? 'producto' : 'productos' }}
                        </span>
                        <span v-if="category.children_count" class="rounded-md bg-slate-100 px-2 py-1">
                            {{ category.children_count }} {{ category.children_count === 1 ? 'subcategoria' : 'subcategorias' }}
                        </span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Link
                        :href="route('admin.categories.edit', category.id)"
                        class="inline-flex h-9 items-center rounded-lg px-2.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :aria-label="`Editar ${category.name}`"
                    >
                        Editar
                    </Link>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center rounded-lg px-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500"
                        :aria-label="`Eliminar ${category.name}`"
                        @click="emit('delete', category)"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>

        <ul
            v-if="category.children.length && (expandedIds.has(category.id) || searching)"
            class="ml-4 space-y-2 border-l-2 border-slate-200 py-2 pl-3 sm:ml-8 sm:pl-5"
        >
            <CategoryTreeNode
                v-for="child in category.children"
                :key="child.id"
                :category="child"
                :expanded-ids="expandedIds"
                :searching="searching"
                @toggle="emit('toggle', $event)"
                @delete="emit('delete', $event)"
            />
        </ul>
    </li>
</template>
