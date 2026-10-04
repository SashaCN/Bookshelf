import { describe, expect, it } from 'vitest'
import { useOnline } from './online'

describe('useOnline', () => {
  it('follows the browser going offline and coming back', () => {
    const { online } = useOnline()

    window.dispatchEvent(new Event('offline'))
    expect(online.value).toBe(false)

    window.dispatchEvent(new Event('online'))
    expect(online.value).toBe(true)
  })

  it('shares one state between all users of it', () => {
    const first = useOnline()
    const second = useOnline()

    window.dispatchEvent(new Event('offline'))

    expect(first.online.value).toBe(false)
    expect(second.online.value).toBe(false)

    window.dispatchEvent(new Event('online'))
  })
})
