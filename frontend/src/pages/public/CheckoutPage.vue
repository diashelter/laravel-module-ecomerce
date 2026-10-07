<script setup lang="ts">
import { onMounted } from 'vue'
import AddressForm from '@/components/AddressForm.vue'
import AddressLines from '@/components/AddressLines.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import { useCheckout } from '@/composables/useCheckout'
import { useCartStore } from '@/stores/cart'
import { formatCents } from '@/utils/money'

const cart = useCartStore()
const checkout = useCheckout()
const { book } = checkout

onMounted(checkout.init)
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <h1 class="page-title">Checkout</h1>

    <EmptyState v-if="cart.isEmpty" title="Seu carrinho está vazio">
      <RouterLink :to="{ name: 'products' }" class="btn btn-primary">Ver produtos</RouterLink>
    </EmptyState>

    <LoadingState v-else-if="checkout.loadingCart.value && !checkout.validation.value" text="Validando carrinho..." />

    <template v-else-if="checkout.validation.value">
      <div v-if="checkout.conflictMessage.value" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        {{ checkout.conflictMessage.value }} Revise os itens abaixo.
      </div>

      <div class="card overflow-x-auto">
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
            <tr v-for="line in checkout.validation.value.items" :key="line.product_id" :class="line.problem && 'bg-red-50'">
              <td>
                <p class="font-medium">{{ line.name ?? `Produto #${line.product_id}` }}</p>
                <p v-if="line.problem" class="text-xs font-semibold text-red-600">{{ line.problem }}</p>
              </td>
              <td class="text-right">{{ line.quantity }}</td>
              <td class="text-right">{{ formatCents(line.unit_price_cents) }}</td>
              <td class="text-right">{{ formatCents(line.subtotal_cents) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!checkout.validation.value.is_valid" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        Alguns itens estão indisponíveis ou acima do estoque. Ajuste o carrinho para continuar.
      </div>

      <section class="card space-y-4 p-6">
        <h2 class="font-semibold">Endereço de entrega</h2>

        <p v-if="checkout.addressError.value" class="rounded-lg bg-red-50 p-3 text-sm font-medium text-red-700" role="alert">
          {{ checkout.addressError.value }}
        </p>

        <div v-if="book.loadError.value" class="space-y-3 text-sm text-red-600">
          <p>{{ book.loadError.value }}</p>
          <button type="button" class="btn btn-secondary btn-sm" @click="checkout.loadAddresses">Tentar novamente</button>
        </div>
        <LoadingState v-else-if="book.loading.value && book.isEmpty.value" text="Carregando endereços..." />

        <template v-else>
          <fieldset v-if="!book.isEmpty.value" class="space-y-3">
            <legend class="sr-only">Escolha o endereço de entrega</legend>
            <label
              v-for="address in book.addresses.value"
              :key="address.id"
              class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/40"
            >
              <input
                type="radio"
                name="delivery-address"
                class="mt-1"
                :value="address.id"
                :checked="checkout.selectedAddressId.value === address.id"
                @change="checkout.selectAddress(address.id)"
              />
              <AddressLines :address="address" />
            </label>
          </fieldset>

          <button
            v-if="!book.isEmpty.value && !checkout.formOpen.value"
            type="button"
            class="btn btn-secondary btn-sm"
            @click="checkout.openForm()"
          >
            Adicionar outro endereço
          </button>

          <div v-if="checkout.formOpen.value" class="space-y-4">
            <p v-if="book.isEmpty.value" class="text-sm text-slate-500">Você ainda não tem endereço cadastrado. Informe onde quer receber o pedido.</p>
            <p v-if="book.formMessage.value" class="rounded-lg bg-red-50 p-3 text-sm font-medium text-red-700" role="alert">
              {{ book.formMessage.value }}
            </p>
            <AddressForm
              :saving="book.saving.value"
              :errors="book.fieldErrors.value"
              :cancellable="!book.isEmpty.value"
              submit-label="Salvar e usar este endereço"
              @submit="checkout.saveAddress"
              @cancel="checkout.closeForm"
            />
          </div>
        </template>
      </section>

      <section class="card space-y-2 p-6 text-sm">
        <div class="flex justify-between">
          <span class="text-slate-600">Subtotal</span>
          <span>{{ formatCents(checkout.subtotalCents.value) }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-600">Frete</span>
          <span>{{ checkout.quoteStatus.value === 'loading' ? 'Calculando...' : formatCents(checkout.shippingCents.value) }}</span>
        </div>
        <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-bold">
          <span>Total</span>
          <span class="text-lg">{{ formatCents(checkout.totalCents.value) }}</span>
        </div>
        <p v-if="checkout.deliveryText.value" class="pt-1 text-slate-500">{{ checkout.deliveryText.value }}</p>
        <div v-if="checkout.quoteMessage.value" class="flex flex-wrap items-center gap-3 pt-1 text-red-600">
          <span>{{ checkout.quoteMessage.value }}</span>
          <button type="button" class="btn btn-secondary btn-sm" @click="checkout.retryQuote">Tentar novamente</button>
        </div>
      </section>

      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <RouterLink :to="{ name: 'cart' }" class="btn btn-secondary">Voltar ao carrinho</RouterLink>
        <button type="button" class="btn btn-primary" :disabled="!checkout.canConfirm.value" @click="checkout.confirm">
          {{ checkout.submitting.value ? 'Confirmando...' : 'Confirmar compra' }}
        </button>
      </div>
    </template>
  </div>
</template>
