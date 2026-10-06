import { ref } from 'vue'
import { ApiError } from '@/services/api'
import { useNotificationStore } from '@/stores/notifications'
import type { ValidationErrors } from '@/types'

/**
 * Stores 422 field errors returned by Laravel and shows other errors as notifications.
 */
export function useFormErrors() {
  const errors = ref<ValidationErrors>({})
  const notifications = useNotificationStore()

  function first(field: string): string | undefined {
    return errors.value[field]?.[0]
  }

  function handle(error: unknown): void {
    if (error instanceof ApiError && error.status === 422) {
      errors.value = error.errors
      return
    }
    errors.value = {}
    if (error instanceof ApiError && [404, 409].includes(error.status)) {
      notifications.error(error.message)
    }
  }

  function reset(): void {
    errors.value = {}
  }

  return { errors, first, handle, reset }
}
