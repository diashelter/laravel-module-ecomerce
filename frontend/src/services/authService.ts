import type { User } from '@/types'
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
  async login(payload: LoginPayload): Promise<User> {
    await ensureCsrfCookie()
    const { data } = await api.post<{ data: User }>('auth/login', payload)
    return data.data
  },

  async register(payload: RegisterPayload): Promise<User> {
    await ensureCsrfCookie()
    const { data } = await api.post<{ data: User }>('auth/register', payload)
    return data.data
  },

  async logout(): Promise<void> {
    await api.post('auth/logout')
  },

  async me(): Promise<User> {
    const { data } = await api.get<{ data: User }>('auth/me')
    return data.data
  },
}
