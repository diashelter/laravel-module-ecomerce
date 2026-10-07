import { computed, ref, type Ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '@/services/api'
import { orderService } from '@/services/orderService'
import { useNotificationStore } from '@/stores/notifications'
import type { Order } from '@/types'

export interface TestCard {
  label: string
  token: string
}

/**
 * Test cards of the fake payment gateway. This list plays the role of a real gateway SDK:
 * the backend only receives the token, and the gateway decides the outcome from it.
 */
export const TEST_CARDS: readonly TestCard[] = [
  { label: 'Cartão aprovado', token: 'fake_card_approved' },
  { label: 'Recusado: saldo insuficiente', token: 'fake_card_insufficient_funds' },
  { label: 'Recusado pelo emissor', token: 'fake_card_declined' },
]

/**
 * Paying an order with a test card. A decline (402) keeps the customer on the page to try
 * another card; an order that can no longer be paid (409) is reloaded.
 */
export function usePayment(orderId: number, order: Ref<Order | null>) {
  const router = useRouter()
  const notifications = useNotificationStore()

  const selectedToken = ref<string | null>(null)
  const paying = ref(false)
  const declineMessage = ref<string | null>(null)

  const canSubmit = computed(
    () => order.value?.status === 'awaiting_payment' && selectedToken.value !== null && !paying.value,
  )
  const submitLabel = computed(() => (paying.value ? 'Pagando...' : 'Pagar'))

  async function pay(): Promise<void> {
    if (!canSubmit.value || selectedToken.value === null) return
    paying.value = true
    declineMessage.value = null
    try {
      const { message } = await orderService.pay(orderId, selectedToken.value)
      notifications.success(message)
      // The status changes in the queue: the order page keeps polling until it moves on.
      await router.push({ name: 'account.order', params: { id: orderId }, query: { paid: '1' } })
    } catch (e) {
      if (!(e instanceof ApiError)) throw e
      if (e.status === 402) {
        declineMessage.value = e.message
      } else if (e.status === 409) {
        notifications.error(e.message)
        order.value = await orderService.find(orderId)
      }
    } finally {
      paying.value = false
    }
  }

  return { cards: TEST_CARDS, selectedToken, paying, declineMessage, canSubmit, submitLabel, pay }
}
