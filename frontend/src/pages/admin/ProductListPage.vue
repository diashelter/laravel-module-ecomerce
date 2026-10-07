<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import { ApiError } from '@/services/api'
import { adminProductService } from '@/services/admin/productService'
import { useNotificationStore } from '@/stores/notifications'
import { useStaffStore } from '@/stores/staff'
import type { Paginated, Product } from '@/types'
import { formatCents } from '@/utils/money'

const notifications = useNotificationStore()
const staff = useStaffStore()
const result = ref<Paginated<Product> | null>(null)
const page = ref(1)

async function load(newPage = page.value): Promise<void> {
  page.value = newPage
  result.value = await adminProductService.list(newPage)
}

async function toggleStatus(product: Product): Promise<void> {
  const status = product.status === 'active' ? 'inactive' : 'active'
  await adminProductService.updateStatus(product.id, status)
  notifications.success(status === 'active' ? 'Produto ativado.' : 'Produto desativado.')
  await load()
}

async function remove(product: Product): Promise<void> {
  if (!window.confirm(`Excluir "${product.name}"?`)) return
  try {
    await adminProductService.remove(product.id)
    notifications.success('Produto excluído.')
    await load()
  } catch (error) {
    // 409: the product is part of past orders.
    if (error instanceof ApiError && error.status === 409) notifications.error(error.message)
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <h1 class="page-title">Produtos</h1>
      <RouterLink :to="{ name: 'admin.products.create' }" class="btn btn-primary">Novo produto</RouterLink>
    </div>

    <LoadingState v-if="!result" />
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Produto</th><th class="text-right">Preço</th><th class="text-right">Estoque</th><th>Status</th><th>Categorias</th><th class="text-right">Ações</th></tr>
        </thead>
        <tbody>
          <tr v-for="product in result.data" :key="product.id">
            <td>
              <div class="flex items-center gap-3">
                <img :src="product.image_url ?? ''" alt="" class="h-10 w-10 rounded bg-slate-100 object-cover" />
                <span class="font-medium">{{ product.name }}</span>
              </div>
            </td>
            <td class="text-right whitespace-nowrap">{{ formatCents(product.price_cents) }}</td>
            <td :class="['text-right', product.available_quantity === 0 && 'font-semibold text-red-600']">{{ product.available_quantity }}</td>
            <td>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', product.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600']">
                {{ product.status_label }}
              </span>
            </td>
            <td class="text-xs text-slate-500">{{ product.categories.map((category) => category.name).join(', ') }}</td>
            <td>
              <div class="flex justify-end gap-2">
                <RouterLink :to="{ name: 'admin.products.edit', params: { id: product.id } }" class="btn btn-secondary btn-sm">Editar</RouterLink>
                <button type="button" class="btn btn-secondary btn-sm" @click="toggleStatus(product)">
                  {{ product.status === 'active' ? 'Desativar' : 'Ativar' }}
                </button>
                <button v-if="staff.canDelete" type="button" class="btn btn-danger btn-sm" @click="remove(product)">Excluir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="px-2 pb-2"><PaginationNav :meta="result.meta" @change="load" /></div>
    </div>
  </div>
</template>
