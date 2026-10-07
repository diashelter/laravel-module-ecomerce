import { computed, ref } from 'vue'
import { ApiError } from '@/services/api'
import { addressService } from '@/services/addressService'
import type { AddressPayload, CustomerAddress, ValidationErrors } from '@/types'

export const LOAD_ERROR_MESSAGE = 'Não foi possível carregar os endereços.'

/**
 * The logged-in customer's address book: listing, saving and deleting. A 422 puts each message
 * under its field, and a 409 (the limit of addresses) becomes a message above the form.
 * The list keeps the order of the API (most recent first).
 */
export function useAddressBook() {
  const addresses = ref<CustomerAddress[]>([])
  const loading = ref(false)
  const loadError = ref<string | null>(null)
  const saving = ref(false)
  const fieldErrors = ref<ValidationErrors>({})
  const formMessage = ref<string | null>(null)

  const isEmpty = computed(() => addresses.value.length === 0)

  async function load(): Promise<void> {
    loading.value = true
    loadError.value = null
    try {
      addresses.value = await addressService.list()
    } catch {
      loadError.value = LOAD_ERROR_MESSAGE
    } finally {
      loading.value = false
    }
  }

  function firstError(field: string): string | undefined {
    return fieldErrors.value[field]?.[0]
  }

  function clearFormErrors(): void {
    fieldErrors.value = {}
    formMessage.value = null
  }

  /** Creates the address, or replaces the one with `id`. Returns the saved address, or null when it was refused. */
  async function save(payload: AddressPayload, id?: number): Promise<CustomerAddress | null> {
    saving.value = true
    clearFormErrors()
    try {
      const saved = id === undefined ? await addressService.create(payload) : await addressService.update(id, payload)
      addresses.value =
        id === undefined ? [saved, ...addresses.value] : addresses.value.map((address) => (address.id === id ? saved : address))
      return saved
    } catch (error) {
      if (!(error instanceof ApiError)) throw error
      if (error.status === 422) fieldErrors.value = error.errors
      // 409: the limit of addresses. 404: the address was removed somewhere else.
      else if (error.status === 409 || error.status === 404) formMessage.value = error.message
      return null
    } finally {
      saving.value = false
    }
  }

  /** Asks before deleting. Returns whether the address was deleted. */
  async function remove(address: CustomerAddress): Promise<boolean> {
    if (!window.confirm(`Excluir o endereço de "${address.recipient_name}"?`)) return false

    try {
      await addressService.remove(address.id)
    } catch (error) {
      if (!(error instanceof ApiError)) throw error
      formMessage.value = error.message
      return false
    }
    addresses.value = addresses.value.filter((current) => current.id !== address.id)
    return true
  }

  return { addresses, loading, loadError, saving, fieldErrors, formMessage, isEmpty, load, firstError, clearFormErrors, save, remove }
}
