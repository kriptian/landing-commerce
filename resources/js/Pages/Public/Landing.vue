<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col bg-white">
        <Head title="Tu tienda online lista para vender" />

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-900 via-blue-800 to-cyan-700"></div>
            <div class="relative z-10 max-w-7xl mx-auto px-6 py-16 lg:py-24 text-white">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight">
                            Vende más con menos esfuerzo
                        </h1>
                        <p class="mt-4 text-cyan-100 text-lg">
                            Plataforma de ecommerce para negocios que quieren crecer: rápida, segura y fácil de usar.
                        </p>
                        <ul class="mt-6 space-y-3 text-cyan-50">
                            <li class="flex gap-3"><span class="mt-1 h-2 w-2 rounded-full bg-white"></span>Catálogo con dominio propio y SEO listo</li>
                            <li class="flex gap-3"><span class="mt-1 h-2 w-2 rounded-full bg-white"></span>Inventario con alertas de stock y variantes</li>
                            <li class="flex gap-3"><span class="mt-1 h-2 w-2 rounded-full bg-white"></span>Promociones, reportes y roles para tu equipo</li>
                        </ul>
                        <div class="mt-8 flex flex-col sm:flex-row gap-3">
                            <a
                                href="https://wa.me/573024061771?text=Hola%20quiero%20crear%20mi%20tienda%20online%20con%20Ondigitalsolution"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-white text-blue-800 font-semibold rounded-lg shadow hover:bg-blue-50 border border-white/60"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-green-500"><path d="M20.52 3.48A11.94 11.94 0 0012.01 0C5.4 0 .03 5.37.03 12c0 2.11.55 4.09 1.6 5.86L0 24l6.3-1.63a11.9 11.9 0 005.7 1.45h.01c6.61 0 11.98-5.37 11.98-12 0-3.2-1.25-6.2-3.47-8.34zM12 21.5c-1.8 0-3.56-.48-5.1-1.38l-.37-.22-3.74 .97 .99-3.65-.24-.38A9.5 9.5 0 1121.5 12c0 5.24-4.26 9.5-9.5 9.5zm5.28-6.92c-.29-.15-1.7-.84-1.96-.94-.26-.1-.45-.15-.64 .15-.19 .29-.74 .94-.9 1.13-.17 .19-.33 .22-.62 .07-.29-.15-1.24-.46-2.35-1.47-.86-.76-1.44-1.7-1.61-1.99-.17-.29-.02-.45 .13-.6 .13-.13 .29-.33 .43-.5 .15-.17 .19-.29 .29-.48 .1-.19 .05-.36-.03-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49l-.55-.01c-.19 0 -.5 .07-.76 .36-.26 .29-1 1-1 2.45s1.02 2.84 1.16 3.03c.15 .19 2 3.06 4.84 4.29 .68 .29 1.21 .46 1.62 .59 .68 .22 1.3 .19 1.79 .12 .55-.08 1.7-.7 1.94-1.38 .24-.68 .24-1.26 .17-1.38-.07-.12-.26-.19-.55-.34z"/></svg>
                                Habla con un asesor
                            </a>
                            <Link :href="route('login')" class="inline-flex items-center justify-center px-5 py-3 bg-white text-blue-800 font-semibold rounded-lg shadow hover:bg-blue-50 border border-white/60">
                                Ya tengo cuenta
                            </Link>
                        </div>

                        <!-- Logo en móvil con tarjeta suave para no saturar -->
                        <div class="sm:hidden mt-8 flex justify-center">
                            <div class="rounded-xl border border-gray-200 bg-white shadow-lg p-3 w-56">
                                <img src="/images/digitalsolution-logo.png?v=4" alt="Ondigitalsolution" class="w-full h-auto object-contain opacity-95" />
                            </div>
                        </div>
                    </div>
                    <div class="relative hidden sm:block">
                        <!-- Tarjeta blanca translúcida detrás del logo para legibilidad -->
                        <div class="absolute -inset-8 bg-white/90 backdrop-blur-xl rounded-2xl shadow-xl border border-white/80"></div>
                        <img src="/images/digitalsolution-logo.png?v=4" alt="Ondigitalsolution" class="relative w-full max-w-md mx-auto" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección informativa estática y legible -->
        <section class="max-w-7xl mx-auto px-6 py-14">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">¿Quiénes somos?</h2>
                    <p class="mt-3 text-gray-600">
                        Somos un equipo de expertos en ecommerce y tecnología que impulsa a negocios a vender online sin complicaciones. Construimos herramientas simples y efectivas para que te enfoques en crecer.
                    </p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Lo que nos mueve</h3>
                    <ul class="mt-3 space-y-2 text-gray-700">
                        <li>• Resultados medibles y crecimiento sostenido</li>
                        <li>• Experiencia de compra rápida y sin fricción</li>
                        <li>• Soporte cercano y mejoras continuas</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Secciones informativas inferiores -->
        <section class="bg-gray-50 border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-6 py-14">
                <!-- ¿Qué hacemos? -->
                <div class="mt-10">
                    <h2 class="text-2xl font-bold text-gray-900">¿Qué hacemos?</h2>
                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="p-5 bg-white rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Catálogo y dominio</h3>
                            <p class="mt-2 text-gray-600">Muestra tus productos con fotos y variantes, con tu marca y dominio propio.</p>
                        </div>
                        <div class="p-5 bg-white rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Inventario siempre al día</h3>
                            <p class="mt-2 text-gray-600">Evita quiebres: control en tiempo real y alertas antes de agotarte.</p>
                        </div>
                        <div class="p-5 bg-white rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Promos y métricas que venden</h3>
                            <p class="mt-2 text-gray-600">Genera descuentos individuales y globales en segundos para impulsar tus ventas.</p>
                        </div>
                    </div>
                </div>

                <!-- ¿Por qué elegirnos? -->
                <div class="mt-10">
                    <h2 class="text-2xl font-bold text-gray-900">¿Por qué elegirnos?</h2>
                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="p-5 bg-white rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Empieza a vender hoy</h3>
                            <p class="mt-2 text-gray-600">Te dejamos operando en horas, no semanas.</p>
                        </div>
                        <div class="p-5 bg-white rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Crece sin complicaciones</h3>
                            <p class="mt-2 text-gray-600">Rinde con picos de tráfico y catálogos grandes.</p>
                        </div>
                        <div class="p-5 bg-white rounded-xl border border-gray-100 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Soporte que sí responde</h3>
                            <p class="mt-2 text-gray-600">Estamos a un mensaje de distancia para resolver y mejorar.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        

        <!-- Footer breve -->
        <footer class="bg-gray-50 border-t border-gray-100">
            <div class="max-w-7xl mx-auto px-6 py-8 text-sm text-gray-600 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <img src="/images/digitalsolution-logo.png?v=4" class="h-6 w-6" alt="Ondigitalsolution" />
                    <span>{{ new Date().getFullYear() }} © Ondigitalsolution</span>
                </div>
                <div class="flex items-center gap-4"></div>
            </div>
        </footer>
    </div>
</template>


