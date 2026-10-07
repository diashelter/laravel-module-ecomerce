<script setup lang="ts">
import { reactive, ref } from 'vue'
import FieldError from '@/components/FieldError.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { accountService } from '@/services/accountService'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'

const auth = useAuthStore()
const notifications = useNotificationStore()
const { first, handle, reset } = useFormErrors()

const form = reactive({
  name: auth.customer?.name ?? '',
  email: auth.customer?.email ?? '',
  current_password: '',
  password: '',
  password_confirmation: '',
})
const saving = ref(false)

async function submit(): Promise<void> {
  saving.value = true
  reset()
  try {
    const changingPassword = form.password !== ''
    const updated = await accountService.updateProfile({
      name: form.name,
      email: form.email,
      ...(changingPassword
        ? { current_password: form.current_password, password: form.password, password_confirmation: form.password_confirmation }
        : {}),
    })
    auth.setCustomer(updated)
    form.current_password = form.password = form.password_confirmation = ''
    notifications.success('Dados atualizados com sucesso.')
  } catch (error) {
    handle(error)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl space-y-6">
    <h1 class="page-title">Meu perfil</h1>

    <form class="card space-y-5 p-6" novalidate @submit.prevent="submit">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="name" class="label">Nome</label>
          <input id="name" v-model="form.name" class="input" autocomplete="name" />
          <FieldError :message="first('name')" />
        </div>
        <div>
          <label for="email" class="label">E-mail</label>
          <input id="email" v-model="form.email" type="email" class="input" autocomplete="email" />
          <FieldError :message="first('email')" />
        </div>
      </div>

      <fieldset class="space-y-4 border-t border-slate-100 pt-5">
        <legend class="text-sm font-semibold text-slate-700">Alterar senha (opcional)</legend>
        <div>
          <label for="current_password" class="label">Senha atual</label>
          <input id="current_password" v-model="form.current_password" type="password" class="input" autocomplete="current-password" />
          <FieldError :message="first('current_password')" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="password" class="label">Nova senha</label>
            <input id="password" v-model="form.password" type="password" class="input" autocomplete="new-password" />
            <FieldError :message="first('password')" />
          </div>
          <div>
            <label for="password_confirmation" class="label">Confirmar nova senha</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" />
          </div>
        </div>
      </fieldset>

      <div class="flex justify-end">
        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar alterações' }}</button>
      </div>
    </form>
  </div>
</template>
