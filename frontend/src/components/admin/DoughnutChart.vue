<script setup lang="ts">
import { ArcElement, Chart, Legend, Tooltip } from 'chart.js'
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'

Chart.register(ArcElement, Tooltip, Legend)

const props = defineProps<{ labels: string[]; values: number[]; colors: string[] }>()

const data = computed(() => ({
  labels: props.labels,
  datasets: [{ data: props.values, backgroundColor: props.colors, borderWidth: 0 }],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' as const } },
}
</script>

<template>
  <div class="h-64"><Doughnut :data="data" :options="options" /></div>
</template>
