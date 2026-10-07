import type { RouteLocationRaw } from 'vue-router'
import type { StaffRole } from '@/types'

/** What the guard decides on: which sessions the browser has. */
export interface GuardState {
  customer: boolean
  staffRole: StaffRole | null
}

export interface GuardTarget {
  fullPath: string
  meta: {
    requiresShopper?: boolean
    guestOnly?: boolean
    requiresStaff?: boolean
    staffGuestOnly?: boolean
    requiresAdminRole?: boolean
  }
}

/**
 * Where to send the browser instead of the target, or undefined to let it through. Customer and
 * staff sessions are independent: a session of one side never opens the other side's pages.
 * This is only about user experience: the API enforces the same rules.
 */
export function guardRedirect(to: GuardTarget, state: GuardState): RouteLocationRaw | undefined {
  const { meta } = to

  if (meta.requiresShopper && !state.customer) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (meta.guestOnly && state.customer) {
    return { name: 'account' }
  }
  if (meta.requiresStaff && state.staffRole === null) {
    return { name: 'admin.login', query: { redirect: to.fullPath } }
  }
  if (meta.staffGuestOnly && state.staffRole !== null) {
    return { name: 'admin.dashboard' }
  }
  if (meta.requiresAdminRole && state.staffRole !== 'admin') {
    return { name: 'forbidden' }
  }
  return undefined
}
