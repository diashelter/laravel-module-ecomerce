import type { Order } from '@/types'
import { formatDay } from './date'

/** The promise made before the payment is approved: the days count from the approval. */
export function leadTimeText(businessDays: number): string {
  return `Entrega em até ${businessDays} dias úteis após a aprovação do pagamento`
}

/** What an order says about its delivery: delivered, a forecast day, or the lead time while there is no forecast yet. */
export function describeDelivery(order: Pick<Order, 'status' | 'delivery'>): string {
  if (order.status === 'delivered') return 'Pedido entregue'
  if (order.delivery.estimated_on !== null) return `Entrega prevista: ${formatDay(order.delivery.estimated_on)}`
  return leadTimeText(order.delivery.business_days)
}
