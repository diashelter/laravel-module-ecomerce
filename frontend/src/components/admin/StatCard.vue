<script setup lang="ts">
defineProps<{ label: string; value: number | string; hint?: string }>()
</script>

<template>
  <div class="card p-5">
    <p class="text-sm text-slate-500">{{ label }}</p>
    <p class="mt-1 text-3xl font-bold text-slate-900">{{ value }}</p>
    <p v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>
  </div>
</template>
