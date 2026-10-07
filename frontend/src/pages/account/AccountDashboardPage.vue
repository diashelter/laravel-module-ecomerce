<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import OrderStatusBadge from '@/components/OrderStatusBadge.vue'
import { accountService } from '@/services/accountService'
import type { AccountSummary } from '@/types'
import { formatDate } from '@/utils/date'
import { formatCents } from '@/utils/money'

const summary = ref<AccountSummary | null>(null)

onMounted(async () => {
  summary.value = await accountService.summary()
})
</script>

<template>
  <LoadingState v-if="!summary" />
  <div v-else class="space-y-6">
    <h1 class="page-title">Olá, {{ summary.customer.name }}!</h1>

    <div class="grid gap-4 sm:grid-cols-3">
      <div class="card p-5">
        <p class="text-sm text-slate-500">Nome</p>
        <p class="mt-1 font-semibold">{{ summary.customer.name }}</p>
        <p class="text-sm text-slate-500">{{ summary.customer.email }}</p>
      </div>
      <div class="card p-5">
        <p class="text-sm text-slate-500">Pedidos realizados</p>
        <p class="mt-1 text-3xl font-bold">{{ summary.orders_count }}</p>
      </div>
      <div class="card p-5">
        <p class="text-sm text-slate-500">Último pedido</p>
        <template v-if="summary.last_order">
          <RouterLink :to="{ name: 'account.order', params: { id: summary.last_order.id } }" class="mt-1 block font-semibold text-indigo-600 hover:underline">
            #{{ summary.last_order.id }} · {{ formatCents(summary.last_order.total_cents) }}
          </RouterLink>
          <OrderStatusBadge :status="summary.last_order.status" :label="summary.last_order.status_label" />
        </template>
        <p v-else class="mt-1 text-sm text-slate-500">Nenhum pedido ainda.</p>
      </div>
    </div>

    <section class="card">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <h2 class="font-semibold">Pedidos recentes</h2>
        <RouterLink :to="{ name: 'account.orders' }" class="text-sm text-indigo-600 hover:underline">Ver todos</RouterLink>
      </div>
      <p v-if="summary.recent_orders.length === 0" class="p-5 text-sm text-slate-500">
        Você ainda não fez pedidos. <RouterLink :to="{ name: 'products' }" class="text-indigo-600 hover:underline">Ver produtos</RouterLink>
      </p>
      <div v-else class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr><th>Pedido</th><th>Data</th><th class="text-right">Total</th><th>Status</th></tr>
          </thead>
          <tbody>
            <tr v-for="order in summary.recent_orders" :key="order.id">
              <td><RouterLink :to="{ name: 'account.order', params: { id: order.id } }" class="font-medium text-indigo-600 hover:underline">#{{ order.id }}</RouterLink></td>
              <td>{{ formatDate(order.created_at) }}</td>
              <td class="text-right">{{ formatCents(order.total_cents) }}</td>
              <td><OrderStatusBadge :status="order.status" :label="order.status_label" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
