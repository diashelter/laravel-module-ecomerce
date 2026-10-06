import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { configureApiErrorHandlers } from './services/api'
import { useAuthStore } from './stores/auth'
import { useNotificationStore } from './stores/notifications'
import './styles/main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

const auth = useAuthStore()
const notifications = useNotificationStore()

// Global API error handling (401 / 403 / 5xx). 404, 409 and 422 are handled by each page.
configureApiErrorHandlers({
  onUnauthorized: () => {
    auth.clear()
    notifications.error('Sua sessão expirou. Faça login novamente.')
    void router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  },
  onForbidden: (message) => {
    notifications.error(message)
    void router.push({ name: 'forbidden' })
  },
  onServerError: (message) => notifications.error(message),
})

app.mount('#app')
