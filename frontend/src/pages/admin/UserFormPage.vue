<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import FieldError from '@/components/FieldError.vue'
import LoadingState from '@/components/LoadingState.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { adminUserService } from '@/services/admin/userService'
import { useNotificationStore } from '@/stores/notifications'

const props = defineProps<{ id?: number }>()

const router = useRouter()
const notifications = useNotificationStore()
const { first, handle, reset } = useFormErrors()

const isEdit = computed(() => props.id !== undefined)
const loading = ref(isEdit.value)
const saving = ref(false)

// No role field: customers created here are always "customer".
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })

onMounted(async () => {
  if (props.id === undefined) return
  const user = await adminUserService.find(props.id)
  form.name = user.name
  form.email = user.email
  loading.value = false
})

async function submit(): Promise<void> {
  saving.value = true
  reset()
  const payload = {
    name: form.name,
    email: form.email,
    ...(form.password ? { password: form.password, password_confirmation: form.password_confirmation } : {}),
  }
  try {
    const user = props.id !== undefined ? await adminUserService.update(props.id, payload) : await adminUserService.create(payload)
    notifications.success(isEdit.value ? 'Cliente atualizado.' : 'Cliente criado.')
    await router.push({ name: 'admin.users.show', params: { id: user.id } })
  } catch (error) {
    handle(error)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <LoadingState v-if="loading" />
  <div v-else class="max-w-2xl space-y-6">
    <div>
      <RouterLink :to="{ name: 'admin.users' }" class="text-sm text-indigo-600 hover:underline">← Clientes</RouterLink>
      <h1 class="page-title mt-1">{{ isEdit ? 'Editar cliente' : 'Novo cliente' }}</h1>
    </div>

    <form class="card space-y-5 p-6" novalidate @submit.prevent="submit">
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
