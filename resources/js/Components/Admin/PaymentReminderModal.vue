<script setup>
import Modal from '@/Components/Modal.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    reminder: { type: Object, default: null },
    userId: { type: [Number, String], required: true },
});

const show = ref(false);
let timer = null;

const isPersistent = computed(() => props.reminder?.mode === 'persistent');
const storageKey = computed(() => props.reminder
    ? `payment-reminder:${props.userId}:${props.reminder.id}:${props.reminder.version}`
    : null);

const intervalMilliseconds = computed(() => {
    const value = Math.max(1, Number(props.reminder?.repeat_interval || 1));
    const multiplier = { minutes: 60000, hours: 3600000, days: 86400000 }[props.reminder?.repeat_unit] || 3600000;
    return value * multiplier;
});

const evaluateVisibility = () => {
    if (! props.reminder) {
        show.value = false;
        return;
    }
    if (isPersistent.value) {
        show.value = true;
        return;
    }

    const dismissedUntil = Number(localStorage.getItem(storageKey.value) || 0);
    show.value = Date.now() >= dismissedUntil;
};

const dismiss = () => {
    if (isPersistent.value || ! storageKey.value) return;
    localStorage.setItem(storageKey.value, String(Date.now() + intervalMilliseconds.value));
    show.value = false;
};

const checkStatus = async () => {
    if (! props.reminder) return;
    try {
        const { data } = await window.axios.get(route('admin.payment-reminder.status'));
        if (! data.active) {
            show.value = false;
            return;
        }
        if (data.id !== props.reminder.id || data.version !== props.reminder.version) {
            router.reload({ only: ['paymentReminder'] });
            return;
        }
        evaluateVisibility();
    } catch (_) {
        // Keep the current reminder visible if its status cannot be verified.
    }
};

watch(() => props.reminder, evaluateVisibility, { deep: true });

onMounted(() => {
    evaluateVisibility();
    timer = window.setInterval(checkStatus, 30000);
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <Modal :show="show" max-width="lg" :closeable="!isPersistent" @close="dismiss">
        <article dusk="payment-reminder" class="overflow-hidden rounded-lg bg-white">
            <div class="bg-slate-950 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-extrabold uppercase tracking-[0.2em] text-cyan-300">Aviso administrativo</p><h2 class="mt-2 text-2xl font-black">Recordatorio de servicio</h2></div>
                    <button v-if="!isPersistent" dusk="payment-reminder-close" type="button" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-slate-300 hover:bg-white/20 hover:text-white" aria-label="Cerrar recordatorio" @click="dismiss">×</button>
                </div>
            </div>
            <div class="p-6">
                <img v-if="reminder?.image_url" :src="reminder.image_url" alt="Informacion para realizar el pago" class="mx-auto max-h-72 w-auto max-w-full rounded-2xl border border-slate-200 bg-white object-contain p-2 shadow-sm" />
                <p class="whitespace-pre-line text-center text-base leading-7 text-slate-700" :class="reminder?.image_url ? 'mt-5' : ''">{{ reminder?.message }}</p>
                <div v-if="isPersistent" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-sm font-semibold text-amber-900">Este aviso requiere atencion antes de continuar usando el panel.</div>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                    <Link :href="route('logout')" method="post" as="button" class="ui-secondary-button">Cerrar sesion</Link>
                    <button v-if="!isPersistent" dusk="payment-reminder-dismiss" type="button" class="ui-primary-button" @click="dismiss">Entendido</button>
                </div>
            </div>
        </article>
    </Modal>
</template>
