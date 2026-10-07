<script setup lang="ts">
import type { OrderItem } from '@/types'
import { formatCents } from '@/utils/money'

defineProps<{ items: OrderItem[]; itemsTotalCents: number; shippingCents: number; totalCents: number }>()
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
          <td class="text-right">{{ formatCents(item.unit_price_cents) }}</td>
          <td class="text-right">{{ formatCents(item.subtotal_cents) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="border-t-2 border-slate-200">
          <td colspan="3" class="px-4 py-2 text-right text-slate-600">Subtotal</td>
          <td class="px-4 py-2 text-right">{{ formatCents(itemsTotalCents) }}</td>
        </tr>
        <tr>
          <td colspan="3" class="px-4 py-2 text-right text-slate-600">Frete</td>
          <td class="px-4 py-2 text-right">{{ formatCents(shippingCents) }}</td>
        </tr>
        <tr>
          <td colspan="3" class="px-4 py-3 text-right font-semibold">Total</td>
          <td class="px-4 py-3 text-right text-lg font-bold">{{ formatCents(totalCents) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>
