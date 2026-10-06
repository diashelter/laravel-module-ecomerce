import type { Category } from '@/types'
import { api } from './api'

export const categoryService = {
  async list(): Promise<Category[]> {
    const { data } = await api.get<{ data: Category[] }>('categories')
    return data.data
  },
}
