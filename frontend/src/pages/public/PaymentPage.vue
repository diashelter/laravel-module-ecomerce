<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import LoadingState from '@/components/LoadingState.vue'
import OrderItemsTable from '@/components/OrderItemsTable.vue'
import OrderStatusBadge from '@/components/OrderStatusBadge.vue'
import { usePolling } from '@/composables/usePolling'
import { ApiError } from '@/services/api'
import { orderService } from '@/services/orderService'
import { useNotificationStore } from '@/stores/notifications'
import type { Order } from '@/types'
import { formatCents } from '@/utils/money'

const props = defineProps<{ orderId: number }>()

const router = useRouter()
const notifications = useNotificationStore()

const order = ref<Order | null>(null)
const error = ref<string | null>(null)
const paying = ref(false)

const canPay = computed(() => order.value?.status === 'awaiting_payment')

async function load(): Promise<void> {
  order.value = await orderService.find(props.orderId)
}

// Right after checkout the order is "placed"; the queue worker moves it to
// "awaiting_payment" a moment later, so we poll until that happens.
const polling = usePolling(async () => {
  await load()
  return order.value?.status === 'placed'
})

async function approve(): Promise<void> {
  paying.value = true
  try {
    const { message } = await orderService.approvePayment(props.orderId)
    notifications.success(message)
    // The status changes in the queue: the order page keeps polling until it moves on.
    await router.push({ name: 'account.order', params: { id: props.orderId }, query: { paid: '1' } })
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      notifications.error(e.message)
      await load()
    }
  } finally {
    paying.value = false
  }
}

onMounted(async () => {
  try {
    await load()
    if (order.value?.status === 'placed') polling.start()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Não foi possível carregar o pedido.'
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <h1 class="page-title">Pagamento</h1>

    <p v-if="error" class="card p-6 text-center text-red-600">{{ error }}</p>
    <LoadingState v-else-if="!order" />

    <template v-else>
      <div class="card space-y-4 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm text-slate-500">Pedido</p>
            <p class="text-2xl font-bold">#{{ order.id }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm text-slate-500">Valor</p>
            <p class="text-2xl font-bold text-indigo-700">{{ formatCents(order.total_cents) }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 text-sm">
          Status: <OrderStatusBadge :status="order.status" :label="order.status_label" />
        </div>
      </div>

      <div class="card">
        <OrderItemsTable :items="order.items ?? []" :total-cents="order.total_cents" />
      </div>

      <div class="card space-y-4 p-6 text-center">
        <p class="text-sm text-slate-500">
          Pagamento simulado para fins de estudo: nenhum gateway real é utilizado.
        </p>
        <p v-if="order.status === 'placed'" class="text-sm text-amber-700">
          Processando o pedido na fila... o botão será liberado em instantes.
        </p>
        <p v-else-if="!canPay" class="text-sm text-slate-600">O pagamento deste pedido já foi processado.</p>
        <div class="flex flex-col justify-center gap-3 sm:flex-row">
          <button type="button" class="btn btn-primary" :disabled="!canPay || paying" @click="approve">
            {{ paying ? 'Aprovando...' : 'Aprovar pagamento' }}
          </button>
          <RouterLink :to="{ name: 'account.order', params: { id: order.id } }" class="btn btn-secondary">Ver pedido</RouterLink>
        </div>
      </div>
    </template>
  </div>
</template>
