import type { CartItemPayload, Order, Paginated } from '@/types'
import { api } from './api'

export const orderService = {
  /** Only ids and quantities are sent: prices and totals are calculated by the backend. */
  async place(items: CartItemPayload[]): Promise<Order> {
    const { data } = await api.post<{ data: Order }>('orders', { items })
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

  async approvePayment(id: number): Promise<{ order: Order; message: string }> {
    const { data } = await api.post<{ data: Order; message: string }>(`orders/${id}/payment`)
    return { order: data.data, message: data.message }
  },
}
