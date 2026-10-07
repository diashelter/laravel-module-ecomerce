import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authService, type LoginPayload, type RegisterPayload } from '@/services/authService'
import type { Customer } from '@/types'

/**
 * Keeps only the logged-in shopper in memory (the store area). The session itself is an
 * HTTP-only cookie managed by Laravel Sanctum: nothing about authentication goes to localStorage.
 * Staff members have their own store (see staff.ts).
 */
export const useAuthStore = defineStore('auth', () => {
  const customer = ref<Customer | null>(null)
  const loaded = ref(false)
  let loading: Promise<void> | null = null

  const isAuthenticated = computed(() => customer.value !== null)

  /** Fetches the current shopper once (on the first navigation). */
  async function ensureLoaded(): Promise<void> {
    if (loaded.value) return

    loading ??= authService
      .me()
      .then((current) => {
        customer.value = current
      })
      .catch(() => {
        customer.value = null
      })
      .finally(() => {
        loaded.value = true
        loading = null
      })

    return loading
  }

  async function login(payload: LoginPayload): Promise<Customer> {
    customer.value = await authService.login(payload)
    loaded.value = true
    return customer.value
  }

  async function register(payload: RegisterPayload): Promise<Customer> {
    customer.value = await authService.register(payload)
    loaded.value = true
    return customer.value
  }

  async function logout(): Promise<void> {
    try {
      await authService.logout()
    } finally {
      clear()
    }
  }

  function setCustomer(updated: Customer): void {
    customer.value = updated
  }

  function clear(): void {
    customer.value = null
    loaded.value = true
  }

  return { customer, loaded, isAuthenticated, ensureLoaded, login, register, logout, setCustomer, clear }
})
