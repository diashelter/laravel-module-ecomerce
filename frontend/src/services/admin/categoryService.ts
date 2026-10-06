import type { Category } from '@/types'
import { api } from '../api'

export const adminCategoryService = {
  async list(): Promise<Category[]> {
    const { data } = await api.get<{ data: Category[] }>('admin/categories')
    return data.data
  },

  async create(name: string): Promise<Category> {
    const { data } = await api.post<{ data: Category }>('admin/categories', { name })
    return data.data
  },

  async update(id: number, name: string): Promise<Category> {
    const { data } = await api.put<{ data: Category }>(`admin/categories/${id}`, { name })
    return data.data
  },

  async remove(id: number): Promise<void> {
    await api.delete(`admin/categories/${id}`)
  },
}
