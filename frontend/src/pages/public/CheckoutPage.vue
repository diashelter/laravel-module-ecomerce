<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { ApiError } from '@/services/api'
import { cartService } from '@/services/cartService'
import { orderService } from '@/services/orderService'
import { useCartStore } from '@/stores/cart'
import { useNotificationStore } from '@/stores/notifications'
import type { CartValidation } from '@/types'
import { formatMoney } from '@/utils/money'

const cart = useCartStore()
const notifications = useNotificationStore()
const router = useRouter()

const validation = ref<CartValidation | null>(null)
const loading = ref(false)
const submitting = ref(false)
const conflictMessage = ref<string | null>(null)

/** Before confirming, the backend re-checks products, status, stock, quantities and prices. */
async function validate(): Promise<void> {
  if (cart.isEmpty) return
  loading.value = true
  try {
    validation.value = await cartService.validate(cart.payload)
    cart.syncFromValidation(validation.value)
  } finally {
    loading.value = false
  }
}

async function confirm(): Promise<void> {
  submitting.value = true
  conflictMessage.value = null
  try {
    const order = await orderService.place(cart.payload)
    cart.clear()
    notifications.success(`Pedido #${order.id} criado com sucesso!`)
    await router.push({ name: 'payment', params: { orderId: order.id } })
  } catch (error) {
    // 409: stock changed between the validation and the confirmation.
    if (error instanceof ApiError && error.status === 409) {
      conflictMessage.value = error.message
      await validate()
    } else if (error instanceof ApiError && error.status === 422) {
      notifications.error(error.message)
    }
  } finally {
    submitting.value = false
  }
}

onMounted(validate)
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <h1 class="page-title">Checkout</h1>

    <EmptyState v-if="cart.isEmpty" title="Seu carrinho está vazio">
      <RouterLink :to="{ name: 'products' }" class="btn btn-primary">Ver produtos</RouterLink>
    </EmptyState>

    <LoadingState v-else-if="loading && !validation" text="Validando carrinho..." />

    <template v-else-if="validation">
      <div v-if="conflictMessage" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        {{ conflictMessage }} Revise os itens abaixo.
      </div>

      <div class="card overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Produto</th>
              <th class="text-right">Qtd.</th>
              <th class="text-right">Preço unitário</th>
              <th class="text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in validation.items" :key="line.product_id" :class="line.problem && 'bg-red-50'">
              <td>
                <p class="font-medium">{{ line.name ?? `Produto #${line.product_id}` }}</p>
                <p v-if="line.problem" class="text-xs font-semibold text-red-600">{{ line.problem }}</p>
              </td>
              <td class="text-right">{{ line.quantity }}</td>
              <td class="text-right">{{ formatMoney(line.unit_price) }}</td>
              <td class="text-right">{{ formatMoney(line.subtotal) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-slate-200">
              <td colspan="3" class="px-4 py-3 text-right font-semibold">Total</td>
              <td class="px-4 py-3 text-right text-lg font-bold">{{ formatMoney(validation.total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div v-if="!validation.is_valid" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        Alguns itens estão indisponíveis ou acima do estoque. Ajuste o carrinho para continuar.
      </div>

      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <RouterLink :to="{ name: 'cart' }" class="btn btn-secondary">Voltar ao carrinho</RouterLink>
        <button type="button" class="btn btn-primary" :disabled="!validation.is_valid || submitting || loading" @click="confirm">
          {{ submitting ? 'Confirmando...' : 'Confirmar compra' }}
        </button>
      </div>
    </template>
  </div>
</template>
