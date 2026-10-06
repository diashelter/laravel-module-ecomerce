<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import FieldError from '@/components/FieldError.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { first, handle, reset } = useFormErrors()

// There is no role field: the backend always registers customers.
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const submitting = ref(false)

async function submit(): Promise<void> {
  submitting.value = true
  reset()
  try {
    await auth.register(form)
    await router.push({ name: 'account' })
  } catch (error) {
    handle(error)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <h1 class="mb-1 text-2xl font-bold">Criar conta</h1>
  <p class="mb-6 text-sm text-slate-500">Cadastre-se para fazer pedidos.</p>

  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div>
      <label for="name" class="label">Nome</label>
      <input id="name" v-model="form.name" type="text" autocomplete="name" class="input" required />
      <FieldError :message="first('name')" />
    </div>
    <div>
      <label for="email" class="label">E-mail</label>
      <input id="email" v-model="form.email" type="email" autocomplete="email" class="input" required />
      <FieldError :message="first('email')" />
    </div>
    <div>
      <label for="password" class="label">Senha</label>
      <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="input" required />
      <FieldError :message="first('password')" />
    </div>
    <div>
      <label for="password_confirmation" class="label">Confirmar senha</label>
      <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="input" required />
    </div>
    <button type="submit" class="btn btn-primary w-full" :disabled="submitting">{{ submitting ? 'Criando...' : 'Criar conta' }}</button>
  </form>

  <p class="mt-6 text-center text-sm text-slate-500">
    Já tem conta?
    <RouterLink :to="{ name: 'login' }" class="font-medium text-indigo-600 hover:underline">Entrar</RouterLink>
  </p>
</template>
