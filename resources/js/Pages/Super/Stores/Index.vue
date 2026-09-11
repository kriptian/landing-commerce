<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Modal from '@/Components/Modal.vue';
import AlertModal from '@/Components/AlertModal.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({ stores: { type: Array, default: () => [] } });
const page = usePage();
const search = ref('');
const reminderStore = ref(null);
const storeToDeactivate = ref(null);
const storeToDelete = ref(null);
const imagePreview = ref(null);
const fileInput = ref(null);
const notice = ref({ show: Boolean(page.props.flash?.success), type: 'success', message: page.props.flash?.success || '' });

const form = useForm({
    message: '',
    mode: 'repeatable',
    repeat_interval: 24,
    repeat_unit: 'hours',
    pause_catalog: false,
    active: true,
    image: null,
    remove_image: false,
});

const filteredStores = computed(() => {
    const term = search.value.trim().toLowerCase();
    return term ? props.stores.filter((store) => [store.name, store.slug, store.plan].some((value) => String(value || '').toLowerCase().includes(term))) : props.stores;
});

const revokePreview = () => {
    if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;
};

const openReminder = (store) => {
    const reminder = store.payment_reminder;
    reminderStore.value = store;
    form.clearErrors();
    form.message = reminder?.message || `Hola, equipo de ${store.name}. Queremos recordarte que tienes un pago pendiente. Puedes realizarlo usando el codigo QR adjunto. Si ya pagaste, por favor ignora este mensaje.`;
    form.mode = reminder?.mode || 'repeatable';
    form.repeat_interval = reminder?.repeat_interval || 24;
    form.repeat_unit = reminder?.repeat_unit || 'hours';
    form.pause_catalog = Boolean(reminder?.pause_catalog);
    form.active = reminder?.active ?? true;
    form.image = null;
    form.remove_image = false;
    revokePreview();
    imagePreview.value = reminder?.image_url || null;
};

const closeReminder = () => {
    if (form.processing) return;
    reminderStore.value = null;
    form.clearErrors();
    revokePreview();
};

const selectImage = (event) => {
    const file = event.target.files?.[0] || null;
    revokePreview();
    form.image = file;
    form.remove_image = false;
    imagePreview.value = file ? URL.createObjectURL(file) : reminderStore.value?.payment_reminder?.image_url || null;
};

const removeImage = () => {
    revokePreview();
    form.image = null;
    form.remove_image = true;
    if (fileInput.value) fileInput.value.value = '';
};

const saveReminder = () => {
    form.post(route('super.stores.payment-reminder.update', reminderStore.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeReminder(),
    });
};

const deactivateReminder = () => router.delete(route('super.stores.payment-reminder.destroy', storeToDeactivate.value.id), {
    preserveScroll: true,
    onSuccess: () => { storeToDeactivate.value = null; },
});

const destroyStore = () => router.delete(route('super.stores.destroy', storeToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => { storeToDelete.value = null; },
});

onBeforeUnmount(revokePreview);
</script>

