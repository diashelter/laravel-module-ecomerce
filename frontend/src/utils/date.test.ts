/// <reference types="node" />
import { afterEach, describe, expect, it } from 'vitest'
import { formatDay } from './date'

describe('date', () => {
  const originalTimezone = process.env.TZ

  afterEach(() => {
    process.env.TZ = originalTimezone
  })

  it('formats a date without shifting it to the previous day', () => {
    process.env.TZ = 'America/Sao_Paulo'

    // The trap: reading the day as a Date makes it midnight UTC, which is still the 8th in São Paulo.
    expect(new Date('2026-10-09').toLocaleDateString('pt-BR')).toBe('08/10/2026')
    expect(formatDay('2026-10-09')).toBe('09/10/2026')
  })
})
