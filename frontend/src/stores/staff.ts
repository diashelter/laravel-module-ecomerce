import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { adminAuthService } from '@/services/admin/authService'
import type { LoginPayload } from '@/services/authService'
import type { StaffMember } from '@/types'

/**
 * Keeps only the logged-in staff member in memory (the admin area). It is separate from the
 * shopper store: the two sessions are independent, even in the same browser.
 */
export const useStaffStore = defineStore('staff', () => {
  const member = ref<StaffMember | null>(null)
  const loaded = ref(false)
  let loading: Promise<void> | null = null

  const isAuthenticated = computed(() => member.value !== null)
  const isAdmin = computed(() => member.value?.role === 'admin')

  /** Only the admin role removes records and manages staff; support creates, edits and reads. */
  const canDelete = isAdmin
  const canManageStaff = isAdmin

  /** Fetches the current staff member once (on the first admin navigation). */
  async function ensureLoaded(): Promise<void> {
    if (loaded.value) return

    loading ??= adminAuthService
      .me()
      .then((current) => {
        member.value = current
      })
      .catch(() => {
        member.value = null
      })
      .finally(() => {
        loaded.value = true
        loading = null
      })

    return loading
  }

  async function login(payload: LoginPayload): Promise<StaffMember> {
    member.value = await adminAuthService.login(payload)
    loaded.value = true
    return member.value
  }

  async function logout(): Promise<void> {
    try {
      await adminAuthService.logout()
    } finally {
      clear()
    }
  }

  function clear(): void {
    member.value = null
    loaded.value = true
  }

  return { member, loaded, isAuthenticated, isAdmin, canDelete, canManageStaff, ensureLoaded, login, logout, clear }
})
