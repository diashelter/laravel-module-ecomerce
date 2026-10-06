const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/** Message shown under the price field when the typed text is not a valid amount. */
export const INVALID_PRICE_MESSAGE = 'Informe um preço válido, por exemplo 199,90.'

/** Formats integer cents as BRL (129990 -> "R$ 1.299,90"). Renders a dash when there is no value. */
export function formatCents(cents: number | null | undefined): string {
  return cents == null ? '—' : currency.format(cents / 100)
}

/**
 * Reads the price the admin types, in reais ("199,90", "199.90", "199,9" or "199"), into integer cents.
 * Returns null for anything else: empty, more than 2 decimals, thousands separators, signs or letters.
 */
export function parseReaisInput(text: string): number | null {
  const match = /^(\d+)(?:[.,](\d{1,2}))?$/.exec(text.trim())
  if (match === null) return null

  const [, reais = '0', decimals = ''] = match
  return Number(reais) * 100 + Number(decimals.padEnd(2, '0'))
}

/** Renders integer cents as the text of the price field (19990 -> "199,90"). */
export function centsToReaisInput(cents: number): string {
  return `${Math.floor(cents / 100)},${String(cents % 100).padStart(2, '0')}`
}
