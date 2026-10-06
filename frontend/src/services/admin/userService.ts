import type { Paginated, User } from '@/types'
import { api } from '../api'

export interface CustomerPayload {
  name: string
  email: string
  password?: string
  password_confirmation?: string
}

export const adminUserService = {
  async list(page = 1): Promise<Paginated<User>> {
    const { data } = await api.get<Paginated<User>>('admin/users', { params: { page } })
    return data
  },

  async find(id: number): Promise<User> {
    const { data } = await api.get<{ data: User }>(`admin/users/${id}`)
    return data.data
  },

  async create(payload: CustomerPayload): Promise<User> {
    const { data } = await api.post<{ data: User }>('admin/users', payload)
    return data.data
  },

  async update(id: number, payload: CustomerPayload): Promise<User> {
    const { data } = await api.put<{ data: User }>(`admin/users/${id}`, payload)
    return data.data
  },
}
