import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    requiresAdmin?: boolean
    requiresCustomer?: boolean
    guestOnly?: boolean
    title?: string
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/PublicLayout.vue'),
    children: [
      { path: '', redirect: { name: 'products' } },
      { path: 'products', name: 'products', component: () => import('@/pages/public/ProductListPage.vue'), meta: { title: 'Produtos' } },
      { path: 'products/:id(\\d+)', name: 'product', component: () => import('@/pages/public/ProductDetailPage.vue'), props: (route) => ({ id: Number(route.params.id) }) },
      { path: 'cart', name: 'cart', component: () => import('@/pages/public/CartPage.vue'), meta: { title: 'Carrinho' } },
      {
        path: 'checkout',
        name: 'checkout',
        component: () => import('@/pages/public/CheckoutPage.vue'),
        meta: { requiresAuth: true, requiresCustomer: true, title: 'Checkout' },
      },
      {
        path: 'payment/:orderId(\\d+)',
        name: 'payment',
        component: () => import('@/pages/public/PaymentPage.vue'),
        props: (route) => ({ orderId: Number(route.params.orderId) }),
        meta: { requiresAuth: true, requiresCustomer: true, title: 'Pagamento' },
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
    meta: { requiresAuth: true, requiresCustomer: true },
    children: [
      { path: '', name: 'account', component: () => import('@/pages/account/AccountDashboardPage.vue'), meta: { title: 'Minha conta' } },
      { path: 'profile', name: 'account.profile', component: () => import('@/pages/account/ProfilePage.vue'), meta: { title: 'Meu perfil' } },
      { path: 'orders', name: 'account.orders', component: () => import('@/pages/account/OrderListPage.vue'), meta: { title: 'Meus pedidos' } },
      {
        path: 'orders/:id(\\d+)',
        name: 'account.order',
        component: () => import('@/pages/account/OrderDetailPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Pedido' },
      },
    ],
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      { path: '', name: 'admin.dashboard', component: () => import('@/pages/admin/DashboardPage.vue'), meta: { title: 'Dashboard' } },
      { path: 'products', name: 'admin.products', component: () => import('@/pages/admin/ProductListPage.vue'), meta: { title: 'Produtos' } },
      { path: 'products/new', name: 'admin.products.create', component: () => import('@/pages/admin/ProductFormPage.vue'), meta: { title: 'Novo produto' } },
      {
        path: 'products/:id(\\d+)/edit',
        name: 'admin.products.edit',
        component: () => import('@/pages/admin/ProductFormPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Editar produto' },
      },
      { path: 'categories', name: 'admin.categories', component: () => import('@/pages/admin/CategoryListPage.vue'), meta: { title: 'Categorias' } },
      { path: 'stocks', name: 'admin.stocks', component: () => import('@/pages/admin/StockListPage.vue'), meta: { title: 'Estoque' } },
      { path: 'users', name: 'admin.users', component: () => import('@/pages/admin/UserListPage.vue'), meta: { title: 'Clientes' } },
      { path: 'users/new', name: 'admin.users.create', component: () => import('@/pages/admin/UserFormPage.vue'), meta: { title: 'Novo cliente' } },
      {
        path: 'users/:id(\\d+)',
        name: 'admin.users.show',
        component: () => import('@/pages/admin/UserDetailPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Cliente' },
      },
      {
        path: 'users/:id(\\d+)/edit',
        name: 'admin.users.edit',
        component: () => import('@/pages/admin/UserFormPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Editar cliente' },
      },
      { path: 'orders', name: 'admin.orders', component: () => import('@/pages/admin/OrderListPage.vue'), meta: { title: 'Pedidos' } },
      {
        path: 'orders/:id(\\d+)',
        name: 'admin.orders.show',
        component: () => import('@/pages/admin/OrderDetailPage.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Pedido' },
      },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue'), meta: { title: 'Página não encontrada' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

/**
 * Route protection. This is only about user experience: the API enforces the same rules.
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.ensureLoaded()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.isAdmin ? { name: 'admin.dashboard' } : { name: 'account' }
  }
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'forbidden' }
  }
  if (to.meta.requiresCustomer && !auth.isCustomer) {
    return { name: 'forbidden' }
  }
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Loja Demo` : 'Loja Demo'
})

export default router
