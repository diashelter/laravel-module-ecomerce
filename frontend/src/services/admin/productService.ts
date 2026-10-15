import type { Paginated, Product, ProductStatus } from '@/types'
import { api } from '../api'

export interface ProductPayload {
  name: string
  price_cents: number
  description: string
  image_url: string | null
  status: ProductStatus
  category_ids: string[]
}

export interface CreateProductPayload extends ProductPayload {
  stock_quantity: number
}

export const adminProductService = {
  async list(page = 1): Promise<Paginated<Product>> {
    const { data } = await api.get<Paginated<Product>>('admin/products', { params: { page } })
    return data
  },

  async find(id: string): Promise<Product> {
    const { data } = await api.get<{ data: Product }>(`admin/products/${id}`)
    return data.data
  },

  async create(payload: CreateProductPayload): Promise<Product> {
    const { data } = await api.post<{ data: Product }>('admin/products', payload)
    return data.data
  },

  async update(id: string, payload: ProductPayload): Promise<Product> {
    const { data } = await api.put<{ data: Product }>(`admin/products/${id}`, payload)
    return data.data
  },

  async updateStatus(id: string, status: ProductStatus): Promise<Product> {
    const { data } = await api.patch<{ data: Product }>(`admin/products/${id}/status`, { status })
    return data.data
  },

  async remove(id: string): Promise<void> {
    await api.delete(`admin/products/${id}`)
  },
}
