import type { Paginated, Product, ProductStatus } from '@/types'
import { api } from '../api'

export interface ProductPayload {
  name: string
  price: string
  description: string
  image_url: string | null
  status: ProductStatus
  category_ids: number[]
}

export interface CreateProductPayload extends ProductPayload {
  stock_quantity: number
}

export const adminProductService = {
  async list(page = 1): Promise<Paginated<Product>> {
    const { data } = await api.get<Paginated<Product>>('admin/products', { params: { page } })
    return data
  },

  async find(id: number): Promise<Product> {
    const { data } = await api.get<{ data: Product }>(`admin/products/${id}`)
    return data.data
  },

  async create(payload: CreateProductPayload): Promise<Product> {
    const { data } = await api.post<{ data: Product }>('admin/products', payload)
    return data.data
  },

  async update(id: number, payload: ProductPayload): Promise<Product> {
    const { data } = await api.put<{ data: Product }>(`admin/products/${id}`, payload)
    return data.data
  },

  async updateStatus(id: number, status: ProductStatus): Promise<Product> {
    const { data } = await api.patch<{ data: Product }>(`admin/products/${id}/status`, { status })
    return data.data
  },

  async remove(id: number): Promise<void> {
    await api.delete(`admin/products/${id}`)
  },
}
