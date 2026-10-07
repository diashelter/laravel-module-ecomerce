import type { Customer } from '@/types'
import { api, ensureCsrfCookie } from './api'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload extends LoginPayload {
  name: string
  password_confirmation: string
}

export const authService = {
  async login(payload: LoginPayload): Promise<Customer> {
    await ensureCsrfCookie()
    const { data } = await api.post<{ data: Customer }>('auth/login', payload)
    return data.data
  },

  async register(payload: RegisterPayload): Promise<Customer> {
    await ensureCsrfCookie()
    const { data } = await api.post<{ data: Customer }>('auth/register', payload)
    return data.data
  },

  async logout(): Promise<void> {
    await api.post('auth/logout')
  },

  async me(): Promise<Customer> {
    const { data } = await api.get<{ data: Customer }>('auth/me')
    return data.data
  },
}
