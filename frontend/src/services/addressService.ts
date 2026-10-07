import type { AddressPayload, CustomerAddress } from '@/types'
import { api } from './api'

/** The logged-in customer's own address book, most recent first. */
export const addressService = {
  async list(): Promise<CustomerAddress[]> {
    const { data } = await api.get<{ data: CustomerAddress[] }>('account/addresses')
    return data.data
  },

  async create(payload: AddressPayload): Promise<CustomerAddress> {
    const { data } = await api.post<{ data: CustomerAddress }>('account/addresses', payload)
    return data.data
  },

  /** Replaces the whole address: every required field is sent again. */
  async update(id: number, payload: AddressPayload): Promise<CustomerAddress> {
    const { data } = await api.put<{ data: CustomerAddress }>(`account/addresses/${id}`, payload)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await api.delete(`account/addresses/${id}`)
  },
}
