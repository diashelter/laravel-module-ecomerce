import { onBeforeUnmount } from 'vue'

/**
 * Calls `task` repeatedly while it returns true. Used to follow order status changes
 * made asynchronously by the queue worker.
 */
export function usePolling(task: () => Promise<boolean>, intervalMs = 2000) {
  let timer: ReturnType<typeof setTimeout> | null = null
  let stopped = false

  async function tick(): Promise<void> {
    if (stopped) return
    let shouldContinue = false
    try {
      shouldContinue = await task()
    } catch {
      shouldContinue = false
    }
    if (shouldContinue && !stopped) timer = setTimeout(tick, intervalMs)
  }

  function start(): void {
    stop()
    stopped = false
    timer = setTimeout(tick, intervalMs)
  }

  function stop(): void {
    stopped = true
    if (timer) clearTimeout(timer)
    timer = null
  }

  onBeforeUnmount(stop)

  return { start, stop }
}
