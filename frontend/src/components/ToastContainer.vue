<script setup lang="ts">
import { useNotificationStore } from '@/stores/notifications'

const store = useNotificationStore()

const styles = {
  success: 'border-green-200 bg-green-50 text-green-800',
  error: 'border-red-200 bg-red-50 text-red-800',
  info: 'border-slate-200 bg-white text-slate-800',
}
</script>

<template>
  <div class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite">
    <TransitionGroup
      enter-from-class="translate-y-2 opacity-0"
      enter-active-class="transition duration-200"
      leave-to-class="opacity-0"
      leave-active-class="transition duration-150"
    >
      <div
        v-for="notification in store.notifications"
        :key="notification.id"
        :class="['pointer-events-auto flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg', styles[notification.type]]"
        role="status"
      >
        <span>{{ notification.message }}</span>
        <button type="button" class="opacity-60 hover:opacity-100" aria-label="Fechar" @click="store.dismiss(notification.id)">✕</button>
      </div>
    </TransitionGroup>
  </div>
</template>
