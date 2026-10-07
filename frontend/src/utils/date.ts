export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('pt-BR')
}

export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

/**
 * Formats a day without a time ("2026-10-09" -> "09/10/2026"). It never goes through `new Date(iso)`:
 * that reads the text as midnight UTC, which is still the previous day in São Paulo.
 */
export function formatDay(isoDay: string): string {
  const [year, month, day] = isoDay.split('-')
  return `${day}/${month}/${year}`
}
