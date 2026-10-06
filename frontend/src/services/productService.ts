import type { Paginated, Product } from '@/types'
import { api } from './api'

export type ProductSort = 'name' | 'price_asc' | 'price_desc'

export interface ProductFilters {
  category?: string
  sort?: ProductSort
  page?: number
}

export const productService = {
  async list(filters: ProductFilters): Promise<Paginated<Product>> {
    const { data } = await api.get<Paginated<Product>>('products', { params: filters })
    return data
  },

  async find(id: number): Promise<Product> {
    const { data } = await api.get<{ data: Product }>(`products/${id}`)
    return data.data
  },
}
