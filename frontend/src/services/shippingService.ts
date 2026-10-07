import type { BrazilianState, ShippingQuote } from '@/types'
import { api } from './api'

export const shippingService = {
  /** Public: the checkout asks it before the customer confirms the purchase. */
  async quote(state: BrazilianState): Promise<ShippingQuote> {
    const { data } = await api.get<{ data: ShippingQuote }>('shipping/quote', { params: { state } })
    return data.data
  },
}
