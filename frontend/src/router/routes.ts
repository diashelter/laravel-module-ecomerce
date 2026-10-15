import type { RouteRecordRaw } from 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    /** Store pages that need a logged-in shopper. */
    requiresShopper?: boolean
    /** Login and register: a logged-in shopper is sent to "Minha conta". */
    guestOnly?: boolean
    /** Admin pages: need a logged-in staff member. */
    requiresStaff?: boolean
    /** Admin login: a logged-in staff member is sent to the dashboard. */
    staffGuestOnly?: boolean
    /** Only the admin role (staff management). */
    requiresAdminRole?: boolean
    title?: string
  }
}

/** The canonical id of a record: a UUID in lowercase with hyphens (the same form the API accepts). */
const ID_PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
// Routes with an id are case sensitive: the router would otherwise accept an uppercase UUID.

export const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/PublicLayout.vue'),
    children: [
      { path: '', redirect: { name: 'products' } },
      { path: 'products', name: 'products', component: () => import('@/pages/public/ProductListPage.vue'), meta: { title: 'Produtos' } },
      { path: `products/:id(${ID_PATTERN})`, sensitive: true, name: 'product', component: () => import('@/pages/public/ProductDetailPage.vue'), props: (route) => ({ id: String(route.params.id) }) },
      { path: 'cart', name: 'cart', component: () => import('@/pages/public/CartPage.vue'), meta: { title: 'Carrinho' } },
      {
        path: 'checkout',
        name: 'checkout',
        component: () => import('@/pages/public/CheckoutPage.vue'),
        meta: { requiresShopper: true, title: 'Checkout' },
      },
      {
        path: `payment/:orderId(${ID_PATTERN})`,
        sensitive: true,
        name: 'payment',
        component: () => import('@/pages/public/PaymentPage.vue'),
        props: (route) => ({ orderId: String(route.params.orderId) }),
        meta: { requiresShopper: true, title: 'Pagamento' },
      },
      { path: 'forbidden', name: 'forbidden', component: () => import('@/pages/ForbiddenPage.vue'), meta: { title: 'Acesso negado' } },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { guestOnly: true },
    children: [
      { path: 'login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue'), meta: { title: 'Entrar' } },
      { path: 'register', name: 'register', component: () => import('@/pages/auth/RegisterPage.vue'), meta: { title: 'Criar conta' } },
    ],
  },
  {
    path: '/account',
    component: () => import('@/layouts/CustomerLayout.vue'),
    meta: { requiresShopper: true },
    children: [
      { path: '', name: 'account', component: () => import('@/pages/account/AccountDashboardPage.vue'), meta: { title: 'Minha conta' } },
      { path: 'profile', name: 'account.profile', component: () => import('@/pages/account/ProfilePage.vue'), meta: { title: 'Meu perfil' } },
      { path: 'orders', name: 'account.orders', component: () => import('@/pages/account/OrderListPage.vue'), meta: { title: 'Meus pedidos' } },
      { path: 'addresses', name: 'account.addresses', component: () => import('@/pages/account/AddressBookPage.vue'), meta: { title: 'Endereços' } },
      {
        path: `orders/:id(${ID_PATTERN})`,
        sensitive: true,
        name: 'account.order',
        component: () => import('@/pages/account/OrderDetailPage.vue'),
        props: (route) => ({ id: String(route.params.id) }),
        meta: { title: 'Pedido' },
      },
    ],
  },
  {
    path: '/admin/login',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { staffGuestOnly: true },
    children: [{ path: '', name: 'admin.login', component: () => import('@/pages/auth/AdminLoginPage.vue'), meta: { title: 'Entrar no painel' } }],
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresStaff: true },
    children: [
      { path: '', name: 'admin.dashboard', component: () => import('@/pages/admin/DashboardPage.vue'), meta: { title: 'Dashboard' } },
      { path: 'products', name: 'admin.products', component: () => import('@/pages/admin/ProductListPage.vue'), meta: { title: 'Produtos' } },
      { path: 'products/new', name: 'admin.products.create', component: () => import('@/pages/admin/ProductFormPage.vue'), meta: { title: 'Novo produto' } },
      {
        path: `products/:id(${ID_PATTERN})/edit`,
        sensitive: true,
        name: 'admin.products.edit',
        component: () => import('@/pages/admin/ProductFormPage.vue'),
        props: (route) => ({ id: String(route.params.id) }),
        meta: { title: 'Editar produto' },
      },
      { path: 'categories', name: 'admin.categories', component: () => import('@/pages/admin/CategoryListPage.vue'), meta: { title: 'Categorias' } },
      { path: 'stocks', name: 'admin.stocks', component: () => import('@/pages/admin/StockListPage.vue'), meta: { title: 'Estoque' } },
      { path: 'customers', name: 'admin.customers', component: () => import('@/pages/admin/CustomerListPage.vue'), meta: { title: 'Clientes' } },
      { path: 'customers/new', name: 'admin.customers.create', component: () => import('@/pages/admin/CustomerFormPage.vue'), meta: { title: 'Novo cliente' } },
      {
        path: `customers/:id(${ID_PATTERN})`,
        sensitive: true,
        name: 'admin.customers.show',
        component: () => import('@/pages/admin/CustomerDetailPage.vue'),
        props: (route) => ({ id: String(route.params.id) }),
        meta: { title: 'Cliente' },
      },
      {
        path: `customers/:id(${ID_PATTERN})/edit`,
        sensitive: true,
        name: 'admin.customers.edit',
        component: () => import('@/pages/admin/CustomerFormPage.vue'),
        props: (route) => ({ id: String(route.params.id) }),
        meta: { title: 'Editar cliente' },
      },
      { path: 'users', name: 'admin.users', component: () => import('@/pages/admin/StaffListPage.vue'), meta: { requiresAdminRole: true, title: 'Usuários' } },
      { path: 'users/new', name: 'admin.users.create', component: () => import('@/pages/admin/StaffFormPage.vue'), meta: { requiresAdminRole: true, title: 'Novo usuário' } },
      {
        path: `users/:id(${ID_PATTERN})/edit`,
        sensitive: true,
        name: 'admin.users.edit',
        component: () => import('@/pages/admin/StaffFormPage.vue'),
        props: (route) => ({ id: String(route.params.id) }),
        meta: { requiresAdminRole: true, title: 'Editar usuário' },
      },
      { path: 'orders', name: 'admin.orders', component: () => import('@/pages/admin/OrderListPage.vue'), meta: { title: 'Pedidos' } },
      {
        path: `orders/:id(${ID_PATTERN})`,
        sensitive: true,
        name: 'admin.orders.show',
        component: () => import('@/pages/admin/OrderDetailPage.vue'),
        props: (route) => ({ id: String(route.params.id) }),
        meta: { title: 'Pedido' },
      },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue'), meta: { title: 'Página não encontrada' } },
]
