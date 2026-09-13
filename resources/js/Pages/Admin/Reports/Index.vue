<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPage from '@/Components/Admin/AdminPage.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    orders: Object,
    stats: Object,
    filters: Object,
    chartData: Object,
    physicalSales: Object,
    physicalSalesFilters: Object,
    expensesList: Object,
});

const page = usePage();
const reportType = ref(page.url.includes('type=physical') ? 'physical' : 'digital');
const activeTab = ref('activity');
const activePhysicalTab = ref('sales');

const filterForm = useForm({
    start_date: props.filters?.start_date || formatYMD(getRangeDates('last30')[0]),
    end_date: props.filters?.end_date || formatYMD(getRangeDates('last30')[1]),
});

const physicalSalesFilterForm = useForm({
    start_date: props.physicalSalesFilters?.start_date || '',
    end_date: props.physicalSalesFilters?.end_date || '',
    search: props.physicalSalesFilters?.search || '',
});

function formatYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getRangeDates(range) {
    const today = new Date();
    const end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    let start = new Date(end);

    if (range === 'last7') start.setDate(start.getDate() - 6);
    if (range === 'last30') start.setDate(start.getDate() - 29);
    if (range === 'thisMonth') start = new Date(end.getFullYear(), end.getMonth(), 1);
    if (range === 'lastMonth') {
        start = new Date(end.getFullYear(), end.getMonth() - 1, 1);
        return [start, new Date(end.getFullYear(), end.getMonth(), 0)];
    }
    return [start, end];
}

const applyFilters = () => {
    if (reportType.value === 'physical') {
        physicalSalesFilterForm.get(route('admin.reports.index', { type: 'physical' }), { preserveState: true, preserveScroll: true });
        return;
    }
    filterForm.get(route('admin.reports.index'), { preserveState: true, preserveScroll: true });
};

const setQuickRange = (range) => {
    if (!range) return;
    const [start, end] = getRangeDates(range);
    const form = reportType.value === 'physical' ? physicalSalesFilterForm : filterForm;
    form.start_date = formatYMD(start);
    form.end_date = formatYMD(end);
    applyFilters();
};

const activeRange = computed(() => {
    const form = reportType.value === 'physical' ? physicalSalesFilterForm : filterForm;
    return ['today', 'last7', 'last30', 'thisMonth', 'lastMonth'].find((range) => {
        const [start, end] = getRangeDates(range);
        return form.start_date === formatYMD(start) && form.end_date === formatYMD(end);
    }) || '';
});

const dailyActivity = computed(() => {
    const labels = props.chartData?.labels || [];
    return labels.map((label, index) => ({
        date: label,
        delivered: Number(props.chartData?.delivered?.[index] || 0),
        cancelled: Number(props.chartData?.cancelled?.[index] || 0),
    }));
});

const maxDailyActivity = computed(() => Math.max(1, ...dailyActivity.value.map((day) => day.delivered + day.cancelled)));
const activityWidth = (value) => `${Math.max(value ? 7 : 0, (value / maxDailyActivity.value) * 100)}%`;

const formatCurrency = (value) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format(Number(value || 0));

