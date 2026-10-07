import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useStaffStore } from '@/stores/staff'
import { guardRedirect } from './guards'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

/**
 * Route protection (the decision itself is in guards.ts). Each area loads only its own session:
 * the admin pages ask the staff session, everything else asks the shopper session.
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const staff = useStaffStore()

  if (to.meta.requiresStaff || to.meta.staffGuestOnly) {
    await staff.ensureLoaded()
  } else {
    await auth.ensureLoaded()
  }

  return guardRedirect(to, { customer: auth.isAuthenticated, staffRole: staff.member?.role ?? null })
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Loja Demo` : 'Loja Demo'
})

export default router
