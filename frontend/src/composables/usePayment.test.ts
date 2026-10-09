import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { ApiError } from '@/services/api'
import { orderService } from '@/services/orderService'
import { useNotificationStore } from '@/stores/notifications'
import type { Order, OrderStatus, PaymentAttempt } from '@/types'
import { usePayment } from './usePayment'

const push = vi.fn()

vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

vi.mock('@/services/orderService', () => ({
  orderService: { pay: vi.fn(), find: vi.fn() },
}))

function attempt(): PaymentAttempt {
  return { id: 3, order_id: 7, status: 'approved', amount_cents: 12345 }
}

function order(status: OrderStatus): Order {
  return {
    id: 7,
    total_cents: 12345,
    items_total_cents: 12345,
    shipping_cents: 0,
    delivery: {
      business_days: 2,
      estimated_on: null,
      address: {
        recipient_name: 'Ana Souza',
        postal_code: '01310100',
        street: 'Avenida Paulista',
        number: '1000',
        complement: null,
        district: 'Bela Vista',
        city: 'São Paulo',
        state: 'SP',
      },
    },
    status,
    status_label: '',
    status_step: 2,
    timeline: [],
    created_at: '',
    updated_at: '',
  }
}

function setup(status: OrderStatus = 'awaiting_payment') {
  const current = ref<Order | null>(order(status))
  return { current, payment: usePayment(7, current) }
}

describe('usePayment', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('lists the three test cards with none selected', () => {
    const { payment } = setup()

    expect(payment.cards.map((card) => [card.label, card.token])).toEqual([
      ['Cartão aprovado', 'fake_card_approved'],
      ['Recusado: saldo insuficiente', 'fake_card_insufficient_funds'],
      ['Recusado pelo emissor', 'fake_card_declined'],
    ])
    expect(payment.selectedToken.value).toBeNull()
    expect(payment.canSubmit.value).toBe(false)
  })

  it('pays with the selected card and locks the button while paying', async () => {
    let resolve!: (value: { payment: PaymentAttempt; message: string }) => void
    vi.mocked(orderService.pay).mockReturnValue(new Promise((r) => (resolve = r)))
    const { payment } = setup()

    payment.selectedToken.value = 'fake_card_declined'
    expect(payment.canSubmit.value).toBe(true)

    const paying = payment.pay()

    expect(orderService.pay).toHaveBeenCalledWith(7, 'fake_card_declined')
    expect(payment.canSubmit.value).toBe(false)
    expect(payment.submitLabel.value).toBe('Pagando...')

    resolve({ payment: attempt(), message: 'ok' })
    await paying
  })

  it('navigates to the order after an approved payment', async () => {
    vi.mocked(orderService.pay).mockResolvedValue({
      payment: attempt(),
      message: 'Pagamento aprovado. O pedido será atualizado em instantes.',
    })
    const notifications = useNotificationStore()
    const success = vi.spyOn(notifications, 'success')
    const { payment } = setup()

    payment.selectedToken.value = 'fake_card_approved'
    await payment.pay()

    expect(success).toHaveBeenCalledWith('Pagamento aprovado. O pedido será atualizado em instantes.')
    expect(push).toHaveBeenCalledWith({ name: 'account.order', params: { id: 7 }, query: { paid: '1' } })
  })

  it('keeps the page and shows the decline after a 402', async () => {
    vi.mocked(orderService.pay).mockRejectedValue(
      new ApiError(402, 'Pagamento recusado: saldo insuficiente.', {}, 'PAYMENT_DECLINED'),
    )
    const { payment } = setup()

    payment.selectedToken.value = 'fake_card_insufficient_funds'
    await payment.pay()

    expect(payment.declineMessage.value).toBe('Pagamento recusado: saldo insuficiente.')
    expect(payment.selectedToken.value).toBe('fake_card_insufficient_funds')
    expect(payment.canSubmit.value).toBe(true)
    expect(push).not.toHaveBeenCalled()
  })

  it('reloads the order after a 409 and blocks payment while placed', async () => {
    vi.mocked(orderService.pay).mockRejectedValue(new ApiError(409, 'Este pedido não está aguardando pagamento.'))
    vi.mocked(orderService.find).mockResolvedValue(order('payment_approved'))
    const notifications = useNotificationStore()
    const error = vi.spyOn(notifications, 'error')
    const { current, payment } = setup()

    payment.selectedToken.value = 'fake_card_approved'
    await payment.pay()

    expect(error).toHaveBeenCalledWith('Este pedido não está aguardando pagamento.')
    expect(orderService.find).toHaveBeenCalledTimes(1)
    expect(orderService.find).toHaveBeenCalledWith(7)
    expect(current.value?.status).toBe('payment_approved')

    const placed = setup('placed').payment
    placed.selectedToken.value = 'fake_card_approved'
    expect(placed.canSubmit.value).toBe(false)
  })
})
