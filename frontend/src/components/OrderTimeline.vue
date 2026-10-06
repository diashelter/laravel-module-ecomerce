<script setup lang="ts">
import type { TimelineStep } from '@/types'

defineProps<{ steps: TimelineStep[] }>()
</script>

<template>
  <ol class="grid gap-4 sm:grid-cols-4">
    <li v-for="(step, index) in steps" :key="step.status" class="flex items-center gap-3 sm:flex-col sm:text-center">
      <span
        :class="[
          'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold',
          step.completed ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-500',
        ]"
      >
        {{ step.completed ? '✓' : index + 1 }}
      </span>
      <span :class="['text-sm', step.completed ? 'font-semibold text-slate-900' : 'text-slate-500']">{{ step.label }}</span>
    </li>
  </ol>
</template>
