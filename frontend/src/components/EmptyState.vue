<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="card px-6 py-12 text-center">
    <p class="font-semibold text-slate-800">{{ title }}</p>
    <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
    <div class="mt-4"><slot /></div>
  </div>
</template>
