import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { adminMenuLinks } from '@/utils/adminMenu'
import { guardRedirect, type GuardState, type GuardTarget } from './guards'
import { routes } from './routes'

const visitor: GuardState = { customer: false, staffRole: null }
const staffOnly = (role: 'admin' | 'support'): GuardState => ({ customer: false, staffRole: role })

/** Resolves the target through the real route table, so the meta comes from routes.ts. */
function target(path: string): GuardTarget {
  const router = createRouter({ history: createMemoryHistory(), routes })
  const resolved = router.resolve(path)
  return { fullPath: resolved.fullPath, meta: resolved.meta }
}

describe('router guard', () => {
  it('sends a staff-only browser from checkout to the store login', () => {
    expect(guardRedirect(target('/checkout'), staffOnly('admin'))).toEqual({ name: 'login', query: { redirect: '/checkout' } })
  })

  it.each(['/admin', '/admin/products', '/admin/customers/3'])('sends a browser without a staff session to the admin login: %s', (path) => {
    const expected = { name: 'admin.login', query: { redirect: path } }

    expect(guardRedirect(target(path), visitor)).toEqual(expected)
    expect(guardRedirect(target(path), { customer: true, staffRole: null })).toEqual(expected)
  })

  it('sends a staff member away from the admin login to the dashboard', () => {
    expect(guardRedirect(target('/admin/login'), staffOnly('support'))).toEqual({ name: 'admin.dashboard' })
    expect(guardRedirect(target('/admin/login'), visitor)).toBeUndefined()
  })

  it('hides staff management from the support role', () => {
    expect(guardRedirect(target('/admin/users'), staffOnly('support'))).toEqual({ name: 'forbidden' })
    expect(adminMenuLinks(false).map((link) => link.label)).not.toContain('Usuários')
  })

  it('shows staff management to the admin role', () => {
    const menu = adminMenuLinks(true).find((link) => link.label === 'Usuários')

    expect(menu?.to).toEqual({ name: 'admin.users' })
    expect(guardRedirect(target('/admin/users'), staffOnly('admin'))).toBeUndefined()
  })

  it('registers the admin customer pages under admin customers', () => {
    const router = createRouter({ history: createMemoryHistory(), routes })

    expect(['/admin/customers', '/admin/customers/new', '/admin/customers/3', '/admin/customers/3/edit'].map((path) => router.resolve(path).name)).toEqual([
      'admin.customers',
      'admin.customers.create',
      'admin.customers.show',
      'admin.customers.edit',
    ])
    expect(adminMenuLinks(false).find((link) => link.label === 'Clientes')?.to).toEqual({ name: 'admin.customers' })
  })

  it('registers the address book under the customer account', () => {
    const router = createRouter({ history: createMemoryHistory(), routes })
    const resolved = router.resolve('/account/addresses')

    expect(resolved.name).toBe('account.addresses')
    expect(resolved.matched[0].meta.requiresShopper).toBe(true)
    expect(guardRedirect(target('/account/addresses'), visitor)).toEqual({ name: 'login', query: { redirect: '/account/addresses' } })
  })
})
