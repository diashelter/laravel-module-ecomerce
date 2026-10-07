<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import FieldError from '@/components/FieldError.vue'
import LoadingState from '@/components/LoadingState.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { ApiError } from '@/services/api'
import { adminStaffService } from '@/services/admin/staffService'
import { useNotificationStore } from '@/stores/notifications'
import type { StaffRole } from '@/types'

const props = defineProps<{ id?: number }>()

const router = useRouter()
const notifications = useNotificationStore()
const { first, handle, reset } = useFormErrors()

const isEdit = computed(() => props.id !== undefined)
const loading = ref(isEdit.value)
const saving = ref(false)
const businessError = ref<string | null>(null)

const form = reactive({ name: '', email: '', role: 'support' as StaffRole, password: '', password_confirmation: '' })

onMounted(async () => {
  if (props.id === undefined) return
  const member = await adminStaffService.find(props.id)
  form.name = member.name
  form.email = member.email
  form.role = member.role
  loading.value = false
})

async function submit(): Promise<void> {
  saving.value = true
  reset()
  businessError.value = null
  const payload = {
    name: form.name,
    email: form.email,
    role: form.role,
    ...(form.password ? { password: form.password, password_confirmation: form.password_confirmation } : {}),
  }
  try {
    if (props.id !== undefined) {
      await adminStaffService.update(props.id, payload)
    } else {
      await adminStaffService.create(payload)
    }
    notifications.success(isEdit.value ? 'Usuário atualizado.' : 'Usuário criado.')
    await router.push({ name: 'admin.users' })
  } catch (error) {
    // 409: an admin cannot change their own role.
    if (error instanceof ApiError && error.status === 409) {
      businessError.value = error.message
    } else {
      handle(error)
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <LoadingState v-if="loading" />
  <div v-else class="max-w-2xl space-y-6">
    <div>
      <RouterLink :to="{ name: 'admin.users' }" class="text-sm text-indigo-600 hover:underline">← Usuários</RouterLink>
      <h1 class="page-title mt-1">{{ isEdit ? 'Editar usuário' : 'Novo usuário' }}</h1>
    </div>

    <form class="card space-y-5 p-6" novalidate @submit.prevent="submit">
      <p v-if="businessError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ businessError }}</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="name" class="label">Nome</label>
          <input id="name" v-model="form.name" class="input" />
          <FieldError :message="first('name')" />
        </div>
        <div>
          <label for="email" class="label">E-mail</label>
          <input id="email" v-model="form.email" type="email" class="input" />
          <FieldError :message="first('email')" />
        </div>
        <div>
          <label for="role" class="label">Papel</label>
          <select id="role" v-model="form.role" class="input">
            <option value="support">Suporte</option>
            <option value="admin">Administrador</option>
          </select>
          <FieldError :message="first('role')" />
        </div>
        <div></div>
        <div>
          <label for="password" class="label">Senha {{ isEdit ? '(deixe em branco para manter)' : '' }}</label>
          <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="input" />
          <FieldError :message="first('password')" />
        </div>
        <div>
          <label for="password_confirmation" class="label">Confirmar senha</label>
          <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="input" />
        </div>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar' }}</button>
      </div>
    </form>
  </div>
</template>
