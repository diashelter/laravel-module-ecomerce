<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const menuOpen = ref(false)

const links = [
  { to: { name: 'admin.dashboard' }, label: 'Dashboard', exact: true },
  { to: { name: 'admin.products' }, label: 'Produtos', exact: false },
  { to: { name: 'admin.categories' }, label: 'Categorias', exact: false },
  { to: { name: 'admin.stocks' }, label: 'Estoque', exact: false },
  { to: { name: 'admin.users' }, label: 'Clientes', exact: false },
  { to: { name: 'admin.orders' }, label: 'Pedidos', exact: false },
]

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen md:flex">
    <aside class="bg-slate-900 text-slate-200 md:fixed md:inset-y-0 md:w-60">
      <div class="flex items-center justify-between px-5 py-4">
        <RouterLink :to="{ name: 'admin.dashboard' }"><AppLogo dark /></RouterLink>
        <button type="button" class="rounded p-2 hover:bg-slate-800 md:hidden" aria-label="Abrir menu" @click="menuOpen = !menuOpen">
          ☰
        </button>
      </div>
      <nav :class="[menuOpen ? 'block' : 'hidden', 'space-y-1 px-3 pb-4 md:block']" @click="menuOpen = false">
        <RouterLink
          v-for="link in links"
          :key="link.label"
          :to="link.to"
          class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800"
          :exact-active-class="link.exact ? 'bg-slate-800 font-semibold text-white' : ''"
          :active-class="link.exact ? '' : 'bg-slate-800 font-semibold text-white'"
        >
          {{ link.label }}
        </RouterLink>
        <RouterLink :to="{ name: 'products' }" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800">Ver loja</RouterLink>
        <button type="button" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-red-300 hover:bg-slate-800" @click="logout">
          Sair
        </button>
      </nav>
    </aside>
    <div class="min-w-0 flex-1 md:ml-60">
      <header class="border-b border-slate-200 bg-white px-6 py-4 text-sm text-slate-500">
        Painel administrativo · <span class="font-medium text-slate-700">{{ auth.user?.name }}</span>
      </header>
      <main class="p-4 sm:p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
