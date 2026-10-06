<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import { ApiError } from '@/services/api'
import { adminStockService, type StockOperation } from '@/services/admin/stockService'
import { useNotificationStore } from '@/stores/notifications'
import type { Paginated, Stock } from '@/types'

const notifications = useNotificationStore()
const result = ref<Paginated<Stock> | null>(null)
const amounts = reactive<Record<number, number>>({})
const page = ref(1)

async function load(newPage = page.value): Promise<void> {
  page.value = newPage
  result.value = await adminStockService.list(newPage)
}

function statusOf(stock: Stock): { label: string; style: string } {
  if (stock.product.status === 'inactive') return { label: 'Inativo', style: 'bg-slate-200 text-slate-600' }
  if (stock.quantity === 0) return { label: 'Sem estoque', style: 'bg-red-100 text-red-700' }
  return { label: 'Disponível', style: 'bg-green-100 text-green-800' }
}

async function adjust(stock: Stock, operation: StockOperation): Promise<void> {
  const quantity = amounts[stock.id] ?? 1
  try {
    const updated = await adminStockService.adjust(stock.id, operation, quantity)
    stock.quantity = updated.quantity
    stock.product = updated.product
    notifications.success(`Estoque de "${stock.product.name}": ${updated.quantity} un.`)
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      notifications.error(error.errors.quantity?.[0] ?? error.message)
    }
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="page-title">Estoque</h1>
      <p class="text-sm text-slate-500">Produtos com estoque zero ficam indisponíveis automaticamente.</p>
    </div>

    <LoadingState v-if="!result" />
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Produto</th><th class="text-right">Estoque atual</th><th>Status</th><th class="text-right">Atualizar estoque</th></tr>
        </thead>
        <tbody>
          <tr v-for="stock in result.data" :key="stock.id">
            <td class="font-medium">{{ stock.product.name }}</td>
            <td class="text-right text-lg font-bold">{{ stock.quantity }}</td>
            <td><span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', statusOf(stock).style]">{{ statusOf(stock).label }}</span></td>
            <td>
              <div class="flex items-center justify-end gap-2">
                <input v-model.number="amounts[stock.id]" type="number" min="1" placeholder="1" class="input w-20" :aria-label="`Quantidade para ${stock.product.name}`" />
                <button type="button" class="btn btn-secondary btn-sm" @click="adjust(stock, 'increase')">+ Aumentar</button>
                <button type="button" class="btn btn-secondary btn-sm" @click="adjust(stock, 'decrease')">− Reduzir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="px-2 pb-2"><PaginationNav :meta="result.meta" @change="load" /></div>
    </div>
  </div>
</template>