<template>
    <Head title="Súper Stores" />
    <AuthenticatedLayout>
        <AdminPage>
            <PageHeader eyebrow="Superadministracion" title="Súper Stores" description="Administra tiendas, planes y recordatorios de servicio desde un solo lugar."><template #actions><Link :href="route('super.stores.create')" class="ui-primary-button">+ Crear tienda</Link></template></PageHeader>

            <div class="ui-card mb-6 p-4"><label for="store-search" class="ui-label">Buscar tienda</label><input id="store-search" v-model="search" type="search" class="ui-input" placeholder="Nombre, enlace o plan" /></div>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="store in filteredStores" :key="store.id" class="ui-card flex flex-col p-5">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-extrabold uppercase tracking-wider text-indigo-600">Plan {{ store.plan?.replace('_', ' ') }}</p><h2 class="mt-2 truncate text-lg font-black text-slate-950">{{ store.name }}</h2><p class="mt-1 truncate text-sm text-slate-500">/{{ store.slug }}</p></div><span class="ui-badge-neutral">{{ store.users_count }} usuarios</span></div>
                    <div class="mt-5 rounded-2xl p-4" :class="store.payment_reminder?.active ? 'bg-amber-50 ring-1 ring-amber-200' : 'bg-slate-50'"><div class="flex items-center justify-between gap-3"><div><p class="text-xs font-extrabold uppercase tracking-wider" :class="store.payment_reminder?.active ? 'text-amber-700' : 'text-slate-400'">Recordatorio</p><p class="mt-1 text-sm font-bold text-slate-800">{{ store.payment_reminder?.active ? (store.payment_reminder.mode === 'persistent' ? 'Persistente' : `Cada ${store.payment_reminder.repeat_interval} ${store.payment_reminder.repeat_unit}`) : 'Sin alerta activa' }}</p></div><span v-if="store.payment_reminder?.active" class="h-2.5 w-2.5 rounded-full bg-amber-500"></span></div><p v-if="store.payment_reminder?.active && store.payment_reminder.pause_catalog" class="mt-3 border-t border-amber-200 pt-3 text-xs font-bold text-rose-700">Catalogo público pausado</p></div>
                    <div class="mt-auto grid grid-cols-2 gap-2 pt-5"><button type="button" class="ui-primary-button col-span-2" @click="openReminder(store)">{{ store.payment_reminder ? 'Configurar recordatorio' : 'Crear recordatorio' }}</button><Link :href="route('super.stores.edit', store.id)" class="ui-secondary-button">Editar tienda</Link><button type="button" class="ui-secondary-button text-rose-700" @click="storeToDelete = store">Eliminar</button><button v-if="store.payment_reminder?.active" type="button" class="col-span-2 mt-1 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-extrabold text-emerald-700 hover:bg-emerald-100" @click="storeToDeactivate = store">Pago confirmado / retirar alerta</button></div>
                </article>
                <div v-if="!filteredStores.length" class="ui-card col-span-full p-12 text-center text-sm text-slate-500">No encontramos tiendas con esa busqueda.</div>
            </section>
        </AdminPage>

        <Modal :show="Boolean(reminderStore)" max-width="2xl" @close="closeReminder">
            <form class="p-5 sm:p-7" @submit.prevent="saveReminder">
                <div><p class="text-xs font-extrabold uppercase tracking-[0.18em] text-indigo-600">{{ reminderStore?.name }}</p><h2 class="mt-2 text-2xl font-black text-slate-950">Recordatorio de pago</h2><p class="mt-1 text-sm text-slate-500">Este contenido solo sera visible dentro del panel administrativo de esta tienda.</p></div>
                <div class="mt-6 space-y-6">
                    <FormField id="reminder-message" label="Mensaje personalizado" :error="form.errors.message" required><template #default="field"><textarea id="reminder-message" v-model="form.message" rows="5" class="ui-input" :aria-describedby="field.describedBy" :aria-invalid="field.invalid"></textarea></template></FormField>
                    <div><label class="ui-label">Imagen o codigo QR</label><div class="grid gap-4 sm:grid-cols-[150px_1fr] sm:items-center"><div class="flex h-40 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-slate-50"><img v-if="imagePreview" :src="imagePreview" alt="Vista previa del QR" class="h-full w-full object-contain p-2" /><span v-else class="px-4 text-center text-xs text-slate-400">Sin imagen adjunta</span></div><div><input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-4 file:py-2.5 file:font-bold file:text-indigo-700" @change="selectImage" /><button v-if="imagePreview" type="button" class="mt-3 text-sm font-bold text-rose-600" @click="removeImage">Retirar imagen</button><p class="ui-help">JPG, PNG o WEBP. Maximo 5 MB. El archivo se guarda de forma privada.</p><p v-if="form.errors.image" class="ui-error">{{ form.errors.image }}</p></div></div></div>
                    <div><label class="ui-label">Comportamiento del aviso</label><div class="grid gap-3 sm:grid-cols-2"><label class="cursor-pointer rounded-2xl border p-4" :class="form.mode === 'repeatable' ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-100' : 'border-slate-200'"><input v-model="form.mode" type="radio" value="repeatable" class="text-indigo-600 focus:ring-indigo-500" /><strong class="ml-2 text-sm text-slate-900">Se puede cerrar</strong><span class="mt-2 block text-xs leading-5 text-slate-500">Reaparece despues del intervalo configurado.</span></label><label class="cursor-pointer rounded-2xl border p-4" :class="form.mode === 'persistent' ? 'border-rose-400 bg-rose-50 ring-2 ring-rose-100' : 'border-slate-200'"><input v-model="form.mode" type="radio" value="persistent" class="text-rose-600 focus:ring-rose-500" /><strong class="ml-2 text-sm text-slate-900">Persistente</strong><span class="mt-2 block text-xs leading-5 text-slate-500">No permite cerrar el aviso hasta retirarlo desde aqui.</span></label></div></div>
                    <div v-if="form.mode === 'repeatable'" class="grid grid-cols-[1fr_1.4fr] gap-3"><FormField id="repeat-interval" label="Repetir cada" :error="form.errors.repeat_interval"><template #default="field"><input id="repeat-interval" v-model.number="form.repeat_interval" type="number" min="1" max="10080" class="ui-input" :aria-describedby="field.describedBy" /></template></FormField><FormField id="repeat-unit" label="Unidad" :error="form.errors.repeat_unit"><template #default="field"><select id="repeat-unit" v-model="form.repeat_unit" class="ui-input" :aria-describedby="field.describedBy"><option value="minutes">Minutos</option><option value="hours">Horas</option><option value="days">Dias</option></select></template></FormField></div>
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4"><input v-model="form.pause_catalog" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /><span><strong class="block text-sm text-slate-900">Pausar temporalmente el catalogo público</strong><span class="mt-1 block text-xs leading-5 text-slate-500">Los visitantes veran un mensaje neutral. Productos, carrito y checkout no estaran disponibles mientras la alerta siga activa.</span></span></label>
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl bg-slate-950 p-4 text-white"><span><strong class="block text-sm">Activar recordatorio</strong><span class="mt-1 block text-xs text-slate-400">Si lo desactivas, la configuracion queda guardada como borrador.</span></span><input v-model="form.active" type="checkbox" class="rounded border-slate-500 text-cyan-400 focus:ring-cyan-400" /></label>
                </div>
                <div class="mt-7 flex justify-end gap-3"><button type="button" class="ui-secondary-button" @click="closeReminder">Cancelar</button><button type="submit" class="ui-primary-button" :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar configuracion' }}</button></div>
            </form>
        </Modal>

        <Modal :show="Boolean(storeToDeactivate)" @close="storeToDeactivate = null"><div class="p-6"><h2 class="text-xl font-black text-slate-950">Confirmar pago de {{ storeToDeactivate?.name }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">La alerta se retirara inmediatamente y, si el catalogo estaba pausado, volvera a estar disponible.</p><div class="mt-6 flex justify-end gap-3"><button type="button" class="ui-secondary-button" @click="storeToDeactivate = null">Cancelar</button><button type="button" class="ui-primary-button bg-emerald-600 hover:bg-emerald-700" @click="deactivateReminder">Confirmar pago</button></div></div></Modal>
        <Modal :show="Boolean(storeToDelete)" @close="storeToDelete = null"><div class="p-6"><h2 class="text-xl font-black text-slate-950">Eliminar {{ storeToDelete?.name }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">Se eliminaran la tienda y sus datos. Esta accion no se puede deshacer.</p><div class="mt-6 flex justify-end gap-3"><button type="button" class="ui-secondary-button" @click="storeToDelete = null">Cancelar</button><button type="button" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700" @click="destroyStore">Eliminar definitivamente</button></div></div></Modal>
        <AlertModal :show="notice.show" :type="notice.type" title="Súper Stores" :message="notice.message" primary-text="Entendido" @close="notice.show = false" @primary="notice.show = false" />
    </AuthenticatedLayout>
</template>
