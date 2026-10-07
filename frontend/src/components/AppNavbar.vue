<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useStaffStore } from '@/stores/staff'

const auth = useAuthStore()
const cart = useCartStore()
const staff = useStaffStore()
const router = useRouter()
const open = ref(false)

async function logout(): Promise<void> {
  // The browser has a single session cookie, so leaving the store ends the admin session too.
  await auth.logout()
  staff.clear()
  await router.push({ name: 'products' })
}
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
      <RouterLink :to="{ name: 'products' }"><AppLogo /></RouterLink>

      <button type="button" class="rounded p-2 text-slate-600 hover:bg-slate-100 sm:hidden" aria-label="Abrir menu" @click="open = !open">
        ☰
      </button>

      <nav
        :class="[open ? 'flex' : 'hidden', 'absolute inset-x-0 top-full flex-col gap-1 border-b border-slate-200 bg-white p-4 sm:static sm:flex sm:flex-row sm:items-center sm:gap-2 sm:border-0 sm:p-0']"
        @click="open = false"
      >
        <RouterLink :to="{ name: 'products' }" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100" active-class="font-semibold text-indigo-700">
          Produtos
        </RouterLink>
        <RouterLink :to="{ name: 'cart' }" class="relative rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100" active-class="font-semibold text-indigo-700">
          Carrinho
          <span v-if="cart.count > 0" class="ml-1 rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">{{ cart.count }}</span>
        </RouterLink>

        <template v-if="auth.isAuthenticated">
          <RouterLink :to="{ name: 'account' }" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100" active-class="font-semibold text-indigo-700">
            Minha conta
          </RouterLink>
          <button type="button" class="rounded-lg px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-100" @click="logout">Sair</button>
        </template>
        <template v-else>
          <RouterLink :to="{ name: 'login' }" class="btn btn-primary btn-sm">Entrar</RouterLink>
        </template>
      </nav>
    </div>
  </header>
</template>
