<template>
  <div class="card mx-auto max-w-lg px-6 py-12 text-center">
    <p class="text-5xl font-bold text-slate-300">403</p>
    <p class="mt-4 text-lg font-semibold">Acesso negado</p>
    <p class="mt-1 text-sm text-slate-500">Você não tem permissão para acessar esta página.</p>
    <RouterLink :to="{ name: 'products' }" class="btn btn-primary mt-6">Voltar para a loja</RouterLink>
  </div>
</template>
