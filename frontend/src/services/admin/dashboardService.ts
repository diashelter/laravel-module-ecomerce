import type { DashboardData } from '@/types'
import { api } from '../api'

export const adminDashboardService = {
  async summary(): Promise<DashboardData> {
    const { data } = await api.get<{ data: DashboardData }>('admin/dashboard')
    return data.data
  },
}
