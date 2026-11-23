import { afterEach, describe, expect, it, vi } from 'vitest'
import { registerSW } from './registerSW'

function stubWorker() {
  const register = vi.fn().mockResolvedValue({})
  Object.defineProperty(navigator, 'serviceWorker', { value: { register }, configurable: true })
  return register
}

afterEach(() => {
  vi.unstubAllEnvs()
  Reflect.deleteProperty(navigator, 'serviceWorker')
})

describe('registerSW', () => {
  it('does nothing outside production builds', () => {
    const register = stubWorker()
    vi.stubEnv('PROD', false)
    registerSW()
    expect(register).not.toHaveBeenCalled()
  })

  it('does nothing in the demo build', () => {
    const register = stubWorker()
    vi.stubEnv('PROD', true)
    vi.stubEnv('VITE_DEMO', 'true')
    registerSW()
    expect(register).not.toHaveBeenCalled()
  })

  it('does nothing when the browser has no service workers', () => {
    vi.stubEnv('PROD', true)
    expect(() => registerSW()).not.toThrow()
  })

  it('registers /sw.js in production and swallows a failed registration', async () => {
    const register = stubWorker()
    register.mockRejectedValue(new Error('denied'))
    vi.stubEnv('PROD', true)
    registerSW()
    expect(register).toHaveBeenCalledWith('/sw.js', { scope: '/' })
    await Promise.resolve()
  })
})
