import type { AccountSummary, User } from '@/types'
import { api } from './api'

export interface ProfilePayload {
  name: string
  email: string
  current_password?: string
  password?: string
  password_confirmation?: string
}

export const accountService = {
  async summary(): Promise<AccountSummary> {
    const { data } = await api.get<{ data: AccountSummary }>('account')
    return data.data
  },

  async updateProfile(payload: ProfilePayload): Promise<User> {
    const { data } = await api.put<{ data: User }>('account/profile', payload)
    return data.data
  },
}
