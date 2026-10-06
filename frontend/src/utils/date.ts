export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('pt-BR')
}

export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}
