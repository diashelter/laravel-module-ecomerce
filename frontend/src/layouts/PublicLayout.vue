<script setup lang="ts">
import AppNavbar from '@/components/AppNavbar.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <AppNavbar />
    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
      <RouterView />
    </main>
    <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-500">
      Loja Demo · projeto de estudo (Laravel + Vue)
    </footer>
  </div>
</template>
