const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/**
 * Converts a decimal string from the API ("19.90") into integer cents (1990),
 * so the cart can add values without floating point errors.
 */
export function toCents(value: string): number {
  const [integer = '0', decimals = ''] = value.split('.')
  return Number(integer) * 100 + Number(decimals.padEnd(2, '0').slice(0, 2))
}

export function formatCents(cents: number): string {
  return currency.format(cents / 100)
}

/** Formats a decimal string coming from the API ("1299.90" -> "R$ 1.299,90"). */
export function formatMoney(value: string | null | undefined): string {
  return value == null ? '—' : formatCents(toCents(value))
}
