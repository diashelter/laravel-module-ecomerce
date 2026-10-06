<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import { adminUserService } from '@/services/admin/userService'
import type { Paginated, User } from '@/types'
import { formatDate } from '@/utils/date'

const result = ref<Paginated<User> | null>(null)

async function load(page = 1): Promise<void> {
  result.value = await adminUserService.list(page)
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <h1 class="page-title">Clientes</h1>
      <RouterLink :to="{ name: 'admin.users.create' }" class="btn btn-primary">Novo cliente</RouterLink>
    </div>

    <LoadingState v-if="!result" />
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Nome</th><th>E-mail</th><th>Tipo</th><th class="text-right">Pedidos</th><th>Cadastro</th><th class="text-right">Ações</th></tr>
        </thead>
        <tbody>
          <tr v-for="user in result.data" :key="user.id">
            <td class="font-medium">{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', user.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-700']">
                {{ user.role_label }}
              </span>
            </td>
            <td class="text-right">{{ user.orders_count }}</td>
            <td>{{ formatDate(user.created_at) }}</td>
            <td>
              <div class="flex justify-end gap-2">
                <RouterLink :to="{ name: 'admin.users.show', params: { id: user.id } }" class="btn btn-secondary btn-sm">Ver</RouterLink>
                <RouterLink v-if="user.role === 'customer'" :to="{ name: 'admin.users.edit', params: { id: user.id } }" class="btn btn-secondary btn-sm">
                  Editar
                </RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="px-2 pb-2"><PaginationNav :meta="result.meta" @change="load" /></div>
    </div>
  </div>
</template>
