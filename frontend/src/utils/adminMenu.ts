import type { RouteLocationRaw } from 'vue-router'

export interface AdminMenuLink {
  to: RouteLocationRaw
  label: string
  exact: boolean
}

/** The admin menu. "Usuários" (staff management) is only for the admin role. */
export function adminMenuLinks(canManageStaff: boolean): AdminMenuLink[] {
  return [
    { to: { name: 'admin.dashboard' }, label: 'Dashboard', exact: true },
    { to: { name: 'admin.products' }, label: 'Produtos', exact: false },
    { to: { name: 'admin.categories' }, label: 'Categorias', exact: false },
    { to: { name: 'admin.stocks' }, label: 'Estoque', exact: false },
    { to: { name: 'admin.customers' }, label: 'Clientes', exact: false },
    { to: { name: 'admin.orders' }, label: 'Pedidos', exact: false },
    ...(canManageStaff ? [{ to: { name: 'admin.users' }, label: 'Usuários', exact: false }] : []),
  ]
}
