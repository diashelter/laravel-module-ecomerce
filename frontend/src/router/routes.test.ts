import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter, type RouteLocationNormalized } from 'vue-router'
import { routes } from './routes'

const ID = '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a'

/** Resolves a path through the real route table and returns the name and the props the page receives. */
function resolve(path: string) {
  const router = createRouter({ history: createMemoryHistory(), routes })
  const resolved = router.resolve(path)
  const propsOption = resolved.matched.at(-1)?.props.default
  const props = typeof propsOption === 'function' ? propsOption(resolved as unknown as RouteLocationNormalized) : propsOption
  return { name: resolved.name, props }
}

describe('routes with a record id', () => {
  it.each([
    [`/products/${ID}`, 'product', { id: ID }],
    [`/payment/${ID}`, 'payment', { orderId: ID }],
    [`/account/orders/${ID}`, 'account.order', { id: ID }],
    [`/admin/products/${ID}/edit`, 'admin.products.edit', { id: ID }],
    [`/admin/customers/${ID}`, 'admin.customers.show', { id: ID }],
    [`/admin/customers/${ID}/edit`, 'admin.customers.edit', { id: ID }],
    [`/admin/users/${ID}/edit`, 'admin.users.edit', { id: ID }],
    [`/admin/orders/${ID}`, 'admin.orders.show', { id: ID }],
  ])('matches a canonical uuid in every route with an id: %s', (path, name, props) => {
    expect(resolve(path)).toEqual({ name, props })
  })

  it.each(['/products/7', '/account/orders/42', '/payment/abc', '/admin/orders/0199E3B0-4C2A-7D10-9A3B-5F6E7D8C9B0A'])(
    'renders not found for an id that is not a uuid: %s',
    (path) => {
      expect(resolve(path).name).toBe('not-found')
    },
  )
})
