<script setup lang="ts">
import AddressLines from '@/components/AddressLines.vue'
import type { Order } from '@/types'
import { describeDelivery } from '@/utils/delivery'

defineProps<{ order: Pick<Order, 'status' | 'delivery'> }>()
</script>

<template>
  <section class="card space-y-3 p-5">
    <h2 class="font-semibold">Entrega</h2>
    <p class="text-sm font-medium text-indigo-700">{{ describeDelivery(order) }}</p>
    <AddressLines :address="order.delivery.address" />
  </section>
</template>
