<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import LoadingState from '@/components/LoadingState.vue'
import OrderDeliveryCard from '@/components/OrderDeliveryCard.vue'
import OrderItemsTable from '@/components/OrderItemsTable.vue'
import OrderStatusBadge from '@/components/OrderStatusBadge.vue'
import OrderTimeline from '@/components/OrderTimeline.vue'
import { usePolling } from '@/composables/usePolling'
import { ApiError } from '@/services/api'
import { orderService } from '@/services/orderService'
import type { Order, OrderStatus } from '@/types'
import { formatDateTime } from '@/utils/date'

const props = defineProps<{ id: number }>()

const route = useRoute()
const order = ref<Order | null>(null)
const error = ref<string | null>(null)

// Coming from the payment page: the PaymentApproved event may not have been processed yet.
const paymentJustApproved = route.query.paid === '1'

/** Statuses changed automatically by the queue worker: keep refreshing while in one of them. */
function isProcessing(status: OrderStatus): boolean {
  return status === 'placed' || status === 'payment_approved' || (status === 'awaiting_payment' && paymentJustApproved)
}

const polling = usePolling(async () => {
  order.value = await orderService.find(props.id)
  return isProcessing(order.value.status)
})

onMounted(async () => {
  try {
    order.value = await orderService.find(props.id)
    if (isProcessing(order.value.status)) polling.start()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Não foi possível carregar o pedido.'
  }
})
</script>

<template>
  <p v-if="error" class="card p-6 text-center text-red-600">{{ error }}</p>
  <LoadingState v-else-if="!order" />
  <div v-else class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <RouterLink :to="{ name: 'account.orders' }" class="text-sm text-indigo-600 hover:underline">← Meus pedidos</RouterLink>
        <h1 class="page-title mt-1">Pedido #{{ order.id }}</h1>
        <p class="text-sm text-slate-500">Realizado em {{ formatDateTime(order.created_at) }}</p>
      </div>
      <OrderStatusBadge :status="order.status" :label="order.status_label" />
    </div>

    <section class="card p-6">
      <h2 class="mb-5 font-semibold">Acompanhamento</h2>
      <OrderTimeline :steps="order.timeline" />
      <p v-if="isProcessing(order.status)" class="mt-5 text-sm text-slate-500">
        Processando na fila... a página atualiza automaticamente.
      </p>
      <div v-else-if="order.status === 'awaiting_payment'" class="mt-5">
        <RouterLink :to="{ name: 'payment', params: { orderId: order.id } }" class="btn btn-primary">Ir para pagamento</RouterLink>
      </div>
    </section>

    <OrderDeliveryCard :order="order" />

    <section class="card">
      <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Produtos</h2>
      <OrderItemsTable
        :items="order.items ?? []"
        :items-total-cents="order.items_total_cents"
        :shipping-cents="order.shipping_cents"
        :total-cents="order.total_cents"
      />
    </section>
  </div>
</template>
