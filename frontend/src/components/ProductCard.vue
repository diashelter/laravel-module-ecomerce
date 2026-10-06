<script setup lang="ts">
import { computed } from 'vue'
import { useCartStore } from '@/stores/cart'
import { useNotificationStore } from '@/stores/notifications'
import type { Product } from '@/types'
import { formatMoney } from '@/utils/money'

const props = defineProps<{ product: Product }>()

const cart = useCartStore()
const notifications = useNotificationStore()

// The backend decides availability (active AND stock > 0); the card only reflects it.
const unavailableLabel = computed(() => (props.product.status === 'inactive' ? 'Indisponível' : 'Sem estoque'))

function addToCart(): void {
  if (cart.add(props.product)) notifications.success(`${props.product.name} adicionado ao carrinho.`)
}
</script>

<template>
  <article
    :class="['card flex flex-col overflow-hidden transition', product.is_available ? 'hover:shadow-md' : 'cursor-not-allowed opacity-50 grayscale']"
    :aria-disabled="!product.is_available"
  >
    <component
      :is="product.is_available ? 'RouterLink' : 'div'"
      :to="product.is_available ? { name: 'product', params: { id: product.id } } : undefined"
      class="relative block"
    >
      <img :src="product.image_url ?? ''" :alt="product.name" class="aspect-square w-full bg-slate-100 object-cover" loading="lazy" />
      <span v-if="!product.is_available" class="absolute top-3 left-3 rounded-full bg-slate-900/80 px-3 py-1 text-xs font-semibold text-white">
        {{ unavailableLabel }}
      </span>
    </component>

    <div class="flex flex-1 flex-col gap-2 p-4">
      <div class="flex flex-wrap gap-1">
        <span v-for="category in product.categories" :key="category.id" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
          {{ category.name }}
        </span>
      </div>
      <component
        :is="product.is_available ? 'RouterLink' : 'h3'"
        :to="product.is_available ? { name: 'product', params: { id: product.id } } : undefined"
        class="line-clamp-2 font-semibold text-slate-900"
      >
        {{ product.name }}
      </component>
      <div class="mt-auto flex items-center justify-between gap-2 pt-2">
        <span class="text-lg font-bold text-slate-900">{{ formatMoney(product.price) }}</span>
        <button type="button" class="btn btn-primary btn-sm" :disabled="!product.is_available" @click="addToCart">
          Adicionar
        </button>
      </div>
    </div>
  </article>
</template>
