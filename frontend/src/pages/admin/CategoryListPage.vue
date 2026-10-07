<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import FieldError from '@/components/FieldError.vue'
import LoadingState from '@/components/LoadingState.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { ApiError } from '@/services/api'
import { adminCategoryService } from '@/services/admin/categoryService'
import { useNotificationStore } from '@/stores/notifications'
import { useStaffStore } from '@/stores/staff'
import type { Category } from '@/types'
import { slugify } from '@/utils/slug'

const notifications = useNotificationStore()
const staff = useStaffStore()
const { first, handle, reset } = useFormErrors()

const categories = ref<Category[] | null>(null)
const editing = ref<Category | null>(null)
const name = ref('')
const saving = ref(false)

const slugPreview = computed(() => slugify(name.value))

async function load(): Promise<void> {
  categories.value = await adminCategoryService.list()
}

function edit(category: Category): void {
  editing.value = category
  name.value = category.name
  reset()
}

function cancel(): void {
  editing.value = null
  name.value = ''
  reset()
}

async function submit(): Promise<void> {
  saving.value = true
  reset()
  try {
    if (editing.value) {
      await adminCategoryService.update(editing.value.id, name.value)
      notifications.success('Categoria atualizada.')
    } else {
      await adminCategoryService.create(name.value)
      notifications.success('Categoria criada.')
    }
    cancel()
    await load()
  } catch (error) {
    handle(error)
  } finally {
    saving.value = false
  }
}

async function remove(category: Category): Promise<void> {
  if (!window.confirm(`Excluir a categoria "${category.name}"?`)) return
  try {
    await adminCategoryService.remove(category.id)
    notifications.success('Categoria excluída.')
    await load()
  } catch (error) {
    // 409: the category still has products.
    if (error instanceof ApiError && error.status === 409) notifications.error(error.message)
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <h1 class="page-title">Categorias</h1>

    <form class="card grid gap-4 p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-start" novalidate @submit.prevent="submit">
      <div>
        <label for="name" class="label">{{ editing ? 'Editar nome' : 'Nova categoria' }}</label>
        <input id="name" v-model="name" class="input" placeholder="Ex.: Eletrônicos" />
        <FieldError :message="first('name')" />
      </div>
      <div>
        <span class="label">Slug (gerado automaticamente)</span>
        <p class="input bg-slate-50 text-slate-500">{{ slugPreview || '—' }}</p>
        <FieldError :message="first('slug')" />
      </div>
      <div class="flex gap-2 sm:pt-6">
        <button type="submit" class="btn btn-primary" :disabled="saving">{{ editing ? 'Salvar' : 'Criar' }}</button>
        <button v-if="editing" type="button" class="btn btn-secondary" @click="cancel">Cancelar</button>
      </div>
    </form>

    <LoadingState v-if="!categories" />
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Nome</th><th>Slug</th><th class="text-right">Produtos</th><th class="text-right">Ações</th></tr>
        </thead>
        <tbody>
          <tr v-for="category in categories" :key="category.id">
            <td class="font-medium">{{ category.name }}</td>
            <td class="font-mono text-xs text-slate-500">{{ category.slug }}</td>
            <td class="text-right">{{ category.products_count }}</td>
            <td>
              <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary btn-sm" @click="edit(category)">Editar</button>
                <button v-if="staff.canDelete" type="button" class="btn btn-danger btn-sm" @click="remove(category)">Excluir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