const formatDate = (value, withTime = false) => {
    if (!value) return '';
    return new Date(value).toLocaleString('es-CO', withTime
        ? { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatActivityDate = (value) => new Date(`${value}T12:00:00`).toLocaleDateString('es-CO', { weekday: 'short', day: 'numeric', month: 'short' });
const statusLabel = (status) => ({ recibido: 'Recibido', en_preparacion: 'En preparacion', despachado: 'Despachado', entregado: 'Entregado', cancelado: 'Cancelado' }[status] || status);
const statusClass = (status) => ({
    entregado: 'bg-emerald-50 text-emerald-700',
    recibido: 'bg-amber-50 text-amber-700',
    en_preparacion: 'bg-blue-50 text-blue-700',
    despachado: 'bg-violet-50 text-violet-700',
    cancelado: 'bg-rose-50 text-rose-700',
}[status] || 'bg-slate-100 text-slate-700');

const calculateSaleProfit = (sale) => {
    if (!sale?.items?.length) return '-';
    let total = 0;
    let hasAllCosts = true;
    sale.items.forEach((item) => {
        const cost = Number(item.purchase_price ?? item.variant?.purchase_price ?? item.product?.purchase_price ?? 0);
        if (cost <= 0) hasAllCosts = false;
        total += (Number(item.unit_price) - cost) * Number(item.quantity);
    });
    return hasAllCosts ? formatCurrency(total) : '-';
};

const calculateTotalDiscount = (sale) => {
    let total = Number(sale.discount || 0);
    sale.items?.forEach((item) => {
        const difference = Number(item.original_price || 0) - Number(item.unit_price || 0);
        if (difference > 0) total += difference * Number(item.quantity);
    });
    return formatCurrency(total);
};
</script>

<template>
    <Head title="Reportes de ventas" />
    <AuthenticatedLayout>
        <AdminPage>
            <PageHeader eyebrow="Analitica" title="Reportes de ventas" description="Consulta el movimiento de tu negocio por canal y periodo." />

            <div class="mb-6 flex flex-col gap-3 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <label for="report-channel" class="ui-label">Canal de venta</label>
                    <select id="report-channel" v-model="reportType" class="ui-input min-w-64">
                        <option value="digital">Tienda online</option>
                        <option value="physical">Punto de venta</option>
                    </select>
                </div>
                <p class="max-w-xl text-sm text-slate-500">{{ reportType === 'digital' ? 'Pedidos recibidos desde el catalogo publico.' : 'Ventas registradas directamente en caja y gastos operativos.' }}</p>
            </div>

            <template v-if="reportType === 'digital'">
                <form class="mb-7 border-b border-slate-200 pb-7" @submit.prevent="applyFilters">
                    <div class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                        <div><label for="digital-range" class="ui-label">Periodo rapido</label><select id="digital-range" :value="activeRange" class="ui-input" @change="setQuickRange($event.target.value)"><option value="">Personalizado</option><option value="today">Hoy</option><option value="last7">Ultimos 7 dias</option><option value="last30">Ultimos 30 dias</option><option value="thisMonth">Este mes</option><option value="lastMonth">Mes pasado</option></select></div>
                        <div><label for="start-date" class="ui-label">Desde</label><input id="start-date" v-model="filterForm.start_date" type="date" class="ui-input" /></div>
                        <div><label for="end-date" class="ui-label">Hasta</label><input id="end-date" v-model="filterForm.end_date" type="date" class="ui-input" /></div>
                        <div class="flex items-center gap-3"><button type="submit" class="ui-primary-button" :disabled="filterForm.processing">Consultar</button><Link :href="route('admin.reports.index')" class="text-sm font-bold text-slate-500 hover:text-slate-900">Restablecer</Link></div>
                    </div>
                </form>

                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="text-sm font-semibold text-slate-500">Ventas del periodo</p><p class="mt-1 text-3xl font-black tracking-tight text-slate-950">{{ formatCurrency(stats?.totalSales) }}</p></div>
                    <nav class="flex gap-5 border-b border-slate-200" aria-label="Vista del reporte"><button type="button" class="border-b-2 pb-2 text-sm font-bold" :class="activeTab === 'activity' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500'" @click="activeTab = 'activity'">Actividad diaria</button><button type="button" class="border-b-2 pb-2 text-sm font-bold" :class="activeTab === 'orders' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500'" @click="activeTab = 'orders'">Pedidos</button></nav>
                </div>

                <section v-if="activeTab === 'activity'" class="ui-card overflow-hidden">
                    <header class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-extrabold text-slate-900">Movimiento diario</h2><p class="mt-1 text-sm text-slate-500">Compara entregas y cancelaciones sin perder espacio en un grafico vacio.</p></div><div class="flex gap-4 text-xs font-bold text-slate-600"><span class="flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-full bg-emerald-500"></i>Entregados</span><span class="flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-full bg-rose-400"></i>Cancelados</span></div></header>
                    <div v-if="dailyActivity.length" class="divide-y divide-slate-100 px-5">
                        <div v-for="day in dailyActivity" :key="day.date" class="grid gap-2 py-4 sm:grid-cols-[140px_1fr_80px] sm:items-center"><span class="text-sm font-bold capitalize text-slate-700">{{ formatActivityDate(day.date) }}</span><div class="flex h-3 overflow-hidden rounded-full bg-slate-100"><span class="bg-emerald-500" :style="{ width: activityWidth(day.delivered) }"></span><span class="bg-rose-400" :style="{ width: activityWidth(day.cancelled) }"></span></div><span class="text-right text-xs font-semibold text-slate-500">{{ day.delivered }} / {{ day.cancelled }}</span></div>
                    </div>
                    <div v-else class="p-12 text-center"><p class="font-bold text-slate-700">Todavia no hay actividad en este periodo</p><p class="mt-1 text-sm text-slate-500">Prueba con un rango de fechas mas amplio.</p></div>
                </section>

                <section v-else class="ui-card overflow-hidden">
                    <header class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-extrabold text-slate-900">Pedidos del periodo</h2><p class="mt-1 text-sm text-slate-500">Abre un pedido para consultar productos, cantidades y seguimiento.</p></div><a v-if="orders?.data?.length" :href="route('admin.reports.export', { start_date: filters?.start_date, end_date: filters?.end_date })" class="ui-secondary-button">Exportar Excel</a></header>
                    <div class="overflow-x-auto"><table class="min-w-[800px] w-full"><thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Pedido</th><th class="px-5 py-3">Cliente</th><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="order in orders?.data" :key="order.id" class="hover:bg-slate-50"><td class="px-5 py-4 font-bold text-slate-900">#{{ order.sequence_number ?? order.id }}</td><td class="px-5 py-4 text-sm text-slate-600">{{ order.customer_name }}</td><td class="px-5 py-4 text-sm text-slate-500">{{ formatDate(order.created_at) }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(order.status)">{{ statusLabel(order.status) }}</span></td><td class="px-5 py-4 text-right font-extrabold text-slate-900">{{ formatCurrency(order.total_price) }}</td><td class="px-5 py-4 text-right"><Link :href="route('admin.orders.show', order.id)" class="text-sm font-bold text-indigo-700">Ver detalle</Link></td></tr></tbody></table></div>
                    <div v-if="!orders?.data?.length" class="p-12 text-center text-sm text-slate-500">No hay pedidos en este periodo.</div><Pagination v-if="orders?.links" class="border-t border-slate-100 p-5" :links="orders.links" />
                </section>
            </template>

            <template v-else>
                <nav class="mb-6 flex gap-6 border-b border-slate-200" aria-label="Movimiento de caja"><button type="button" class="border-b-2 pb-3 text-sm font-bold" :class="activePhysicalTab === 'sales' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500'" @click="activePhysicalTab = 'sales'">Ventas</button><button type="button" class="border-b-2 pb-3 text-sm font-bold" :class="activePhysicalTab === 'expenses' ? 'border-rose-500 text-rose-700' : 'border-transparent text-slate-500'" @click="activePhysicalTab = 'expenses'">Gastos y salidas</button></nav>

                <form class="mb-7 border-b border-slate-200 pb-7" @submit.prevent="applyFilters">
                    <div class="grid gap-4" :class="activePhysicalTab === 'sales' ? 'lg:grid-cols-[1fr_1fr_1fr_1fr_auto]' : 'lg:grid-cols-[1fr_1fr_1fr_auto]'">
                        <div v-if="activePhysicalTab === 'sales'"><label for="sale-search" class="ui-label">Buscar venta</label><input id="sale-search" v-model="physicalSalesFilterForm.search" type="search" class="ui-input" placeholder="Numero o producto" /></div>
                        <div><label for="physical-range" class="ui-label">Periodo rapido</label><select id="physical-range" :value="activeRange" class="ui-input" @change="setQuickRange($event.target.value)"><option value="">Todo el historial</option><option value="today">Hoy</option><option value="last7">Ultimos 7 dias</option><option value="last30">Ultimos 30 dias</option><option value="thisMonth">Este mes</option><option value="lastMonth">Mes pasado</option></select></div>
                        <div><label for="physical-start" class="ui-label">Desde</label><input id="physical-start" v-model="physicalSalesFilterForm.start_date" type="date" class="ui-input" /></div>
                        <div><label for="physical-end" class="ui-label">Hasta</label><input id="physical-end" v-model="physicalSalesFilterForm.end_date" type="date" class="ui-input" /></div>
                        <div class="flex items-end gap-3"><button type="submit" class="ui-primary-button" :disabled="physicalSalesFilterForm.processing">Consultar</button><Link :href="route('admin.reports.index', { type: 'physical' })" class="pb-2 text-sm font-bold text-slate-500 hover:text-slate-900">Restablecer</Link></div>
                    </div>
                </form>

                <section v-if="activePhysicalTab === 'sales'" class="ui-card overflow-hidden">
                    <header v-if="physicalSales?.data?.length" class="flex justify-end border-b border-slate-200 p-5"><a :href="route('admin.physical-sales.export', { start_date: physicalSalesFilters?.start_date, end_date: physicalSalesFilters?.end_date, search: physicalSalesFilters?.search })" class="ui-secondary-button">Exportar reporte</a></header>
                    <div class="overflow-x-auto"><table class="min-w-[980px] w-full"><thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Venta</th><th class="px-5 py-3">Vendedor</th><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Pago</th><th class="px-5 py-3 text-right">Descuento</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3 text-right">Ganancia</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="sale in physicalSales?.data" :key="sale.id" class="hover:bg-slate-50"><td class="px-5 py-4 font-bold text-slate-900">{{ sale.sale_number }}</td><td class="px-5 py-4 text-sm text-slate-600">{{ sale.user?.name || 'Sin asignar' }}</td><td class="px-5 py-4 text-sm text-slate-500">{{ formatDate(sale.created_at, true) }}</td><td class="px-5 py-4 text-sm capitalize text-slate-600">{{ sale.payment_method }}</td><td class="px-5 py-4 text-right text-sm text-slate-500">{{ calculateTotalDiscount(sale) }}</td><td class="px-5 py-4 text-right font-extrabold text-slate-900">{{ formatCurrency(sale.total) }}</td><td class="px-5 py-4 text-right text-sm font-bold text-emerald-700">{{ calculateSaleProfit(sale) }}</td><td class="px-5 py-4 text-right"><Link :href="route('admin.physical-sales.show', sale.id)" class="text-sm font-bold text-indigo-700">Ver detalle</Link></td></tr></tbody></table></div>
                    <div v-if="!physicalSales?.data?.length" class="p-12 text-center text-sm text-slate-500">No hay ventas en este periodo.</div><Pagination v-if="physicalSales?.links" class="border-t border-slate-100 p-5" :links="physicalSales.links" />
                </section>

                <section v-else class="ui-card overflow-hidden">
                    <header class="flex justify-end border-b border-slate-200 p-5"><a :href="route('admin.physical-sales.export', { start_date: physicalSalesFilters?.start_date, end_date: physicalSalesFilters?.end_date })" class="ui-secondary-button">Exportar reporte</a></header>
                    <div class="overflow-x-auto"><table class="min-w-[680px] w-full"><thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Descripcion</th><th class="px-5 py-3">Registrado por</th><th class="px-5 py-3 text-right">Monto</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="expense in expensesList?.data" :key="expense.id" class="hover:bg-slate-50"><td class="px-5 py-4 text-sm text-slate-500">{{ formatDate(expense.expense_date, true) }}</td><td class="px-5 py-4 font-semibold text-slate-800">{{ expense.description }}</td><td class="px-5 py-4 text-sm text-slate-500">{{ expense.user?.name || 'Sin asignar' }}</td><td class="px-5 py-4 text-right font-extrabold text-rose-700">{{ formatCurrency(expense.amount) }}</td></tr></tbody></table></div>
                    <div v-if="!expensesList?.data?.length" class="p-12 text-center text-sm text-slate-500">No hay gastos en este periodo.</div><Pagination v-if="expensesList?.links" class="border-t border-slate-100 p-5" :links="expensesList.links" />
                </section>
            </template>
        </AdminPage>
    </AuthenticatedLayout>
</template>
