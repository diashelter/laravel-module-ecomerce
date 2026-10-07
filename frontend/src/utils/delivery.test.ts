import { describe, expect, it } from 'vitest'
import type { Order } from '@/types'
import { describeDelivery } from './delivery'

function order(status: Order['status'], estimatedOn: string | null, businessDays = 2): Pick<Order, 'status' | 'delivery'> {
  return {
    status,
    delivery: {
      business_days: businessDays,
      estimated_on: estimatedOn,
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
  }
}

describe('delivery text', () => {
  it.each([
    ['the lead time while there is no forecast', order('awaiting_payment', null, 2), 'Entrega em até 2 dias úteis após a aprovação do pagamento'],
    ['the forecast day once there is one', order('payment_approved', '2026-10-09'), 'Entrega prevista: 09/10/2026'],
    ['delivered in place of the forecast', order('delivered', '2026-10-09'), 'Pedido entregue'],
  ])('describes the delivery forecast of an order: %s', (_label, current, expected) => {
    expect(describeDelivery(current)).toBe(expected)
  })
})
