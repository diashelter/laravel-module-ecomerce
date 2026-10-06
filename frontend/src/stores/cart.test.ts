import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import type { Product } from '@/types'
import { useCartStore } from './cart'

function product(id: number, priceCents: number): Product {
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

  it('persists the cart only under cart-v2', async () => {
    const cart = freshStore()
    cart.add(product(1, 1990), 2)
    await nextTick()

    expect([...storage.keys()]).toEqual(['cart-v2'])
    expect(JSON.parse(storage.get('cart-v2') ?? '[]')[0]).toMatchObject({ product_id: 1, price_cents: 1990, quantity: 2 })
  })

  it('totals price_cents times quantity', () => {
    const cart = freshStore()
    cart.add(product(1, 1990), 3)
    cart.add(product(2, 25000), 1)

    expect(cart.totalCents).toBe(30970)
  })
})
