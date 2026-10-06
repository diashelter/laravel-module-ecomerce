<script setup lang="ts">
import type { PaginationMeta } from '@/types'

defineProps<{ meta: PaginationMeta }>()
const emit = defineEmits<{ change: [page: number] }>()
</script>

<template>
  <nav v-if="meta.last_page > 1" class="flex items-center justify-between gap-4 pt-4 text-sm" aria-label="Paginação">
    <span class="text-slate-500">
      {{ meta.from }}–{{ meta.to }} de {{ meta.total }}
    </span>
    <div class="flex items-center gap-2">
      <button type="button" class="btn btn-secondary btn-sm" :disabled="meta.current_page <= 1" @click="emit('change', meta.current_page - 1)">
        Anterior
      </button>
      <span class="text-slate-600">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button type="button" class="btn btn-secondary btn-sm" :disabled="meta.current_page >= meta.last_page" @click="emit('change', meta.current_page + 1)">
        Próxima
      </button>
    </div>
  </nav>
</template>
