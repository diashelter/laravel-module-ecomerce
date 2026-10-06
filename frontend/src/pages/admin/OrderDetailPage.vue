<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import OrderItemsTable from '@/components/OrderItemsTable.vue'
import OrderStatusBadge from '@/components/OrderStatusBadge.vue'
import OrderTimeline from '@/components/OrderTimeline.vue'
import { adminOrderService } from '@/services/admin/orderService'
import type { Order } from '@/types'
import { formatDateTime } from '@/utils/date'

const props = defineProps<{ id: number }>()
const order = ref<Order | null>(null)

onMounted(async () => {
  order.value = await adminOrderService.find(props.id)
})
</script>

<template>
  <LoadingState v-if="!order" />
  <div v-else class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <RouterLink :to="{ name: 'admin.orders' }" class="text-sm text-indigo-600 hover:underline">← Pedidos</RouterLink>
        <h1 class="page-title mt-1">Pedido #{{ order.id }}</h1>
        <p class="text-sm text-slate-500">{{ formatDateTime(order.created_at) }}</p>
      </div>
      <OrderStatusBadge :status="order.status" :label="order.status_label" />
    </div>

    <section v-if="order.customer" class="card p-5">
      <h2 class="mb-2 font-semibold">Cliente</h2>
      <RouterLink :to="{ name: 'admin.users.show', params: { id: order.customer.id } }" class="font-medium text-indigo-600 hover:underline">
        {{ order.customer.name }}
      </RouterLink>
      <p class="text-sm text-slate-500">{{ order.customer.email }}</p>
    </section>

    <section class="card p-5">
      <h2 class="mb-5 font-semibold">Status</h2>
      <OrderTimeline :steps="order.timeline" />
      <p class="mt-5 text-xs text-slate-500">
        O status é alterado automaticamente pelos eventos e jobs da fila (OrderPlaced, PaymentApproved, OrderPaid, OrderDelivered).
      </p>
    </section>

    <section class="card">
      <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Produtos</h2>
      <OrderItemsTable :items="order.items ?? []" :total="order.total" />
    </section>
  </div>
</template>
