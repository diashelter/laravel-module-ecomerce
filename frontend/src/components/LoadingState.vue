<script setup lang="ts">
defineProps<{ text?: string }>()
</script>

<template>
  <div class="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
    <span class="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" />
    {{ text ?? 'Carregando...' }}
  </div>
</template>
