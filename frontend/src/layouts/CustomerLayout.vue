<script setup lang="ts">
import { useRouter } from 'vue-router'
import AppNavbar from '@/components/AppNavbar.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const links = [
  { to: { name: 'account' }, label: 'Dashboard', exact: true },
  { to: { name: 'account.orders' }, label: 'Meus pedidos', exact: false },
  { to: { name: 'account.profile' }, label: 'Meu perfil', exact: true },
]

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'products' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <AppNavbar />
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-8 sm:px-6 md:flex-row">
      <aside class="md:w-56 md:shrink-0">
        <nav class="card flex gap-1 overflow-x-auto p-2 md:flex-col">
          <RouterLink
            v-for="link in links"
            :key="link.label"
            :to="link.to"
            class="rounded-lg px-3 py-2 text-sm whitespace-nowrap text-slate-600 hover:bg-slate-100"
            :exact-active-class="link.exact ? 'bg-indigo-50 font-semibold text-indigo-700' : ''"
            :active-class="link.exact ? '' : 'bg-indigo-50 font-semibold text-indigo-700'"
          >
            {{ link.label }}
          </RouterLink>
          <button type="button" class="rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50" @click="logout">
            Sair
          </button>
        </nav>
      </aside>
      <main class="min-w-0 flex-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>
