import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '@/services/api'
import { cartService } from '@/services/cartService'
import { orderService } from '@/services/orderService'
import { shippingService } from '@/services/shippingService'
import { useCartStore } from '@/stores/cart'
import { useNotificationStore } from '@/stores/notifications'
import type { AddressPayload, CartValidation, CustomerAddress, ShippingQuote } from '@/types'
import { leadTimeText } from '@/utils/delivery'
import { useAddressBook } from './useAddressBook'

export const QUOTE_ERROR_MESSAGE = 'Não foi possível calcular o frete.'

type QuoteStatus = 'idle' | 'loading' | 'ready' | 'failed'

/**
 * The checkout: the validated cart, the delivery address (picked from the book, or typed in
 * the inline form when there is none) and the shipping quoted for its state. Confirming sends
 * only the cart items and the address id: the backend calculates the shipping and the total,
 * so the figures shown here are for display.
 */
export function useCheckout() {
  const cart = useCartStore()
  const notifications = useNotificationStore()
  const router = useRouter()
  const book = useAddressBook()

  const validation = ref<CartValidation | null>(null)
  const loadingCart = ref(false)
  const selectedAddressId = ref<number | null>(null)
  const formOpen = ref(false)
  const quote = ref<ShippingQuote | null>(null)
  const quoteStatus = ref<QuoteStatus>('idle')
  const submitting = ref(false)
  const conflictMessage = ref<string | null>(null)
  const addressError = ref<string | null>(null)

  // A quote that comes back after another address was picked must not overwrite the newer one.
  let quoteTicket = 0

  const selectedAddress = computed<CustomerAddress | null>(
    () => book.addresses.value.find((address) => address.id === selectedAddressId.value) ?? null,
  )
  const subtotalCents = computed(() => validation.value?.total_cents ?? 0)
  const shippingCents = computed(() => quote.value?.price_cents ?? null)
  const totalCents = computed(() => (shippingCents.value === null ? null : subtotalCents.value + shippingCents.value))
  const deliveryText = computed(() => (quote.value === null ? null : leadTimeText(quote.value.delivery_business_days)))
  const quoteMessage = computed(() => (quoteStatus.value === 'failed' ? QUOTE_ERROR_MESSAGE : null))
  const canConfirm = computed(
    () =>
      validation.value?.is_valid === true &&
      selectedAddress.value !== null &&
      quoteStatus.value === 'ready' &&
      !loadingCart.value &&
      !submitting.value,
  )

  /** Before confirming, the backend re-checks products, status, stock, quantities and prices. */
  async function validate(): Promise<void> {
    if (cart.isEmpty) return
    loadingCart.value = true
    try {
      validation.value = await cartService.validate(cart.payload)
      cart.syncFromValidation(validation.value)
    } finally {
      loadingCart.value = false
    }
  }

  async function requestQuote(): Promise<void> {
    const address = selectedAddress.value
    const ticket = ++quoteTicket

    if (address === null) {
      quote.value = null
      quoteStatus.value = 'idle'
      return
    }

    quote.value = null
    quoteStatus.value = 'loading'
    try {
      const result = await shippingService.quote(address.state)
      if (ticket !== quoteTicket) return
      quote.value = result
      quoteStatus.value = 'ready'
    } catch {
      if (ticket !== quoteTicket) return
      quoteStatus.value = 'failed'
    }
  }

  async function pickAddress(id: number): Promise<void> {
    selectedAddressId.value = id
    formOpen.value = false
    await requestQuote()
  }

  /** The customer chose an address: whatever was said about the previous one no longer applies. */
  async function selectAddress(id: number): Promise<void> {
    addressError.value = null
    await pickAddress(id)
  }

  /** Loads the book: no address opens the inline form, otherwise the most recent one is picked. */
  async function loadAddresses(): Promise<void> {
    await book.load()

    // A failed load says nothing about the book: it must not open the form as if it were empty.
    if (book.loadError.value !== null) return

    if (book.isEmpty.value) {
      selectedAddressId.value = null
      formOpen.value = true
      await requestQuote()
    } else if (selectedAddress.value === null) {
      // Picked here, not by the customer: an address error shown just before must stay on screen.
      await pickAddress(book.addresses.value[0].id)
    }
  }

  async function init(): Promise<void> {
    await Promise.all([validate(), loadAddresses()])
  }

  function openForm(): void {
    book.clearFormErrors()
    formOpen.value = true
  }

  /** The form can only be dismissed while there is an address to fall back to. */
  function closeForm(): void {
    if (!book.isEmpty.value) formOpen.value = false
  }

  /** Saves the inline form's address, picks it and quotes its state. */
  async function saveAddress(payload: AddressPayload): Promise<CustomerAddress | null> {
    const saved = await book.save(payload)
    if (saved !== null) await selectAddress(saved.id)
    return saved
  }

  async function confirm(): Promise<void> {
    if (!canConfirm.value || selectedAddressId.value === null) return

    submitting.value = true
    conflictMessage.value = null
    addressError.value = null
    try {
      const order = await orderService.place(cart.payload, selectedAddressId.value)
      cart.clear()
      notifications.success(`Pedido #${order.id} criado com sucesso!`)
      await router.push({ name: 'payment', params: { orderId: order.id } })
    } catch (error) {
      if (!(error instanceof ApiError)) throw error
      if (error.status === 409) {
        // Stock changed between the validation and the confirmation.
        conflictMessage.value = error.message
        await validate()
      } else if (error.status === 422) {
        const message = error.errors.address_id?.[0]
        if (message === undefined) {
          notifications.error(error.message)
        } else {
          // The address is not in the book anymore (deleted elsewhere): show why and reload the book.
          addressError.value = message
          await loadAddresses()
        }
      }
    } finally {
      submitting.value = false
    }
  }

  return {
    book,
    validation,
    loadingCart,
    selectedAddressId,
    selectedAddress,
    formOpen,
    quote,
    quoteStatus,
    quoteMessage,
    subtotalCents,
    shippingCents,
    totalCents,
    deliveryText,
    submitting,
    conflictMessage,
    addressError,
    canConfirm,
    init,
    loadAddresses,
    selectAddress,
    retryQuote: requestQuote,
    openForm,
    closeForm,
    saveAddress,
    confirm,
  }
}
