<template>
  <div class="flex min-h-screen items-center justify-center px-4">
    <div class="card max-w-lg px-6 py-12 text-center">
      <p class="text-5xl font-bold text-slate-300">404</p>
      <p class="mt-4 text-lg font-semibold">Página não encontrada</p>
      <RouterLink :to="{ name: 'products' }" class="btn btn-primary mt-6">Voltar para a loja</RouterLink>
    </div>
  </div>
</template>
