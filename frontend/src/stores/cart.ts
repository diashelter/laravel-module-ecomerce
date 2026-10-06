import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import type { CartItemPayload, CartValidation, Product } from '@/types'
import { toCents } from '@/utils/money'

export interface CartItem {
  product_id: number
  name: string
  image_url: string | null
  price: string
  quantity: number
  /** Last known available stock, used only to limit the quantity buttons. */
  max_quantity: number
}

const STORAGE_KEY = 'cart'

function loadItems(): CartItem[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed: unknown = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? (parsed as CartItem[]) : []
  } catch {
    return []
  }
}

/**
 * Shopping cart state. Only cart data is persisted in localStorage.
 * Prices and totals shown here are for display: the backend recalculates everything.
 */
export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>(loadItems())

  watch(
    items,
    (value) => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
      } catch {
        // Storage may be unavailable (private mode); the cart still works in memory.
      }
    },
    { deep: true },
  )

  const count = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0))
  const totalCents = computed(() => items.value.reduce((sum, item) => sum + subtotalCents(item), 0))
  const isEmpty = computed(() => items.value.length === 0)
  const payload = computed<CartItemPayload[]>(() =>
    items.value.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
  )

  function subtotalCents(item: CartItem): number {
    return toCents(item.price) * item.quantity
  }

  function find(productId: number): CartItem | undefined {
    return items.value.find((item) => item.product_id === productId)
  }

  function add(product: Product, quantity = 1): boolean {
    if (!product.is_available) return false

    const existing = find(product.id)
    if (existing) {
      existing.quantity = Math.min(existing.quantity + quantity, product.available_quantity)
      existing.max_quantity = product.available_quantity
      existing.price = product.price
      return true
    }

    items.value.push({
      product_id: product.id,
      name: product.name,
      image_url: product.image_url,
      price: product.price,
      quantity: Math.min(quantity, product.available_quantity),
      max_quantity: product.available_quantity,
    })
    return true
  }

  function increment(productId: number): void {
    const item = find(productId)
    if (item && item.quantity < item.max_quantity) item.quantity++
  }

  function decrement(productId: number): void {
    const item = find(productId)
    if (!item) return
    if (item.quantity > 1) item.quantity--
    else remove(productId)
  }

  function remove(productId: number): void {
    items.value = items.value.filter((item) => item.product_id !== productId)
  }

  function clear(): void {
    items.value = []
  }

  /** Updates names, prices and stock limits with the values returned by the backend. */
  function syncFromValidation(validation: CartValidation): void {
    for (const line of validation.items) {
      const item = find(line.product_id)
      if (!item || line.name === null || line.unit_price === null) continue

      item.name = line.name
      item.image_url = line.image_url
      item.price = line.unit_price
      item.max_quantity = line.available_quantity
    }
  }

  return { items, count, totalCents, isEmpty, payload, subtotalCents, add, increment, decrement, remove, clear, syncFromValidation }
})
