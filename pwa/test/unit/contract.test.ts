import { describe, expect, it } from 'vitest'
import { fillPath } from '~/services/api/contract'

describe('fillPath', () => {
  it('replaces every placeholder with an encoded value', () => {
    expect(fillPath('/api/a/{x}/b/{y}', { x: 'é', y: 3 })).toBe('/api/a/%C3%A9/b/3')
  })

  it('refuses a missing parameter instead of calling a wrong URL', () => {
    expect(() => fillPath('/api/me/sessions/{id}', undefined)).toThrow('Missing path parameter "id"')
  })
})
