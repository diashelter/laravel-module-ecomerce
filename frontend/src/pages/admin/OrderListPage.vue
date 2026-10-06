<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import OrderStatusBadge from '@/components/OrderStatusBadge.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import { adminOrderService } from '@/services/admin/orderService'
import type { Order, Paginated } from '@/types'
import { formatDateTime } from '@/utils/date'
import { formatMoney } from '@/utils/money'

const result = ref<Paginated<Order> | null>(null)

async function load(page = 1): Promise<void> {
  result.value = await adminOrderService.list(page)
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <h1 class="page-title">Pedidos</h1>

    <LoadingState v-if="!result" />
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Pedido</th><th>Cliente</th><th class="text-right">Valor</th><th>Status</th><th>Data</th></tr>
        </thead>
        <tbody>
          <tr v-for="order in result.data" :key="order.id">
            <td><RouterLink :to="{ name: 'admin.orders.show', params: { id: order.id } }" class="font-medium text-indigo-600 hover:underline">#{{ order.id }}</RouterLink></td>
            <td>{{ order.customer?.name }}</td>
            <td class="text-right whitespace-nowrap">{{ formatMoney(order.total) }}</td>
            <td><OrderStatusBadge :status="order.status" :label="order.status_label" /></td>
            <td>{{ formatDateTime(order.created_at) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="px-2 pb-2"><PaginationNav :meta="result.meta" @change="load" /></div>
    </div>
  </div>
</template>
