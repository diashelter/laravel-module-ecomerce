<script setup lang="ts">
import { onMounted, ref } from 'vue'
import BarChart from '@/components/admin/BarChart.vue'
import DoughnutChart from '@/components/admin/DoughnutChart.vue'
import StatCard from '@/components/admin/StatCard.vue'
import LoadingState from '@/components/LoadingState.vue'
import { adminDashboardService } from '@/services/admin/dashboardService'
import type { DashboardData } from '@/types'

// All numbers come aggregated from the backend; this page only renders them.
const dashboard = ref<DashboardData | null>(null)

onMounted(async () => {
  dashboard.value = await adminDashboardService.summary()
})
</script>

<template>
  <LoadingState v-if="!dashboard" />
  <div v-else class="space-y-6">
    <h1 class="page-title">Dashboard</h1>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <StatCard label="Total de produtos" :value="dashboard.cards.total_products" />
      <StatCard label="Produtos ativos" :value="dashboard.cards.active_products" />
      <StatCard label="Produtos inativos" :value="dashboard.cards.inactive_products" />
      <StatCard label="Unidades em estoque" :value="dashboard.cards.total_stock_units" />
      <StatCard label="Total de clientes" :value="dashboard.cards.total_customers" />
      <StatCard label="Total de pedidos" :value="dashboard.cards.total_orders" />
    </div>

    <section class="card p-5">
      <h2 class="mb-4 font-semibold">Pedidos por dia (últimos 30 dias)</h2>
      <BarChart :points="dashboard.orders_per_day" label="Pedidos" />
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="card p-5 lg:col-span-2">
        <h2 class="mb-4 font-semibold">Pedidos por mês (últimos 12 meses)</h2>
        <BarChart :points="dashboard.orders_per_month" label="Pedidos" color="#0ea5e9" />
      </section>
      <section class="card p-5">
        <h2 class="mb-4 font-semibold">Estoque</h2>
        <DoughnutChart
          :labels="['Em estoque', 'Sem estoque']"
          :values="[dashboard.stock.products_in_stock, dashboard.stock.products_out_of_stock]"
          :colors="['#22c55e', '#ef4444']"
        />
      </section>
    </div>

    <section class="card flex items-center justify-between p-5">
      <div>
        <h2 class="font-semibold">Clientes</h2>
        <p class="text-sm text-slate-500">Quantidade total de clientes cadastrados</p>
      </div>
      <p class="text-4xl font-bold text-indigo-700">{{ dashboard.cards.total_customers }}</p>
    </section>
  </div>
</template>
