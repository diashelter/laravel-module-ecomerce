import type { Order, Paginated } from '@/types'
import { api } from '../api'

export const adminOrderService = {
  async list(page = 1): Promise<Paginated<Order>> {
    const { data } = await api.get<Paginated<Order>>('admin/orders', { params: { page } })
    return data
  },

  async find(id: number): Promise<Order> {
    const { data } = await api.get<{ data: Order }>(`admin/orders/${id}`)
    return data.data
  },
}
