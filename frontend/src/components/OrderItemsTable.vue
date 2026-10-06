<script setup lang="ts">
import type { OrderItem } from '@/types'
import { formatMoney } from '@/utils/money'

defineProps<{ items: OrderItem[]; total: string }>()
</script>

<template>
  <div class="overflow-x-auto">
    <table class="table">
      <thead>
        <tr>
          <th>Produto</th>
          <th class="text-right">Qtd.</th>
          <th class="text-right">Preço unitário</th>
          <th class="text-right">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td class="font-medium">{{ item.product_name }}</td>
          <td class="text-right">{{ item.quantity }}</td>
          <td class="text-right">{{ formatMoney(item.unit_price) }}</td>
          <td class="text-right">{{ formatMoney(item.subtotal) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="border-t-2 border-slate-200">
          <td colspan="3" class="px-4 py-3 text-right font-semibold">Total</td>
          <td class="px-4 py-3 text-right text-lg font-bold">{{ formatMoney(total) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>
