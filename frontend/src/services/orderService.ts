import type { CartItemPayload, Order, Paginated, PaymentAttempt } from '@/types'
import { api } from './api'

export const orderService = {
  /** Only ids, quantities and the address id are sent: prices, shipping and totals are calculated by the backend. */
  async place(items: CartItemPayload[], addressId: number): Promise<Order> {
    const { data } = await api.post<{ data: Order }>('orders', { items, address_id: addressId })
    return data.data
  },

  async list(page = 1): Promise<Paginated<Order>> {
    const { data } = await api.get<Paginated<Order>>('orders', { params: { page } })
    return data
  },

  async find(id: number): Promise<Order> {
    const { data } = await api.get<{ data: Order }>(`orders/${id}`)
    return data.data
  },

  /** Pays with a test card token; the amount is always the order total, set by the backend. */
  async pay(id: number, cardToken: string): Promise<{ payment: PaymentAttempt; message: string }> {
    const { data } = await api.post<{ data: PaymentAttempt; message: string }>(`orders/${id}/payment`, { card_token: cardToken })
    return { payment: data.data, message: data.message }
  },
}
