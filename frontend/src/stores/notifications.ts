import { defineStore } from 'pinia'
import { ref } from 'vue'

export type NotificationType = 'success' | 'error' | 'info'

export interface Notification {
  id: number
  type: NotificationType
  message: string
}

let nextId = 1

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref<Notification[]>([])

  function notify(message: string, type: NotificationType = 'info', timeoutMs = 4000): void {
    const id = nextId++
    notifications.value.push({ id, type, message })
    setTimeout(() => dismiss(id), timeoutMs)
  }

  function dismiss(id: number): void {
    notifications.value = notifications.value.filter((notification) => notification.id !== id)
  }

  return {
    notifications,
    notify,
    dismiss,
    success: (message: string) => notify(message, 'success'),
    error: (message: string) => notify(message, 'error', 6000),
  }
})
