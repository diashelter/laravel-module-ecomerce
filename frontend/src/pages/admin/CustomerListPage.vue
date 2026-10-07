<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import { adminCustomerService } from '@/services/admin/customerService'
import type { Customer, Paginated } from '@/types'
import { formatDate } from '@/utils/date'

const result = ref<Paginated<Customer> | null>(null)

async function load(page = 1): Promise<void> {
  result.value = await adminCustomerService.list(page)
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <h1 class="page-title">Clientes</h1>
      <RouterLink :to="{ name: 'admin.customers.create' }" class="btn btn-primary">Novo cliente</RouterLink>
    </div>

    <LoadingState v-if="!result" />
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Nome</th><th>E-mail</th><th class="text-right">Pedidos</th><th>Cadastro</th><th class="text-right">Ações</th></tr>
        </thead>
        <tbody>
          <tr v-for="customer in result.data" :key="customer.id">
            <td class="font-medium">{{ customer.name }}</td>
            <td>{{ customer.email }}</td>
            <td class="text-right">{{ customer.orders_count }}</td>
            <td>{{ formatDate(customer.created_at) }}</td>
            <td>
              <div class="flex justify-end gap-2">
                <RouterLink :to="{ name: 'admin.customers.show', params: { id: customer.id } }" class="btn btn-secondary btn-sm">Ver</RouterLink>
                <RouterLink :to="{ name: 'admin.customers.edit', params: { id: customer.id } }" class="btn btn-secondary btn-sm">Editar</RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="px-2 pb-2"><PaginationNav :meta="result.meta" @change="load" /></div>
    </div>
  </div>
</template>
