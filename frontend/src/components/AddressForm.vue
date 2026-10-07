<script setup lang="ts">
import { reactive } from 'vue'
import FieldError from '@/components/FieldError.vue'
import type { AddressFields, AddressPayload, ValidationErrors } from '@/types'
import { BRAZILIAN_STATES } from '@/utils/states'

const props = defineProps<{
  /** The address being edited; empty for a new one. */
  initial?: AddressFields | null
  saving: boolean
  errors: ValidationErrors
  submitLabel: string
  /** Whether the form can be dismissed (the checkout keeps it open while there is no address). */
  cancellable: boolean
}>()

const emit = defineEmits<{ submit: [payload: AddressPayload]; cancel: [] }>()

const form = reactive({
  recipient_name: props.initial?.recipient_name ?? '',
  postal_code: props.initial?.postal_code ?? '',
  street: props.initial?.street ?? '',
  number: props.initial?.number ?? '',
  complement: props.initial?.complement ?? '',
  district: props.initial?.district ?? '',
  city: props.initial?.city ?? '',
  state: props.initial?.state ?? '',
})

function first(field: string): string | undefined {
  return props.errors[field]?.[0]
}

function submit(): void {
  emit('submit', { ...form, state: form.state as AddressPayload['state'] })
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div>
      <label for="address-recipient" class="label">Destinatário</label>
      <input id="address-recipient" v-model="form.recipient_name" class="input" autocomplete="name" />
      <FieldError :message="first('recipient_name')" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <div>
        <label for="address-postal-code" class="label">CEP</label>
        <input
          id="address-postal-code"
          v-model="form.postal_code"
          class="input"
          inputmode="numeric"
          maxlength="9"
          placeholder="00000-000"
          autocomplete="postal-code"
        />
        <FieldError :message="first('postal_code')" />
      </div>
      <div class="sm:col-span-2">
        <label for="address-street" class="label">Logradouro</label>
        <input id="address-street" v-model="form.street" class="input" autocomplete="address-line1" />
        <FieldError :message="first('street')" />
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <div>
        <label for="address-number" class="label">Número</label>
        <input id="address-number" v-model="form.number" class="input" />
        <FieldError :message="first('number')" />
      </div>
      <div class="sm:col-span-2">
        <label for="address-complement" class="label">Complemento (opcional)</label>
        <input id="address-complement" v-model="form.complement" class="input" autocomplete="address-line2" />
        <FieldError :message="first('complement')" />
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <div>
        <label for="address-district" class="label">Bairro</label>
        <input id="address-district" v-model="form.district" class="input" />
        <FieldError :message="first('district')" />
      </div>
      <div>
        <label for="address-city" class="label">Cidade</label>
        <input id="address-city" v-model="form.city" class="input" autocomplete="address-level2" />
        <FieldError :message="first('city')" />
      </div>
      <div>
        <label for="address-state" class="label">UF</label>
        <select id="address-state" v-model="form.state" class="input">
          <option value="" disabled>Selecione</option>
          <option v-for="state in BRAZILIAN_STATES" :key="state" :value="state">{{ state }}</option>
        </select>
        <FieldError :message="first('state')" />
      </div>
    </div>

    <div class="flex justify-end gap-3">
      <button v-if="cancellable" type="button" class="btn btn-secondary" :disabled="saving" @click="emit('cancel')">Cancelar</button>
      <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : submitLabel }}</button>
    </div>
  </form>
</template>
