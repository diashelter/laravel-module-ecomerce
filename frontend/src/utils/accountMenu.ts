import type { RouteLocationRaw } from 'vue-router'

export interface AccountMenuLink {
  to: RouteLocationRaw
  label: string
  exact: boolean
}

/** The customer area menu. */
export function accountMenuLinks(): AccountMenuLink[] {
  return [
    { to: { name: 'account' }, label: 'Dashboard', exact: true },
    { to: { name: 'account.orders' }, label: 'Meus pedidos', exact: false },
    { to: { name: 'account.addresses' }, label: 'Endereços', exact: false },
    { to: { name: 'account.profile' }, label: 'Meu perfil', exact: true },
  ]
}
