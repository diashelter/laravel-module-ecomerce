<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/FieldError.vue'
import { useFormErrors } from '@/composables/useFormErrors'
import { useStaffStore } from '@/stores/staff'

const staff = useStaffStore()
const router = useRouter()
const route = useRoute()
const { first, handle, reset } = useFormErrors()

const form = reactive({ email: '', password: '' })
const submitting = ref(false)

async function submit(): Promise<void> {
  submitting.value = true
  reset()
  try {
    await staff.login(form)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    await router.push(redirect ?? { name: 'admin.dashboard' })
  } catch (error) {
    handle(error)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <h1 class="mb-1 text-2xl font-bold">Painel administrativo</h1>
  <p class="mb-6 text-sm text-slate-500">Acesso restrito à equipe da loja.</p>

  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div>
      <label for="email" class="label">E-mail</label>
      <input id="email" v-model="form.email" type="email" autocomplete="email" class="input" required />
      <FieldError :message="first('email')" />
    </div>
    <div>
      <label for="password" class="label">Senha</label>
      <input id="password" v-model="form.password" type="password" autocomplete="current-password" class="input" required />
      <FieldError :message="first('password')" />
    </div>
    <button type="submit" class="btn btn-primary w-full" :disabled="submitting">{{ submitting ? 'Entrando...' : 'Entrar' }}</button>
  </form>

  <p class="mt-6 text-center text-sm text-slate-500">
    <RouterLink :to="{ name: 'products' }" class="font-medium text-indigo-600 hover:underline">Voltar para a loja</RouterLink>
  </p>
</template>
