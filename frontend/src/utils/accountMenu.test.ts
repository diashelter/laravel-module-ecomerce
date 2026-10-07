import { describe, expect, it } from 'vitest'
import { accountMenuLinks } from './accountMenu'

describe('account menu', () => {
  it('lists Endereços right after Meus pedidos in the account menu', () => {
    const links = accountMenuLinks()

    expect(links.map((link) => link.label)).toEqual(['Dashboard', 'Meus pedidos', 'Endereços', 'Meu perfil'])
    expect(links[2].to).toEqual({ name: 'account.addresses' })
  })
})
