import { describe, expect, it } from 'vitest'
import { useApi } from '~/composables/useApi'

describe('useApi', () => {
  it('shares a single client across the app', () => {
    expect(useApi()).toBe(useApi())
  })
})
