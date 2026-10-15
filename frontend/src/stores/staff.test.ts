import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import type { StaffMember, StaffRole } from '@/types'
import { useStaffStore } from './staff'

function member(role: StaffRole): StaffMember {
  return { id: '0199e3b0-4c2a-7d10-9a3b-000000000001', name: 'Equipe', email: 'equipe@example.com', role, role_label: role, created_at: '' }
}

function storeWith(role: StaffRole) {
  setActivePinia(createPinia())
  const staff = useStaffStore()
  staff.member = member(role)
  return staff
}

describe('staff store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the delete action only to the admin role', () => {
    expect(storeWith('admin').canDelete).toBe(true)
    expect(storeWith('support').canDelete).toBe(false)
  })

  it('has no permissions without a staff member', () => {
    const staff = useStaffStore()

    expect(staff.isAuthenticated).toBe(false)
    expect(staff.canDelete).toBe(false)
    expect(staff.canManageStaff).toBe(false)
  })
})
