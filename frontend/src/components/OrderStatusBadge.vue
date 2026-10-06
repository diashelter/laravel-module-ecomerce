<script setup lang="ts">
import type { OrderStatus } from '@/types'

defineProps<{ status: OrderStatus; label: string }>()

const styles: Record<OrderStatus, string> = {
  placed: 'bg-slate-100 text-slate-700',
  awaiting_payment: 'bg-amber-100 text-amber-800',
  payment_approved: 'bg-blue-100 text-blue-800',
  delivered: 'bg-green-100 text-green-800',
}
</script>

<template>
  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', styles[status]]">{{ label }}</span>
</template>
