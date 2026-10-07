<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AddressForm from '@/components/AddressForm.vue'
import AddressLines from '@/components/AddressLines.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { useAddressBook } from '@/composables/useAddressBook'
import { useNotificationStore } from '@/stores/notifications'
import type { AddressPayload, CustomerAddress } from '@/types'

const notifications = useNotificationStore()
const book = useAddressBook()

const formOpen = ref(false)
const editing = ref<CustomerAddress | null>(null)

function openForm(address: CustomerAddress | null = null): void {
  book.clearFormErrors()
  editing.value = address
  formOpen.value = true
}

function closeForm(): void {
  book.clearFormErrors()
  formOpen.value = false
  editing.value = null
}

async function submit(payload: AddressPayload): Promise<void> {
  const wasEditing = editing.value !== null
  const saved = await book.save(payload, editing.value?.id)
  if (saved === null) return

  notifications.success(wasEditing ? 'Endereço atualizado com sucesso.' : 'Endereço cadastrado com sucesso.')
  closeForm()
}

async function remove(address: CustomerAddress): Promise<void> {
  if (await book.remove(address)) notifications.success('Endereço excluído.')
}

onMounted(book.load)
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="page-title">Endereços</h1>
      <button v-if="!book.isEmpty.value && !formOpen" type="button" class="btn btn-primary" @click="openForm()">Novo endereço</button>
    </div>

    <p v-if="book.loadError.value" class="card p-6 text-center text-red-600">{{ book.loadError.value }}</p>
    <LoadingState v-else-if="book.loading.value && book.isEmpty.value" />

    <template v-else>
      <section v-if="formOpen" class="card space-y-4 p-6">
        <h2 class="font-semibold">{{ editing ? 'Editar endereço' : 'Novo endereço' }}</h2>
        <p v-if="book.formMessage.value" class="rounded-lg bg-red-50 p-3 text-sm font-medium text-red-700" role="alert">
          {{ book.formMessage.value }}
        </p>
        <AddressForm
          :key="editing?.id ?? 'new'"
          :initial="editing"
          :saving="book.saving.value"
          :errors="book.fieldErrors.value"
          :cancellable="true"
          submit-label="Salvar endereço"
          @submit="submit"
          @cancel="closeForm"
        />
      </section>

      <EmptyState v-if="book.isEmpty.value && !formOpen" title="Nenhum endereço cadastrado">
        <button type="button" class="btn btn-primary" @click="openForm()">Cadastrar endereço</button>
      </EmptyState>

      <ul v-else class="space-y-3">
        <li v-for="address in book.addresses.value" :key="address.id" class="card flex flex-wrap items-start justify-between gap-4 p-5">
          <AddressLines :address="address" />
          <div class="flex gap-2">
            <button type="button" class="btn btn-secondary btn-sm" @click="openForm(address)">Editar</button>
            <button type="button" class="btn btn-danger btn-sm" @click="remove(address)">Excluir</button>
          </div>
        </li>
      </ul>
    </template>
  </div>
</template>
