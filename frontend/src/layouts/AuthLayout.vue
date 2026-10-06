<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <RouterLink :to="{ name: 'products' }" class="mb-8">
      <AppLogo />
    </RouterLink>
    <div class="card w-full max-w-md p-6 sm:p-8">
      <RouterView />
    </div>
  </div>
</template>
