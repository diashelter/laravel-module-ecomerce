import { describe, expect, it } from 'vitest'
import { centsToReaisInput, formatCents, INVALID_PRICE_MESSAGE, parseReaisInput } from './money'

// Intl.NumberFormat('pt-BR') separates the symbol with a no-break space.
const nbsp = ' '

describe('money', () => {
  it('formats cents as BRL', () => {
    expect(formatCents(129990)).toBe(`R$${nbsp}1.299,90`)
    expect(formatCents(5)).toBe(`R$${nbsp}0,05`)
    expect(formatCents(0)).toBe(`R$${nbsp}0,00`)
  })

  it('renders a dash for a missing value', () => {
    expect(formatCents(null)).toBe('—')
    expect(formatCents(undefined)).toBe('—')
  })

  it('parses reais input into cents', () => {
    expect(parseReaisInput('199,90')).toBe(19990)
    expect(parseReaisInput('199.90')).toBe(19990)
    expect(parseReaisInput('199,9')).toBe(19990)
    expect(parseReaisInput('199')).toBe(19900)
  })

  it('rejects invalid reais input', () => {
    for (const text of ['', '19,999', '1.299,90', '-10', 'abc']) {
      expect(parseReaisInput(text), text).toBeNull()
    }
  })

  it('exposes the invalid price message', () => {
    expect(INVALID_PRICE_MESSAGE).toBe('Informe um preço válido, por exemplo 199,90.')
  })

  it('renders cents as reais input', () => {
    expect(centsToReaisInput(19990)).toBe('199,90')
    expect(centsToReaisInput(5)).toBe('0,05')
    expect(centsToReaisInput(129990)).toBe('1299,90')
  })
})
