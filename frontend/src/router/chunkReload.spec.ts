import { beforeEach, describe, expect, it, vi } from 'vitest'
import { isChunkLoadError, reloadOnce } from './chunkReload'

describe('chunk load recovery', () => {
  beforeEach(() => sessionStorage.clear())

  it('recognises a failed dynamic import', () => {
    expect(isChunkLoadError(new TypeError('Failed to fetch dynamically imported module: /assets/a.js'))).toBe(true)
    expect(isChunkLoadError(new Error('Something else'))).toBe(false)
  })

  it('reloads once to the target path, then refuses to loop', () => {
    const reload = vi.fn()

    expect(reloadOnce('/stats', reload)).toBe(true)
    expect(reload).toHaveBeenCalledWith('/stats')

    expect(reloadOnce('/stats', reload)).toBe(false)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('does not reload when storage is unavailable', () => {
    const reload = vi.fn()
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked')
    })

    expect(reloadOnce('/stats', reload)).toBe(false)
    expect(reload).not.toHaveBeenCalled()
    vi.restoreAllMocks()
  })
})
