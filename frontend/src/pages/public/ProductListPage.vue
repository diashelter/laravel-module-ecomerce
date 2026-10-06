<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import ProductCard from '@/components/ProductCard.vue'
import { categoryService } from '@/services/categoryService'
import { productService, type ProductSort } from '@/services/productService'
import type { Category, Paginated, Product } from '@/types'

const route = useRoute()
const router = useRouter()

const categories = ref<Category[]>([])
const result = ref<Paginated<Product> | null>(null)
const loading = ref(false)

const sortOptions: { value: ProductSort; label: string }[] = [
  { value: 'name', label: 'Ordem alfabética (A-Z)' },
  { value: 'price_asc', label: 'Menor preço' },
  { value: 'price_desc', label: 'Maior preço' },
]

// Filters live in the URL, so they survive reloads and can be shared.
const filters = computed(() => ({
  category: typeof route.query.category === 'string' ? route.query.category : '',
  sort: (typeof route.query.sort === 'string' ? route.query.sort : 'name') as ProductSort,
  page: Number(route.query.page ?? 1) || 1,
}))

function updateQuery(changes: Partial<{ category: string; sort: ProductSort; page: number }>): void {
  const next = { ...filters.value, page: 1, ...changes }
  void router.push({
    query: {
      ...(next.category ? { category: next.category } : {}),
      ...(next.sort !== 'name' ? { sort: next.sort } : {}),
      ...(next.page > 1 ? { page: String(next.page) } : {}),
    },
  })
}

// Filtering, sorting and pagination are done by the backend.
async function load(): Promise<void> {
  loading.value = true
  try {
    result.value = await productService.list({
      category: filters.value.category || undefined,
      sort: filters.value.sort,
      page: filters.value.page,
    })
  } finally {
    loading.value = false
  }
}

watch(() => route.query, load)

onMounted(async () => {
  await Promise.all([load(), categoryService.list().then((list) => (categories.value = list))])
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="page-title">Produtos</h1>
        <p class="text-sm text-slate-500">Produtos indisponíveis aparecem esmaecidos.</p>
      </div>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label>
          <span class="label">Categoria</span>
          <select class="input" :value="filters.category" @change="updateQuery({ category: ($event.target as HTMLSelectElement).value })">
            <option value="">Todas as categorias</option>
            <option v-for="category in categories" :key="category.id" :value="category.slug">{{ category.name }}</option>
          </select>
        </label>
        <label>
          <span class="label">Ordenar por</span>
          <select class="input" :value="filters.sort" @change="updateQuery({ sort: ($event.target as HTMLSelectElement).value as ProductSort })">
            <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </label>
      </div>
    </div>

    <LoadingState v-if="loading && !result" />
    <template v-else-if="result">
      <EmptyState v-if="result.data.length === 0" title="Nenhum produto encontrado" description="Tente outra categoria." />
      <div v-else :class="['grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', loading && 'opacity-60']">
        <ProductCard v-for="product in result.data" :key="product.id" :product="product" />
      </div>
      <PaginationNav :meta="result.meta" @change="(page) => updateQuery({ page, category: filters.category, sort: filters.sort })" />
    </template>
  </div>
</template>
