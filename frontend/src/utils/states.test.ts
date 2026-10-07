import { describe, expect, it } from 'vitest'
import { BRAZILIAN_STATES } from './states'

describe('states', () => {
  it('offers the 27 states in alphabetical order', () => {
    expect(BRAZILIAN_STATES).toHaveLength(27)
    expect(new Set(BRAZILIAN_STATES).size).toBe(27)
    expect([...BRAZILIAN_STATES]).toEqual([...BRAZILIAN_STATES].sort())
    expect(BRAZILIAN_STATES[0]).toBe('AC')
    expect(BRAZILIAN_STATES[26]).toBe('TO')
  })
})
