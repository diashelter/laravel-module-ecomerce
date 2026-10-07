import type { Paginated, StaffMember, StaffRole } from '@/types'
import { api } from '../api'

export interface StaffPayload {
  name: string
  email: string
  role: StaffRole
  password?: string
  password_confirmation?: string
}

export const adminStaffService = {
  async list(page = 1): Promise<Paginated<StaffMember>> {
    const { data } = await api.get<Paginated<StaffMember>>('admin/users', { params: { page } })
    return data
  },

  async find(id: number): Promise<StaffMember> {
    const { data } = await api.get<{ data: StaffMember }>(`admin/users/${id}`)
    return data.data
  },

  async create(payload: StaffPayload): Promise<StaffMember> {
    const { data } = await api.post<{ data: StaffMember }>('admin/users', payload)
    return data.data
  },

  async update(id: number, payload: StaffPayload): Promise<StaffMember> {
    const { data } = await api.put<{ data: StaffMember }>(`admin/users/${id}`, payload)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await api.delete(`admin/users/${id}`)
  },
}
