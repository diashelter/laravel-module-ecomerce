import type { Paginated, Stock } from '@/types'
import { api } from '../api'

export type StockOperation = 'increase' | 'decrease'

export const adminStockService = {
  async list(page = 1): Promise<Paginated<Stock>> {
    const { data } = await api.get<Paginated<Stock>>('admin/stocks', { params: { page } })
    return data
  },

  async adjust(id: number, operation: StockOperation, quantity: number): Promise<Stock> {
    const { data } = await api.put<{ data: Stock }>(`admin/stocks/${id}`, { operation, quantity })
    return data.data
  },
}
