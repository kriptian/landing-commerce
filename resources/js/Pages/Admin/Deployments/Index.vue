<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    runs: {
        type: Array,
        default: () => [],
    },
});

const runs = ref([...props.runs]);
const preparing = ref(false);
const timer = ref(null);
const activeStatuses = ['preparing', 'ready', 'queued', 'running'];
const activeRun = computed(() => runs.value.find((run) => activeStatuses.includes(run.status)) ?? null);
const readyRun = computed(() => runs.value.find((run) => run.status === 'ready') ?? null);

watch(() => props.runs, (updatedRuns) => {
    runs.value = [...updatedRuns];
});

const confirmationForm = useForm({
    commit_message: 'deploy: actualización de producción',
    confirmation: '',
});

const statusLabel = (status) => ({
    preparing: 'Preparando',
    ready: 'Listo para confirmar',
    queued: 'En cola',
    running: 'Desplegando',
    succeeded: 'Completado',
    failed: 'Fallido',
    cancelled: 'Descartado',
}[status] ?? status);

const statusClass = (status) => ({
    preparing: 'bg-blue-100 text-blue-800',
    ready: 'bg-amber-100 text-amber-800',
    queued: 'bg-indigo-100 text-indigo-800',
    running: 'bg-violet-100 text-violet-800',
    succeeded: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-100 text-slate-600',
}[status] ?? 'bg-gray-100 text-gray-700');

const prepare = () => {
    preparing.value = true;
    router.post(route('admin.deployments.prepare'), {}, {
        preserveScroll: true,
        onFinish: () => {
            preparing.value = false;
            router.reload({ only: ['runs'] });
        },
    });
};

const confirmDeployment = () => {
    if (!readyRun.value) return;

    confirmationForm.post(route('admin.deployments.confirm', readyRun.value.id), {
        preserveScroll: true,
        onSuccess: () => confirmationForm.reset('confirmation'),
    });
};

const discardPreparation = () => {
    if (!readyRun.value) return;

    router.post(route('admin.deployments.discard', readyRun.value.id), {}, {
        preserveScroll: true,
    });
};

const refreshActiveRun = async () => {
    const run = activeRun.value;
    if (!run) return;

    try {
        const response = await window.axios.get(route('admin.deployments.show', run.id));
        const index = runs.value.findIndex((item) => item.id === response.data.id);

        if (index === -1) {
            runs.value.unshift(response.data);
        } else {
            runs.value[index] = response.data;
        }
    } catch (error) {
        if ([401, 419, 423].includes(error.response?.status)) {
            window.clearInterval(timer.value);
        }
    }
};

onMounted(() => {
    timer.value = window.setInterval(refreshActiveRun, 2000);
});

onBeforeUnmount(() => window.clearInterval(timer.value));
</script>

<template>
    <Head title="Desplegar producción" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Operación local protegida</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Desplegar producción</h1>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:px-8">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-950 px-6 py-5 text-white">
                        <p class="text-sm text-slate-300">Paso 1</p>
                        <h2 class="text-lg font-semibold">Preparar y verificar</h2>
                    </div>

                    <div class="space-y-5 p-6">
                        <p class="text-sm leading-6 text-slate-600">
                            Se instalarán dependencias, compilarán assets y ejecutarán pruebas PHP, Chromium y auditorías. Después podrás revisar exactamente qué archivos se publicarán.
                        </p>

                        <ul class="grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                            <li class="rounded-lg bg-slate-50 px-3 py-2">Composer y npm</li>
                            <li class="rounded-lg bg-slate-50 px-3 py-2">Build de Vite</li>
                            <li class="rounded-lg bg-slate-50 px-3 py-2">Pruebas PHP y Dusk</li>
                            <li class="rounded-lg bg-slate-50 px-3 py-2">Auditorías de seguridad</li>
                        </ul>

                        <p v-if="$page.props.errors.deployment" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $page.props.errors.deployment }}
                        </p>

                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                            :disabled="Boolean(activeRun) || preparing"
                            dusk="prepare-deployment"
                            @click="prepare"
                        >
                            {{ preparing ? 'Iniciando...' : activeRun ? 'Hay un proceso activo' : 'Preparar despliegue' }}
                        </button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-sm text-slate-500">Paso 2</p>
                        <h2 class="text-lg font-semibold text-slate-900">Confirmar producción</h2>
                    </div>

                    <form class="space-y-4 p-6" @submit.prevent="confirmDeployment">
                        <p class="text-sm leading-6 text-slate-600">
                            Esta sección se habilita únicamente cuando todas las verificaciones terminan correctamente.
                        </p>

                        <div>
                            <label for="commit-message" class="text-sm font-medium text-slate-700">Mensaje del commit</label>
                            <input
                                id="commit-message"
                                v-model="confirmationForm.commit_message"
                                type="text"
                                maxlength="160"
                                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                :disabled="!readyRun"
                            >
                            <p v-if="confirmationForm.errors.commit_message" class="mt-1 text-sm text-red-600">{{ confirmationForm.errors.commit_message }}</p>
                        </div>

                        <div>
                            <label for="confirmation" class="text-sm font-medium text-slate-700">Escribe DESPLEGAR</label>
                            <input
                                id="confirmation"
                                v-model="confirmationForm.confirmation"
                                type="text"
                                autocomplete="off"
                                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                :disabled="!readyRun"
                                dusk="deployment-confirmation"
                            >
                            <p v-if="confirmationForm.errors.confirmation" class="mt-1 text-sm text-red-600">{{ confirmationForm.errors.confirmation }}</p>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                            :disabled="!readyRun || confirmationForm.processing || confirmationForm.confirmation !== 'DESPLEGAR'"
                            dusk="confirm-deployment"
                        >
                            Confirmar y desplegar
                        </button>
                        <button
                            v-if="readyRun"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="discardPreparation"
                        >
                            Descartar preparación
                        </button>
                    </form>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm text-slate-500">Actividad</p>
                            <h2 class="text-lg font-semibold text-slate-900">Historial y salida</h2>
                        </div>
                        <span v-if="activeRun" class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(activeRun.status)">
                            {{ statusLabel(activeRun.status) }}
                        </span>
                    </div>

                    <div v-if="runs.length" class="space-y-4">
                        <article v-for="run in runs" :key="run.id" class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ run.phase || 'Despliegue' }}</p>
                                    <p class="text-xs text-slate-500">{{ run.commit_sha ? run.commit_sha.slice(0, 10) : `Proceso #${run.id}` }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(run.status)">{{ statusLabel(run.status) }}</span>
                            </div>
                            <p v-if="run.error" class="border-t border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ run.error }}</p>
                            <pre v-if="run.output" class="max-h-80 overflow-auto whitespace-pre-wrap bg-slate-950 p-4 text-xs leading-5 text-slate-200">{{ run.output }}</pre>
                        </article>
                    </div>
                    <p v-else class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Todavía no hay despliegues.</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
