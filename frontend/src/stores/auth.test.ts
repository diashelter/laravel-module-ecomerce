import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/services/api'
import { authService } from '@/services/authService'
import { useAuthStore } from './auth'

describe('auth store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('keeps the store anonymous while only a staff session exists', async () => {
    // A staff session does not open the store: the shopper session check answers 401.
    vi.spyOn(authService, 'me').mockRejectedValue(new ApiError(401, 'Não autenticado.', {}, 'UNAUTHENTICATED'))
    const auth = useAuthStore()

    await auth.ensureLoaded()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.loaded).toBe(true)
  })
})
