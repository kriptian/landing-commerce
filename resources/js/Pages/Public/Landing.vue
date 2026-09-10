<script setup>
import CookieConsent from '@/Components/CookieConsent.vue';
import { Head, Link } from '@inertiajs/vue3';

const whatsapp = 'https://wa.me/573208204198?text=Hola%2C%20quiero%20conocer%20los%20planes%20de%20Ondigital%20Solution';
const features = [
    { number: '01', title: 'Publica tu catalogo', text: 'Organiza productos, fotos, precios y opciones sin conocimientos tecnicos.' },
    { number: '02', title: 'Recibe y registra ventas', text: 'Atiende pedidos online y ventas presenciales desde el mismo lugar.' },
    { number: '03', title: 'Controla tu negocio', text: 'Consulta inventario, clientes y resultados con informacion comprensible.' },
];
</script>

<template>
    <Head title="Tu tienda lista para vender" />
    <div class="min-h-screen bg-white text-slate-950">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
            <nav class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="Navegacion principal">
                <a href="#inicio" class="flex items-center"><img src="/images/New_Logo_ondgtl.png?v=5" alt="Ondigital Solution" class="h-14 w-auto object-contain" /></a>
                <div class="hidden items-center gap-7 text-sm font-semibold text-slate-600 md:flex">
                    <a href="#como-funciona" class="hover:text-indigo-700">Como funciona</a>
                    <a href="#planes" class="hover:text-indigo-700">Planes</a>
                    <a :href="whatsapp" target="_blank" rel="noopener" class="hover:text-indigo-700">Hablar con nosotros</a>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('login')" class="hidden px-3 py-2 text-sm font-bold text-slate-700 sm:inline-flex">Ingresar</Link>
                    <Link :href="route('register')" class="ui-primary-button">Crear tienda</Link>
                </div>
            </nav>
        </header>

        <main>
            <section id="inicio" class="relative overflow-hidden bg-slate-950">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(79,70,229,0.5),transparent_34%),radial-gradient(circle_at_85%_80%,rgba(6,182,212,0.25),transparent_35%)]" aria-hidden="true"></div>
                <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-4 py-20 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-28">
                    <div>
                        <span class="inline-flex rounded-full border border-cyan-300/30 bg-cyan-300/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em] text-cyan-200">Hecho para comerciantes</span>
                        <h1 class="mt-6 text-4xl font-black leading-[1.05] tracking-tight text-white sm:text-6xl">Vender y organizar tu negocio puede ser sencillo.</h1>
                        <p class="mt-6 max-w-xl text-lg leading-8 text-slate-300">Crea tu catalogo, controla existencias y atiende ventas sin hojas de calculo ni sistemas complicados.</p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link :href="route('register')" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-cyan-300 px-6 py-3 text-base font-black text-slate-950 hover:bg-cyan-200">Crear mi tienda gratis</Link>
                            <a :href="whatsapp" target="_blank" rel="noopener" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/20 bg-white/10 px-6 py-3 text-base font-bold text-white hover:bg-white/15">Quiero una demostracion</a>
                        </div>
                        <p class="mt-4 text-sm text-slate-400">Sin tarjeta. Sin comision por venta. Configuracion guiada.</p>
                    </div>

                    <div class="relative">
                        <div class="absolute -inset-5 rotate-2 rounded-[2rem] bg-indigo-500/20 blur-xl"></div>
                        <div class="relative overflow-hidden rounded-3xl border border-white/15 bg-white p-3 shadow-2xl">
                            <div class="rounded-2xl bg-slate-100 p-4 sm:p-6">
                                <div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Hoy en tu tienda</p><p class="mt-1 text-xl font-black">Todo bajo control</p></div><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Tienda activa</span></div>
                                <div class="mt-5 grid grid-cols-3 gap-2 sm:gap-3">
                                    <div class="rounded-xl bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">Ventas</p><p class="mt-1 text-lg font-black sm:text-2xl">$860k</p></div>
                                    <div class="rounded-xl bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">Pedidos</p><p class="mt-1 text-lg font-black sm:text-2xl">18</p></div>
                                    <div class="rounded-xl bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">Por reponer</p><p class="mt-1 text-lg font-black text-amber-600 sm:text-2xl">4</p></div>
                                </div>
                                <div class="mt-3 rounded-xl bg-white p-4 shadow-sm">
                                    <div class="flex items-center justify-between text-sm"><strong>Pedidos recientes</strong><span class="text-indigo-700">Ver todos</span></div>
                                    <div class="mt-3 space-y-2"><div v-for="(name, index) in ['Maria G.', 'Carlos R.', 'Tienda Centro']" :key="name" class="flex items-center justify-between rounded-lg bg-slate-50 p-2.5 text-sm"><span>{{ name }}</span><strong>{{ ['$84.000', '$126.500', '$59.900'][index] }}</strong></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="como-funciona" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Del primer producto a la primera venta</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Un proceso claro, paso a paso</h2><p class="mt-4 text-lg text-slate-600">Cada pantalla indica que necesitas hacer y que puedes dejar para despues.</p></div>
                <div class="mt-10 grid gap-5 md:grid-cols-3"><article v-for="feature in features" :key="feature.number" class="ui-card p-6"><span class="text-sm font-black text-indigo-600">{{ feature.number }}</span><h3 class="mt-8 text-xl font-bold">{{ feature.title }}</h3><p class="mt-3 leading-7 text-slate-600">{{ feature.text }}</p></article></div>
            </section>

            <section class="bg-slate-100 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid gap-8 lg:grid-cols-2 lg:items-center"><div><p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Pensado para el trabajo real</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Menos terminos tecnicos. Mas acciones claras.</h2></div><p class="text-lg leading-8 text-slate-600">Desde buscar un producto hasta cobrar una venta, la plataforma utiliza palabras comerciales, ayudas breves y confirmaciones visibles.</p></div>
                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div v-for="item in ['Catalogo online', 'Punto de venta', 'Control de inventario', 'Reportes comprensibles']" :key="item" class="rounded-2xl bg-white p-5 font-bold shadow-sm"><span class="mb-4 block h-2 w-10 rounded-full bg-cyan-400"></span>{{ item }}</div></div>
                </div>
            </section>

            <section id="planes" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div class="text-center"><p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Planes transparentes</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Empieza gratis y crece cuando lo necesites</h2></div>
                <div class="mx-auto mt-10 grid max-w-5xl gap-5 lg:grid-cols-3">
                    <article class="ui-card p-6"><p class="font-bold text-indigo-700">Emprendedor</p><p class="mt-3 text-3xl font-black">Gratis</p><p class="mt-3 text-sm leading-6 text-slate-600">Catalogo, productos, categorias, opciones y pedidos por WhatsApp.</p><Link :href="route('register')" class="ui-primary-button mt-6 w-full">Empezar ahora</Link></article>
                    <article class="rounded-2xl border-2 border-indigo-500 bg-slate-950 p-6 text-white shadow-xl"><span class="rounded-full bg-cyan-300 px-2.5 py-1 text-xs font-black text-slate-950">RECOMENDADO</span><p class="mt-4 font-bold text-cyan-300">Negociante</p><p class="mt-3 text-3xl font-black">A tu medida</p><p class="mt-3 text-sm leading-6 text-slate-300">Inventario avanzado, ordenes, reportes, clientes y equipo.</p><a :href="whatsapp" target="_blank" rel="noopener" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-black text-slate-950">Hablar por WhatsApp</a></article>
                    <article class="ui-card p-6"><p class="font-bold text-indigo-700">Creador PDF</p><p class="mt-3 text-3xl font-black">Especializado</p><p class="mt-3 text-sm leading-6 text-slate-600">Crea catalogos PDF personalizados con productos y fotos propias.</p><a :href="whatsapp" target="_blank" rel="noopener" class="ui-secondary-button mt-6 w-full">Solicitar informacion</a></article>
                </div>
            </section>

            <section class="bg-indigo-600"><div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-4 py-12 text-center text-white sm:px-6 lg:flex-row lg:px-8 lg:text-left"><div><h2 class="text-2xl font-black">Tu tienda puede estar lista hoy.</h2><p class="mt-2 text-indigo-100">Crea la cuenta y te guiaremos en los siguientes pasos.</p></div><Link :href="route('register')" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-6 py-3 font-black text-indigo-700">Crear mi tienda gratis</Link></div></section>
        </main>

        <footer class="border-t border-slate-200 bg-white"><div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:px-6 lg:px-8"><img src="/images/New_Logo_ondgtl.png?v=5" alt="Ondigital Solution" class="h-10 w-auto" /><p>{{ new Date().getFullYear() }} Ondigital Solution. Comercio sin complicaciones.</p></div></footer>
    </div>
    <CookieConsent />
</template>
