<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import OrderStatusBadge from '@/components/OrderStatusBadge.vue'
import { adminUserService } from '@/services/admin/userService'
import type { User } from '@/types'
import { formatDate, formatDateTime } from '@/utils/date'
import { formatMoney } from '@/utils/money'

const props = defineProps<{ id: number }>()
const user = ref<User | null>(null)

onMounted(async () => {
  user.value = await adminUserService.find(props.id)
})
</script>

<template>
  <LoadingState v-if="!user" />
  <div v-else class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <RouterLink :to="{ name: 'admin.users' }" class="text-sm text-indigo-600 hover:underline">← Clientes</RouterLink>
        <h1 class="page-title mt-1">{{ user.name }}</h1>
      </div>
      <RouterLink v-if="user.role === 'customer'" :to="{ name: 'admin.users.edit', params: { id: user.id } }" class="btn btn-secondary">Editar</RouterLink>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
      <div class="card p-5"><p class="text-sm text-slate-500">E-mail</p><p class="mt-1 font-medium break-all">{{ user.email }}</p></div>
      <div class="card p-5"><p class="text-sm text-slate-500">Tipo</p><p class="mt-1 font-medium">{{ user.role_label }}</p></div>
      <div class="card p-5"><p class="text-sm text-slate-500">Pedidos</p><p class="mt-1 text-2xl font-bold">{{ user.orders_count }}</p></div>
      <div class="card p-5"><p class="text-sm text-slate-500">Cadastro</p><p class="mt-1 font-medium">{{ formatDate(user.created_at) }}</p></div>
    </div>

    <section class="card">
      <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Pedidos recentes</h2>
      <p v-if="!user.orders?.length" class="p-5 text-sm text-slate-500">Nenhum pedido.</p>
      <div v-else class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr><th>Pedido</th><th>Data</th><th class="text-right">Total</th><th>Status</th></tr>
          </thead>
          <tbody>
            <tr v-for="order in user.orders" :key="order.id">
              <td><RouterLink :to="{ name: 'admin.orders.show', params: { id: order.id } }" class="font-medium text-indigo-600 hover:underline">#{{ order.id }}</RouterLink></td>
              <td>{{ formatDateTime(order.created_at) }}</td>
              <td class="text-right">{{ formatMoney(order.total) }}</td>
              <td><OrderStatusBadge :status="order.status" :label="order.status_label" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
