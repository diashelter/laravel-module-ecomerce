import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authService, type LoginPayload, type RegisterPayload } from '@/services/authService'
import type { User } from '@/types'

/**
 * Keeps only the authenticated user in memory. The session itself is an HTTP-only
 * cookie managed by Laravel Sanctum: nothing about authentication goes to localStorage.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loaded = ref(false)
  let loading: Promise<void> | null = null

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isCustomer = computed(() => user.value?.role === 'customer')

  /** Fetches the current user once (on the first navigation). */
  async function ensureLoaded(): Promise<void> {
    if (loaded.value) return

    loading ??= authService
      .me()
      .then((current) => {
        user.value = current
      })
      .catch(() => {
        user.value = null
      })
      .finally(() => {
        loaded.value = true
        loading = null
      })

    return loading
  }

  async function login(payload: LoginPayload): Promise<User> {
    user.value = await authService.login(payload)
    loaded.value = true
    return user.value
  }

  async function register(payload: RegisterPayload): Promise<User> {
    user.value = await authService.register(payload)
    loaded.value = true
    return user.value
  }

  async function logout(): Promise<void> {
    try {
      await authService.logout()
    } finally {
      clear()
    }
  }

  function setUser(updated: User): void {
    user.value = updated
  }

  function clear(): void {
    user.value = null
    loaded.value = true
  }

  return { user, loaded, isAuthenticated, isAdmin, isCustomer, ensureLoaded, login, register, logout, setUser, clear }
})
