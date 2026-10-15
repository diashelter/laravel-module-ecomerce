import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import type { Product } from '@/types'
import { useCartStore } from './cart'

const FIRST_ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a'
const SECOND_ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0b'

function product(id: string, priceCents: number): Product {
  return {
    id,
    name: `Product ${id}`,
    price_cents: priceCents,
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

function freshStore() {
  setActivePinia(createPinia())
  return useCartStore()
}

describe('cart store', () => {
  let storage: Map<string, string>

  beforeEach(() => {
    storage = new Map()
    vi.stubGlobal('localStorage', {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => void storage.set(key, value),
      removeItem: (key: string) => void storage.delete(key),
    })
  })

  it('discards the legacy cart key', () => {
    storage.set('cart', JSON.stringify([{ product_id: 1, name: 'Old', price: '19.90', quantity: 2 }]))

    const cart = freshStore()

    expect(storage.has('cart')).toBe(false)
    expect(cart.isEmpty).toBe(true)
  })

  it('drops the cart saved under cart-v2', () => {
    storage.set('cart-v2', JSON.stringify([{ product_id: 1, name: 'Numeric id', price_cents: 1990, quantity: 2, max_quantity: 5 }]))

    const cart = freshStore()

    expect(storage.has('cart-v2')).toBe(false)
    expect(cart.isEmpty).toBe(true)
  })

  it('persists the cart only under cart-v3', async () => {
    const cart = freshStore()
    cart.add(product(FIRST_ID, 1990), 2)
    await nextTick()

    expect([...storage.keys()]).toEqual(['cart-v3'])
    expect(JSON.parse(storage.get('cart-v3') ?? '[]')[0]).toMatchObject({ product_id: FIRST_ID, price_cents: 1990, quantity: 2 })
  })

  it('totals price_cents times quantity', () => {
    const cart = freshStore()
    cart.add(product(FIRST_ID, 1990), 3)
    cart.add(product(SECOND_ID, 25000), 1)

    expect(cart.totalCents).toBe(30970)
  })
})
