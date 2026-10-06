<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LoadingState from '@/components/LoadingState.vue'
import { ApiError } from '@/services/api'
import { productService } from '@/services/productService'
import { useCartStore } from '@/stores/cart'
import { useNotificationStore } from '@/stores/notifications'
import type { Product } from '@/types'
import { formatMoney } from '@/utils/money'

const props = defineProps<{ id: number }>()

const cart = useCartStore()
const notifications = useNotificationStore()

const product = ref<Product | null>(null)
const loading = ref(true)
const unavailableMessage = ref<string | null>(null)
const quantity = ref(1)

onMounted(async () => {
  try {
    product.value = await productService.find(props.id)
  } catch (error) {
    // The API answers 404 for missing, inactive or out of stock products.
    unavailableMessage.value = error instanceof ApiError && error.status === 404 ? error.message : 'Não foi possível carregar o produto.'
  } finally {
    loading.value = false
  }
})

function addToCart(): void {
  if (product.value && cart.add(product.value, quantity.value)) {
    notifications.success(`${product.value.name} adicionado ao carrinho.`)
  }
}
</script>

<template>
  <LoadingState v-if="loading" />

  <div v-else-if="unavailableMessage" class="card mx-auto max-w-lg px-6 py-12 text-center">
    <p class="text-lg font-semibold text-slate-900">{{ unavailableMessage }}</p>
    <p class="mt-2 text-sm text-slate-500">Este produto não está disponível para compra no momento.</p>
    <RouterLink :to="{ name: 'products' }" class="btn btn-primary mt-6">Voltar para a loja</RouterLink>
  </div>

  <div v-else-if="product" class="grid gap-8 md:grid-cols-2">
    <img :src="product.image_url ?? ''" :alt="product.name" class="card aspect-square w-full object-cover" />
    <div class="space-y-5">
      <RouterLink :to="{ name: 'products' }" class="text-sm text-indigo-600 hover:underline">← Voltar aos produtos</RouterLink>
      <div class="flex flex-wrap gap-1">
        <span v-for="category in product.categories" :key="category.id" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
          {{ category.name }}
        </span>
      </div>
      <h1 class="text-3xl font-bold text-slate-900">{{ product.name }}</h1>
      <p class="text-3xl font-bold text-indigo-700">{{ formatMoney(product.price) }}</p>
      <p class="text-sm text-slate-500">{{ product.available_quantity }} unidade(s) disponível(is)</p>
      <p class="leading-relaxed whitespace-pre-line text-slate-600">{{ product.description }}</p>

      <div class="flex items-end gap-3">
        <label>
          <span class="label">Quantidade</span>
          <input v-model.number="quantity" type="number" min="1" :max="product.available_quantity" class="input w-24" />
        </label>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="quantity < 1 || quantity > product.available_quantity"
          @click="addToCart"
        >
          Adicionar ao carrinho
        </button>
      </div>
    </div>
  </div>
</template>
