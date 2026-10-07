import type { Customer, Paginated } from '@/types'
import { api } from '../api'

export interface CustomerPayload {
  name: string
  email: string
  password?: string
  password_confirmation?: string
}

export const adminCustomerService = {
  async list(page = 1): Promise<Paginated<Customer>> {
    const { data } = await api.get<Paginated<Customer>>('admin/customers', { params: { page } })
    return data
  },

  async find(id: number): Promise<Customer> {
    const { data } = await api.get<{ data: Customer }>(`admin/customers/${id}`)
    return data.data
  },

  async create(payload: CustomerPayload): Promise<Customer> {
    const { data } = await api.post<{ data: Customer }>('admin/customers', payload)
    return data.data
  },

  async update(id: number, payload: CustomerPayload): Promise<Customer> {
    const { data } = await api.put<{ data: Customer }>(`admin/customers/${id}`, payload)
    return data.data
  },
}
