import { describe, expect, it } from 'vitest'
import { formatPostalCode } from './postalCode'

describe('postal code', () => {
  it('formats a postal code with a hyphen', () => {
    expect(formatPostalCode('01310100')).toBe('01310-100')
  })
})
