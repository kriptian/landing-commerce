<script setup>
import { ref, onMounted } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js';

// Registramos las partes de Chart.js que vamos a usar
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
    chartData: {
        type: Object,
        required: true
    }
});

// Opciones para que el gráfico se vea bien
const chartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false, // No mostramos la leyenda del dataset
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                // Formateamos los números del eje Y como moneda
                callback: function(value) {
                    return new Intl.NumberFormat('es-CO', { 
                        style: 'currency', 
                        currency: 'COP', 
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    }).format(value);
                }
            }
        }
    }
});
</script>

<template>
    <div style="height: 400px">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>