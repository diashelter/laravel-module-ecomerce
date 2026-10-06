<script setup lang="ts">
import { BarElement, CategoryScale, Chart, Legend, LinearScale, Tooltip } from 'chart.js'
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import type { ChartPoint } from '@/types'

Chart.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const props = defineProps<{ points: ChartPoint[]; label: string; color?: string }>()

const data = computed(() => ({
  labels: props.points.map((point) => point.label),
  datasets: [{ label: props.label, data: props.points.map((point) => point.total), backgroundColor: props.color ?? '#4f46e5', borderRadius: 4 }],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
}
</script>

<template>
  <div class="h-64"><Bar :data="data" :options="options" /></div>
</template>
