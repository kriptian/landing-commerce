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

// Opciones del gráfico (apilado por estado, sin eje Y visible)
const chartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top',
        },
        tooltip: {
            mode: 'index',
            intersect: false,
        },
    },
    scales: {
        x: {
            stacked: false,
        },
        y: {
            stacked: false,
            beginAtZero: true,
            display: false,
        },
    }
});
</script>

<template>
    <div style="height: 400px">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>