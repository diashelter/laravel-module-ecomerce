<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import FieldError from '@/components/FieldError.vue'
import LoadingState from '@/components/LoadingState.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { adminCategoryService } from '@/services/admin/categoryService'
import { adminProductService } from '@/services/admin/productService'
import { adminStockService, type StockOperation } from '@/services/admin/stockService'
import { useNotificationStore } from '@/stores/notifications'
import type { Category, Product, ProductStatus } from '@/types'
import { centsToReaisInput, INVALID_PRICE_MESSAGE, parseReaisInput } from '@/utils/money'

const props = defineProps<{ id?: number }>()

const router = useRouter()
const notifications = useNotificationStore()
const { first, handle, reset } = useFormErrors()
const stockErrors = useFormErrors()

const isEdit = computed(() => props.id !== undefined)
const categories = ref<Category[]>([])
const product = ref<Product | null>(null)
const loading = ref(true)
const saving = ref(false)
const priceError = ref<string | null>(null)

const form = reactive({
  name: '',
  price: '',
  description: '',
  image_url: '',
  status: 'active' as ProductStatus,
  category_ids: [] as number[],
  stock_quantity: 0,
})

const stockAmount = ref(1)

onMounted(async () => {
  categories.value = await adminCategoryService.list()
  if (props.id !== undefined) {
    product.value = await adminProductService.find(props.id)
    Object.assign(form, {
      name: product.value.name,
      price: centsToReaisInput(product.value.price_cents),
      description: product.value.description,
      image_url: product.value.image_url ?? '',
      status: product.value.status,
      category_ids: product.value.categories.map((category) => category.id),
    })
  }
  loading.value = false
})

async function submit(): Promise<void> {
  reset()
  priceError.value = null
  const priceCents = parseReaisInput(form.price)
  if (priceCents === null) {
    priceError.value = INVALID_PRICE_MESSAGE
    return
  }

  saving.value = true
  const payload = {
    name: form.name,
    price_cents: priceCents,
    description: form.description,
    image_url: form.image_url || null,
    status: form.status,
    category_ids: form.category_ids,
  }
  try {
    if (props.id !== undefined) {
      product.value = await adminProductService.update(props.id, payload)
      notifications.success('Produto atualizado.')
    } else {
      // The initial stock is only sent on creation; afterwards it has its own endpoint.
      const created = await adminProductService.create({ ...payload, stock_quantity: form.stock_quantity })
      notifications.success('Produto criado.')
      await router.push({ name: 'admin.products.edit', params: { id: created.id } })
    }
  } catch (error) {
    handle(error)
  } finally {
    saving.value = false
  }
}

/** Stock is a separate entity (Product 1:1 Stock) with its own endpoint. */
async function adjustStock(operation: StockOperation): Promise<void> {
  if (!product.value?.stock) return
  stockErrors.reset()
  try {
    const stock = await adminStockService.adjust(product.value.stock.id, operation, stockAmount.value)
    product.value.stock.quantity = stock.quantity
    notifications.success('Estoque atualizado.')
  } catch (error) {
    stockErrors.handle(error)
    if (stockErrors.errors.value.quantity === undefined && error instanceof Error) notifications.error(error.message)
  }
}
</script>

<template>
  <LoadingState v-if="loading" />
  <div v-else class="max-w-3xl space-y-6">
    <div>
      <RouterLink :to="{ name: 'admin.products' }" class="text-sm text-indigo-600 hover:underline">← Produtos</RouterLink>
      <h1 class="page-title mt-1">{{ isEdit ? 'Editar produto' : 'Novo produto' }}</h1>
    </div>

    <form class="card space-y-5 p-6" novalidate @submit.prevent="submit">
      <div>
        <label for="name" class="label">Nome</label>
        <input id="name" v-model="form.name" class="input" />
        <FieldError :message="first('name')" />
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="price" class="label">Preço (R$)</label>
          <input id="price" v-model="form.price" inputmode="decimal" placeholder="199,90" class="input" />
          <FieldError :message="priceError ?? first('price_cents')" />
        </div>
        <div>
          <label for="status" class="label">Status</label>
          <select id="status" v-model="form.status" class="input">
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
          <FieldError :message="first('status')" />
        </div>
      </div>
      <div>
        <label for="description" class="label">Descrição</label>
        <textarea id="description" v-model="form.description" rows="4" class="input" />
        <FieldError :message="first('description')" />
      </div>
      <div>
        <label for="image_url" class="label">URL da imagem (opcional)</label>
        <input id="image_url" v-model="form.image_url" type="url" placeholder="https://picsum.photos/seed/meu-produto/600/600" class="input" />
        <p class="mt-1 text-xs text-slate-500">Se vazio, uma imagem fake do picsum.photos é gerada automaticamente.</p>
        <FieldError :message="first('image_url')" />
      </div>
      <fieldset>
        <legend class="label">Categorias</legend>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
          <label v-for="category in categories" :key="category.id" class="flex items-center gap-2 text-sm">
            <input v-model="form.category_ids" type="checkbox" :value="category.id" class="rounded border-slate-300" />
            {{ category.name }}
          </label>
        </div>
        <FieldError :message="first('category_ids')" />
      </fieldset>
      <div v-if="!isEdit">
        <label for="stock_quantity" class="label">Estoque inicial</label>
        <input id="stock_quantity" v-model.number="form.stock_quantity" type="number" min="0" class="input w-40" />
        <FieldError :message="first('stock_quantity')" />
      </div>
      <div class="flex justify-end">
        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar' }}</button>
      </div>
    </form>

    <section v-if="isEdit && product?.stock" class="card space-y-4 p-6">
      <div class="flex items-center justify-between">
        <h2 class="font-semibold">Estoque</h2>
        <p class="text-sm">Atual: <span class="text-2xl font-bold">{{ product.stock.quantity }}</span> un.</p>
      </div>
      <p class="text-xs text-slate-500">O estoque é uma entidade separada do produto e é alterado por um endpoint próprio.</p>
      <div class="flex flex-wrap items-end gap-3">
        <label>
          <span class="label">Quantidade</span>
          <input v-model.number="stockAmount" type="number" min="1" class="input w-32" />
        </label>
        <button type="button" class="btn btn-secondary" @click="adjustStock('increase')">+ Aumentar</button>
        <button type="button" class="btn btn-secondary" @click="adjustStock('decrease')">− Reduzir</button>
      </div>
      <FieldError :message="stockErrors.first('quantity')" />
    </section>
  </div>
</template>
