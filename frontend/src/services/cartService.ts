import type { CartItemPayload, CartValidation } from '@/types'
import { api } from './api'

export const cartService = {
  /** Asks the backend to re-check products, status, stock, quantities and current prices. */
  async validate(items: CartItemPayload[]): Promise<CartValidation> {
    const { data } = await api.post<{ data: CartValidation }>('cart/validate', { items })
    return data.data
  },
}
