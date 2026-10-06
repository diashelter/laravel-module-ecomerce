<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue'
import { useCartStore } from '@/stores/cart'
import { formatCents, formatMoney } from '@/utils/money'

const cart = useCartStore()
</script>

<template>
  <div class="space-y-6">
    <h1 class="page-title">Carrinho</h1>

    <EmptyState v-if="cart.isEmpty" title="Seu carrinho está vazio" description="Adicione produtos para continuar.">
      <RouterLink :to="{ name: 'products' }" class="btn btn-primary">Ver produtos</RouterLink>
    </EmptyState>

    <div v-else class="grid gap-6 lg:grid-cols-3">
      <ul class="card divide-y divide-slate-100 lg:col-span-2">
        <li v-for="item in cart.items" :key="item.product_id" class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
          <img :src="item.image_url ?? ''" :alt="item.name" class="h-20 w-20 rounded-lg bg-slate-100 object-cover" />
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-900">{{ item.name }}</p>
            <p class="text-sm text-slate-500">{{ formatMoney(item.price) }} cada</p>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="btn btn-secondary btn-sm" aria-label="Diminuir" @click="cart.decrement(item.product_id)">−</button>
            <span class="w-8 text-center font-semibold">{{ item.quantity }}</span>
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              aria-label="Aumentar"
              :disabled="item.quantity >= item.max_quantity"
              @click="cart.increment(item.product_id)"
            >
              +
            </button>
          </div>
          <p class="w-28 text-right font-semibold">{{ formatCents(cart.subtotalCents(item)) }}</p>
          <button type="button" class="text-sm text-red-600 hover:underline" @click="cart.remove(item.product_id)">Remover</button>
        </li>
      </ul>

      <aside class="card h-fit space-y-4 p-5">
        <div class="flex justify-between text-lg font-bold">
          <span>Total</span>
          <span>{{ formatCents(cart.totalCents) }}</span>
        </div>
        <p class="text-xs text-slate-500">Preços e estoque serão confirmados no checkout.</p>
        <RouterLink :to="{ name: 'checkout' }" class="btn btn-primary w-full">Finalizar compra</RouterLink>
        <button type="button" class="btn btn-secondary w-full" @click="cart.clear()">Limpar carrinho</button>
      </aside>
    </div>
  </div>
</template>
