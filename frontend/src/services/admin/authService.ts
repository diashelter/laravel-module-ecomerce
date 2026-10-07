import type { StaffMember } from '@/types'
import { api, ensureCsrfCookie } from '../api'
import type { LoginPayload } from '../authService'

export const adminAuthService = {
  async login(payload: LoginPayload): Promise<StaffMember> {
    await ensureCsrfCookie()
    const { data } = await api.post<{ data: StaffMember }>('admin/auth/login', payload)
    return data.data
  },

  async logout(): Promise<void> {
    await api.post('admin/auth/logout')
  },

  async me(): Promise<StaffMember> {
    const { data } = await api.get<{ data: StaffMember }>('admin/auth/me')
    return data.data
  },
}
