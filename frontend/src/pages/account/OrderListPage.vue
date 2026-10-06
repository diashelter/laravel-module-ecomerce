<script setup lang="ts">
import { onMounted, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import OrderStatusBadge from '@/components/OrderStatusBadge.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import { orderService } from '@/services/orderService'
import type { Order, Paginated } from '@/types'
import { formatDateTime } from '@/utils/date'
import { formatCents } from '@/utils/money'

const result = ref<Paginated<Order> | null>(null)

async function load(page = 1): Promise<void> {
  result.value = await orderService.list(page)
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <h1 class="page-title">Meus pedidos</h1>

    <LoadingState v-if="!result" />
    <EmptyState v-else-if="result.data.length === 0" title="Você ainda não fez pedidos">
      <RouterLink :to="{ name: 'products' }" class="btn btn-primary">Ver produtos</RouterLink>
    </EmptyState>
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Pedido</th><th>Data</th><th class="text-right">Itens</th><th class="text-right">Total</th><th>Status</th></tr>
        </thead>
        <tbody>
          <tr v-for="order in result.data" :key="order.id">
            <td><RouterLink :to="{ name: 'account.order', params: { id: order.id } }" class="font-medium text-indigo-600 hover:underline">#{{ order.id }}</RouterLink></td>
            <td>{{ formatDateTime(order.created_at) }}</td>
            <td class="text-right">{{ order.items_count }}</td>
            <td class="text-right">{{ formatCents(order.total_cents) }}</td>
            <td><OrderStatusBadge :status="order.status" :label="order.status_label" /></td>
          </tr>
        </tbody>
      </table>
      <div class="px-2 pb-2"><PaginationNav :meta="result.meta" @change="load" /></div>
    </div>
  </div>
</template>
