<script setup lang="ts">
import type { AddressFields } from '@/types'
import { formatPostalCode } from '@/utils/postalCode'

defineProps<{ address: AddressFields }>()
</script>

<template>
  <address class="space-y-0.5 text-sm not-italic text-slate-600">
    <p class="font-medium text-slate-900">{{ address.recipient_name }}</p>
    <p>
      {{ address.street }}, {{ address.number }}<template v-if="address.complement"> - {{ address.complement }}</template>
    </p>
    <p>{{ address.district }}</p>
    <p>{{ address.city }}/{{ address.state }} · CEP {{ formatPostalCode(address.postal_code) }}</p>
  </address>
</template>
