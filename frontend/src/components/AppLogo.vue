<script setup lang="ts">
defineProps<{ dark?: boolean }>()
</script>

<template>
  <span class="inline-flex items-center gap-2 text-lg font-bold" :class="dark ? 'text-white' : 'text-slate-900'">
    <img src="/favicon.svg" alt="" class="h-7 w-7" />
    Loja Demo
  </span>
</template>
