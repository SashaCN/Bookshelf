import { describe, expect, it } from 'vitest'
import { safeRedirect } from './redirect'

describe('safeRedirect', () => {
  it('keeps an in-app path with its query', () => {
    expect(safeRedirect('/library/7?tab=x')).toBe('/library/7?tab=x')
  })

  it('takes the first of repeated query values', () => {
    expect(safeRedirect(['/stats', '/quotes'])).toBe('/stats')
  })

  it.each([undefined, null, '', 'https://evil.example', '//evil.example', '/\\evil.example', 'library', 42])(
    'ignores %s',
    (value) => {
      expect(safeRedirect(value)).toBeNull()
    },
  )

  it('does not send the reader back to the guest-only forms', () => {
    expect(safeRedirect('/login')).toBeNull()
    expect(safeRedirect('/register?x=1')).toBeNull()
    expect(safeRedirect('/login-help')).toBe('/login-help')
  })
})
