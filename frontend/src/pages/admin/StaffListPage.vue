<script setup lang="ts">
import { onMounted, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationNav from '@/components/PaginationNav.vue'
import { ApiError } from '@/services/api'
import { adminStaffService } from '@/services/admin/staffService'
import { useNotificationStore } from '@/stores/notifications'
import { useStaffStore } from '@/stores/staff'
import type { Paginated, StaffMember } from '@/types'
import { formatDate } from '@/utils/date'

const notifications = useNotificationStore()
const staff = useStaffStore()
const result = ref<Paginated<StaffMember> | null>(null)

async function load(page = 1): Promise<void> {
  result.value = await adminStaffService.list(page)
}

async function remove(member: StaffMember): Promise<void> {
  if (!window.confirm(`Remover "${member.name}" da equipe?`)) return
  try {
    await adminStaffService.remove(member.id)
    notifications.success('Usuário removido.')
    await load(result.value?.meta.current_page)
  } catch (error) {
    // 409: an admin cannot remove their own account.
    if (error instanceof ApiError && error.status === 409) notifications.error(error.message)
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <h1 class="page-title">Usuários</h1>
      <RouterLink :to="{ name: 'admin.users.create' }" class="btn btn-primary">Novo usuário</RouterLink>
    </div>

    <LoadingState v-if="!result" />
    <EmptyState v-else-if="result.data.length === 0" title="Nenhum usuário" />
    <div v-else class="card overflow-x-auto p-2">
      <table class="table">
        <thead>
          <tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Cadastro</th><th class="text-right">Ações</th></tr>
        </thead>
        <tbody>
          <tr v-for="member in result.data" :key="member.id">
            <td class="font-medium">{{ member.name }}</td>
            <td>{{ member.email }}</td>
            <td>
              <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', member.role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-700']">
                {{ member.role_label }}
              </span>
            </td>
            <td>{{ formatDate(member.created_at) }}</td>
            <td>
              <div class="flex justify-end gap-2">
                <RouterLink :to="{ name: 'admin.users.edit', params: { id: member.id } }" class="btn btn-secondary btn-sm">Editar</RouterLink>
                <button v-if="member.id !== staff.member?.id" type="button" class="btn btn-danger btn-sm" @click="remove(member)">Remover</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="px-2 pb-2"><PaginationNav :meta="result.meta" @change="load" /></div>
    </div>
  </div>
</template>
