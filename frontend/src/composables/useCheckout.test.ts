import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/services/api'
import { addressService } from '@/services/addressService'
import { cartService } from '@/services/cartService'
import { orderService } from '@/services/orderService'
import { shippingService } from '@/services/shippingService'
import { useCartStore } from '@/stores/cart'
import type { AddressPayload, BrazilianState, CustomerAddress, Order, Product, ShippingQuote } from '@/types'
import { useCheckout } from './useCheckout'

const push = vi.fn()

vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))
vi.mock('@/services/addressService', () => ({ addressService: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() } }))
vi.mock('@/services/shippingService', () => ({ shippingService: { quote: vi.fn() } }))
vi.mock('@/services/cartService', () => ({ cartService: { validate: vi.fn() } }))
vi.mock('@/services/orderService', () => ({ orderService: { place: vi.fn() } }))

const QUOTES: Record<string, ShippingQuote> = {
  RJ: { state: 'RJ', price_cents: 2200, delivery_business_days: 4 },
  BA: { state: 'BA', price_cents: 3800, delivery_business_days: 8 },
  MG: { state: 'MG', price_cents: 2200, delivery_business_days: 4 },
}

function address(id: number, state: BrazilianState): CustomerAddress {
  return {
    id,
    recipient_name: 'Ana Souza',
    postal_code: '01310100',
    street: 'Avenida Paulista',
    number: '1000',
    complement: null,
    district: 'Bela Vista',
    city: 'São Paulo',
    state,
    created_at: '',
  }
}

const payload: AddressPayload = { ...address(0, 'MG'), complement: null }

function product(id: number): Product {
  return {
    id,
    name: `Product ${id}`,
    price_cents: 10000,
    description: '',
    image_url: null,
    status: 'active',
    status_label: 'Ativo',
    is_available: true,
    available_quantity: 10,
    stock: null,
    categories: [],
    created_at: '',
    updated_at: '',
  }
}

/** A checkout with 2 units of a product of R$ 100,00 in the cart, validated by the backend at R$ 200,00. */
function setup(addresses: CustomerAddress[]) {
  const cart = useCartStore()
  cart.add(product(7), 2)
  vi.mocked(cartService.validate).mockResolvedValue({ items: [], total_cents: 20000, is_valid: true })
  vi.mocked(addressService.list).mockResolvedValue(addresses)
  vi.mocked(shippingService.quote).mockImplementation(async (state) => QUOTES[state])

  return { cart, checkout: useCheckout() }
}

describe('useCheckout', () => {
  beforeEach(() => {
    vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined })
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('opens the address form when the customer has no address', async () => {
    const { checkout } = setup([])

    await checkout.init()

    expect(checkout.formOpen.value).toBe(true)
    expect(checkout.canConfirm.value).toBe(false)
  })

  it('selects and quotes an address saved during checkout', async () => {
    const { checkout } = setup([])
    vi.mocked(addressService.create).mockResolvedValue(address(9, 'MG'))
    await checkout.init()

    await checkout.saveAddress(payload)

    expect(addressService.create).toHaveBeenCalledWith(payload)
    expect(checkout.selectedAddressId.value).toBe(9)
    expect(shippingService.quote).toHaveBeenCalledWith('MG')
    expect(checkout.formOpen.value).toBe(false)
  })

  it('preselects the most recent address and quotes it', async () => {
    const { checkout } = setup([address(2, 'RJ'), address(1, 'BA')])

    await checkout.init()

    expect(checkout.selectedAddressId.value).toBe(2)
    expect(shippingService.quote).toHaveBeenCalledWith('RJ')
    expect(checkout.formOpen.value).toBe(false)

    checkout.openForm()
    expect(checkout.formOpen.value).toBe(true)
  })

  it('shows the subtotal, the shipping and the total', async () => {
    const { checkout } = setup([address(2, 'RJ')])

    await checkout.init()

    expect(checkout.subtotalCents.value).toBe(20000)
    expect(checkout.shippingCents.value).toBe(2200)
    expect(checkout.totalCents.value).toBe(22200)
    expect(checkout.deliveryText.value).toBe('Entrega em até 4 dias úteis após a aprovação do pagamento')
    expect(checkout.canConfirm.value).toBe(true)
  })

  it('quotes again when another address is selected', async () => {
    const { checkout } = setup([address(2, 'RJ'), address(1, 'BA')])
    await checkout.init()

    await checkout.selectAddress(1)

    expect(shippingService.quote).toHaveBeenLastCalledWith('BA')
    expect(checkout.shippingCents.value).toBe(3800)
    expect(checkout.totalCents.value).toBe(23800)
  })

  it('blocks the confirmation while the shipping is unknown', async () => {
    let reject!: (reason: Error) => void
    const { checkout } = setup([address(2, 'RJ')])
    vi.mocked(shippingService.quote).mockReturnValueOnce(new Promise((_, r) => (reject = r)))

    const loading = checkout.init()
    await vi.waitFor(() => expect(checkout.quoteStatus.value).toBe('loading'))
    expect(checkout.canConfirm.value).toBe(false)

    reject(new Error('network'))
    await loading
    expect(checkout.quoteMessage.value).toBe('Não foi possível calcular o frete.')
    expect(checkout.canConfirm.value).toBe(false)

    await checkout.retryQuote()
    expect(shippingService.quote).toHaveBeenCalledTimes(2)
    expect(checkout.quoteMessage.value).toBeNull()
    expect(checkout.canConfirm.value).toBe(true)
  })

  it('places the order with the selected address', async () => {
    const { cart, checkout } = setup([address(2, 'RJ'), address(1, 'BA')])
    vi.mocked(orderService.place).mockResolvedValue({ id: 33 } as Order)
    await checkout.init()
    const items = [...cart.payload]

    await checkout.confirm()

    expect(orderService.place).toHaveBeenCalledWith(items, 2)
    expect(push).toHaveBeenCalledWith({ name: 'payment', params: { orderId: 33 } })
  })

  it('reloads the addresses when the delivery address is refused', async () => {
    const { checkout } = setup([address(2, 'RJ')])
    vi.mocked(orderService.place).mockRejectedValue(
      new ApiError(422, 'Dados inválidos.', { address_id: ['Endereço de entrega não encontrado.'] }),
    )
    await checkout.init()
    expect(addressService.list).toHaveBeenCalledTimes(1)

    vi.mocked(addressService.list).mockResolvedValue([address(5, 'BA')])
    await checkout.confirm()

    expect(checkout.addressError.value).toBe('Endereço de entrega não encontrado.')
    expect(addressService.list).toHaveBeenCalledTimes(2)
    expect(checkout.selectedAddressId.value).toBe(5)
    expect(push).not.toHaveBeenCalled()
  })
})
