import { effectScope } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { PositionPayload } from '@/types/reader'
import { SAVE_INTERVAL_MS, usePositionSaver } from './usePositionSaver'

function setVisibility(state: 'visible' | 'hidden') {
  Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state })
  document.dispatchEvent(new Event('visibilitychange'))
}

describe('usePositionSaver', () => {
  let position: PositionPayload | null
  const save = vi.fn<(position: PositionPayload) => Promise<unknown>>()

  const started: ReturnType<typeof usePositionSaver>[] = []

  function saver() {
    const created = usePositionSaver({ read: () => position, save })
    started.push(created)
    return created
  }

  beforeEach(() => {
    vi.useFakeTimers()
    position = { chapter: 2, chapter_progress: 0.25 }
    save.mockReset()
    save.mockResolvedValue(undefined)
  })

  afterEach(() => {
    // Listeners of one test must not hear the events of the next.
    for (const created of started.splice(0)) void created.stop(false)

    setVisibility('visible')
    vi.useRealTimers()
  })

  it('sends the position every 15 seconds', async () => {
    saver().start()

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS - 1)
    expect(save).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1)
    expect(save).toHaveBeenCalledTimes(1)
    expect(save).toHaveBeenCalledWith({ chapter: 2, chapter_progress: 0.25 })

    position = { chapter: 2, chapter_progress: 0.5 }
    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
    expect(save).toHaveBeenCalledTimes(2)
    expect(save).toHaveBeenLastCalledWith({ chapter: 2, chapter_progress: 0.5 })
  })

  it('does not send a position that has been sent', async () => {
    saver().start()

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS * 4)

    expect(save).toHaveBeenCalledTimes(1)
  })

  it('does not send anything while there is no position', async () => {
    position = null
    saver().start()

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS * 2)

    expect(save).not.toHaveBeenCalled()
  })

  it('sends the position when the page is hidden, and not when it is shown again', async () => {
    saver().start()

    setVisibility('visible')
    expect(save).not.toHaveBeenCalled()

    setVisibility('hidden')
    await vi.advanceTimersByTimeAsync(0)
    expect(save).toHaveBeenCalledTimes(1)
    // The page is going out of sight, and the request must not go with it.
    expect(save).toHaveBeenCalledWith({ chapter: 2, chapter_progress: 0.25 }, { keepalive: true })
  })

  it('sends the position when the page is going away', async () => {
    saver().start()

    window.dispatchEvent(new Event('pagehide'))
    await vi.advanceTimersByTimeAsync(0)

    expect(save).toHaveBeenCalledTimes(1)
    expect(save).toHaveBeenCalledWith({ chapter: 2, chapter_progress: 0.25 }, { keepalive: true })
  })

  it('sends the position as it leaves with keepalive, and the usual ones without', async () => {
    const { start, stop } = saver()
    start()

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
    expect(save).toHaveBeenLastCalledWith({ chapter: 2, chapter_progress: 0.25 })

    position = { chapter: 2, chapter_progress: 0.5 }
    await stop()
    expect(save).toHaveBeenLastCalledWith({ chapter: 2, chapter_progress: 0.5 }, { keepalive: true })
  })

  it('sends a position it is given without keepalive, unless told that the page is leaving', async () => {
    const { flush } = saver()

    await flush({ chapter: 1, chapter_progress: 1 })
    expect(save).toHaveBeenLastCalledWith({ chapter: 1, chapter_progress: 1 })

    await flush({ chapter: 2, chapter_progress: 0.9 }, { keepalive: true })
    expect(save).toHaveBeenLastCalledWith({ chapter: 2, chapter_progress: 0.9 }, { keepalive: true })
  })

  it('sends the position one last time when it is stopped, and then listens to nothing', async () => {
    const { start, stop } = saver()
    start()

    await stop()
    expect(save).toHaveBeenCalledTimes(1)

    position = { chapter: 5, chapter_progress: 0.9 }
    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS * 2)
    setVisibility('hidden')
    window.dispatchEvent(new Event('pagehide'))
    await vi.advanceTimersByTimeAsync(0)

    expect(save).toHaveBeenCalledTimes(1)
  })

  it('sends the position when the scope it belongs to ends', async () => {
    const scope = effectScope()
    scope.run(() => saver().start())

    scope.stop()
    await vi.advanceTimersByTimeAsync(0)

    expect(save).toHaveBeenCalledTimes(1)
  })

  it('sends a position it is given, as the reader leaves a chapter', async () => {
    const { start, flush } = saver()
    start()

    await flush({ chapter: 2, chapter_progress: 1 })

    expect(save).toHaveBeenCalledWith({ chapter: 2, chapter_progress: 1 })
  })

  it('tries again at the next time when sending failed, without making a fuss', async () => {
    save.mockRejectedValueOnce(new Error('offline'))
    saver().start()

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
    expect(save).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
    expect(save).toHaveBeenCalledTimes(2)
    expect(save).toHaveBeenLastCalledWith({ chapter: 2, chapter_progress: 0.25 })

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
    expect(save).toHaveBeenCalledTimes(2)
  })

  it('sends positions one at a time and in order', async () => {
    const finish: (() => void)[] = []
    save.mockImplementation(() => new Promise<void>((resolve) => finish.push(resolve)))
    const { flush } = saver()

    void flush({ chapter: 1, chapter_progress: 0.1 })
    void flush({ chapter: 1, chapter_progress: 0.2 })
    await vi.advanceTimersByTimeAsync(0)

    // The second waits until the first has been answered.
    expect(save).toHaveBeenCalledTimes(1)

    finish[0]!()
    await vi.advanceTimersByTimeAsync(0)
    expect(save).toHaveBeenCalledTimes(2)
    expect(save).toHaveBeenLastCalledWith({ chapter: 1, chapter_progress: 0.2 })
  })

  it('does not start twice when started twice', async () => {
    const { start } = saver()
    start()
    start()

    await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)

    expect(save).toHaveBeenCalledTimes(1)
  })
})
